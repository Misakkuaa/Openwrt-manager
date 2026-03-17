<?php
/**
 * 接收客户端命令执行结果的API
 */

header('Content-Type: application/json');

// 启用CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// 加载配置文件
require_once '../config/config.php';
require_once '../utils/CryptoUtils.php';

try {
    error_log("=== Command Result API Called ===");
    
    // 记录到API监控日志
    $monitorLog = '/tmp/owrt_api_calls.log';
    file_put_contents($monitorLog, 
        date('Y-m-d H:i:s') . " - command_result.php called from " . $_SERVER['REMOTE_ADDR'] . "\n", 
        FILE_APPEND | LOCK_EX);
    
    $debugLog = '/tmp/command_result_debug.log';
    file_put_contents($debugLog, 
        "\n=== " . date('Y-m-d H:i:s') . " ===\n", 
        FILE_APPEND | LOCK_EX);
    
    // 直接使用PDO连接
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $cryptoUtils = new CryptoUtils();
    
    // 获取POST数据
    $rawData = file_get_contents('php://input');
    error_log("Raw data received: " . substr($rawData, 0, 200) . "...");
    error_log("Raw data length: " . strlen($rawData));
    file_put_contents('/tmp/command_result_debug.log', 
        "Raw data length: " . strlen($rawData) . "\n", 
        FILE_APPEND | LOCK_EX);
    
    if (empty($rawData)) {
        $error = 'No data received';
        file_put_contents('/tmp/command_result_debug.log', "ERROR: $error\n", FILE_APPEND | LOCK_EX);
        throw new Exception($error);
    }

    // 检查是否为加密数据
    $requestData = null;
    $isEncrypted = false;
    
    // 尝试解析为JSON
    $jsonData = json_decode($rawData, true);
    
    if ($jsonData && isset($jsonData['encrypted']) && $jsonData['encrypted'] === true && isset($jsonData['data'])) {
        // 客户端发送的包装格式：{"encrypted": true, "data": "base64_data"}
        try {
            error_log("Processing wrapped encrypted data from client");
            file_put_contents('/tmp/command_result_debug.log', 
                "Processing wrapped encrypted data from client\n", 
                FILE_APPEND | LOCK_EX);
            $decryptedData = CryptoUtils::decryptWithKey($jsonData['data'], CryptoUtils::getAESKey());
            $requestData = $decryptedData;
            $isEncrypted = true;
            error_log("Client wrapped data decrypted successfully");
        } catch (Exception $e) {
            error_log("Client wrapped data decryption failed: " . $e->getMessage());
            file_put_contents('/tmp/command_result_debug.log', 
                "ERROR: Client wrapped data decryption failed: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            throw new Exception('Failed to decrypt wrapped data: ' . $e->getMessage());
        }
    } else if ($jsonData && isset($jsonData['device_id'])) {
        // 明文JSON数据，包含device_id字段
        $requestData = $jsonData;
        error_log("Received plaintext data: " . json_encode($requestData));
    } else {
        // 尝试直接解密base64数据（测试格式）
        try {
            error_log("Attempting to decrypt direct base64 data...");
            file_put_contents('/tmp/command_result_debug.log', 
                "Attempting to decrypt direct base64 data...\n", 
                FILE_APPEND | LOCK_EX);
            $decryptedData = CryptoUtils::decryptWithKey($rawData, CryptoUtils::getAESKey());
            $requestData = $decryptedData;
            $isEncrypted = true;
            error_log("Decrypted direct base64 data successfully");
            file_put_contents('/tmp/command_result_debug.log', 
                "Decrypted data successfully: " . json_encode($decryptedData) . "\n", 
                FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log("Direct base64 decryption failed: " . $e->getMessage());
            file_put_contents('/tmp/command_result_debug.log', 
                "ERROR: Decryption failed: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            throw new Exception('Failed to decrypt or parse data: ' . $e->getMessage());
        }
    }
    
    if (!$requestData) {
        throw new Exception('Failed to parse request data');
    }

    // 验证必需字段
    $requiredFields = ['device_id', 'token', 'command_id', 'command', 'exit_code', 'output'];
    foreach ($requiredFields as $field) {
        if (!isset($requestData[$field])) {
            $error = "Missing required field: {$field}";
            file_put_contents('/tmp/command_result_debug.log', "ERROR: $error\n", FILE_APPEND | LOCK_EX);
            throw new Exception($error);
        }
    }
    
    file_put_contents('/tmp/command_result_debug.log', 
        "All required fields present: device_id={$requestData['device_id']}, command_id={$requestData['command_id']}\n", 
        FILE_APPEND | LOCK_EX);

    // 简单的token验证 - 检查token格式
    $token = $requestData['token'];
    if (strlen($token) < 10) {
        error_log("Token validation failed - invalid format");
        http_response_code(401);
        throw new Exception('Invalid device token');
    }
    
    error_log("Token validation passed for device: {$requestData['device_id']}");
    error_log("Command data - ID: {$requestData['command_id']}, Exit Code: {$requestData['exit_code']}, Output Length: " . strlen($requestData['output']));

    // 更新命令执行结果
    error_log("SQL Query parameters: command_id={$requestData['command_id']}, device_id={$requestData['device_id']}, exit_code={$requestData['exit_code']}");
    file_put_contents('/tmp/command_result_debug.log', 
        "Updating command: command_id={$requestData['command_id']}, device_id={$requestData['device_id']}, exit_code={$requestData['exit_code']}, output_length=" . strlen($requestData['output']) . "\n", 
        FILE_APPEND | LOCK_EX);

    try {
        // 使用PDO直接更新数据库
        $updateQuery = "UPDATE device_commands SET 
                        status = :status, 
                        exit_code = :exit_code, 
                        output = :output, 
                        completed_at = :completed_at 
                        WHERE id = :command_id AND device_id = :device_id";
        
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([
            'status' => 'completed',
            'exit_code' => $requestData['exit_code'],
            'output' => $requestData['output'],
            'completed_at' => date('Y-m-d H:i:s'),
            'command_id' => $requestData['command_id'],
            'device_id' => $requestData['device_id']
        ]);
        
        $rowCount = $stmt->rowCount();
        
        error_log("Database update result: affected rows = {$rowCount}");
        file_put_contents('/tmp/command_result_debug.log', 
            "Database update result: affected rows = {$rowCount}\n", 
            FILE_APPEND | LOCK_EX);
        
    } catch (Exception $e) {
        error_log("Database update exception: " . $e->getMessage());
        file_put_contents('/tmp/command_result_debug.log', 
            "Database update exception: " . $e->getMessage() . "\n", 
            FILE_APPEND | LOCK_EX);
        throw new Exception('Failed to update command result: ' . $e->getMessage());
    }
    
    if ($rowCount === 0) {
        error_log("Warning: No rows were updated. Command ID {$requestData['command_id']} may not exist or belong to device {$requestData['device_id']}");
        file_put_contents('/tmp/command_result_debug.log', 
            "WARNING: No rows updated for command_id={$requestData['command_id']}, device_id={$requestData['device_id']}\n", 
            FILE_APPEND | LOCK_EX);
        
        // 检查命令是否存在
        $stmt = $pdo->prepare("SELECT id, device_id, status FROM device_commands WHERE id = :command_id");
        $stmt->execute(['command_id' => $requestData['command_id']]);
        $existingCommand = $stmt->fetch();
        
        if ($existingCommand) {
            error_log("Command exists: " . json_encode($existingCommand));
            file_put_contents('/tmp/command_result_debug.log', 
                "Command exists: " . json_encode($existingCommand) . "\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            error_log("Command ID {$requestData['command_id']} does not exist in database");
            file_put_contents('/tmp/command_result_debug.log', 
                "Command ID {$requestData['command_id']} does not exist in database\n", 
                FILE_APPEND | LOCK_EX);
        }
    } else {
        error_log("Command result updated successfully");
        file_put_contents('/tmp/command_result_debug.log', 
            "SUCCESS: Command result updated successfully\n", 
            FILE_APPEND | LOCK_EX);
    }

    // 记录日志
    try {
        $logMessage = sprintf(
            'Command executed (ID: %d, Exit: %d): %s', 
            $requestData['command_id'],
            $requestData['exit_code'],
            substr($requestData['command'], 0, 100)
        );

        $stmt = $pdo->prepare("INSERT INTO logs (level, message, created_at) VALUES (?, ?, ?)");
        $stmt->execute([
            'INFO',
            $logMessage,
            date('Y-m-d H:i:s')
        ]);
        
        error_log("Log entry created successfully");
        file_put_contents('/tmp/command_result_debug.log', 
            "Log entry created: $logMessage\n", 
            FILE_APPEND | LOCK_EX);
            
    } catch (Exception $e) {
        error_log("Failed to create log entry: " . $e->getMessage());
        file_put_contents('/tmp/command_result_debug.log', 
            "Failed to create log entry: " . $e->getMessage() . "\n", 
            FILE_APPEND | LOCK_EX);
        // 不抛出异常，日志失败不应该影响主要功能
    }

    // 构建响应
    $response = [
        'status' => 'success',
        'message' => 'Command result received',
        'timestamp' => time()
    ];

    // 根据请求类型返回响应（加密或明文）
    if ($isEncrypted) {
        // 加密请求，返回直接的base64加密响应（与客户端格式一致）
        error_log("Sending encrypted response");
        try {
            $encryptedResponse = CryptoUtils::encrypt($response);
            error_log("Response encrypted successfully, length: " . strlen($encryptedResponse));
            echo $encryptedResponse;
        } catch (Exception $e) {
            error_log("Failed to encrypt response: " . $e->getMessage());
            throw new Exception('Failed to encrypt response: ' . $e->getMessage());
        }
    } else {
        // 明文请求，返回明文响应
        error_log("Sending plaintext response: " . json_encode($response));
        echo json_encode($response);
    }

} catch (Exception $e) {
    error_log("Command result API error: " . $e->getMessage());
    file_put_contents('/tmp/command_result_debug.log', 
        "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
        FILE_APPEND | LOCK_EX);
    
    $errorResponse = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ];

    // 如果是认证错误，返回401状态码
    if (strpos($e->getMessage(), 'token') !== false) {
        http_response_code(401);
    } else {
        http_response_code(400);
    }

    echo json_encode($errorResponse);
}
?>
