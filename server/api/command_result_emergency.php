<?php
// 临时的command_result.php用于调试客户端通信
header('Content-Type: application/json');

// 启用详细的错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$timestamp = date('Y-m-d H:i:s');
$debugFile = '/tmp/command_result_emergency_debug.log';

function emergency_log($message) {
    global $debugFile, $timestamp;
    $fullMessage = "[$timestamp] $message\n";
    file_put_contents($debugFile, $fullMessage, FILE_APPEND | LOCK_EX);
    error_log($message);
}

emergency_log("=== EMERGENCY COMMAND_RESULT DEBUG ===");
emergency_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
emergency_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'));
emergency_log("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? '0'));
emergency_log("Remote Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    emergency_log("ERROR: Not a POST request");
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

emergency_log("POST request confirmed");

// 获取原始输入
$rawInput = file_get_contents('php://input');
emergency_log("Raw input length: " . strlen($rawInput));

if (empty($rawInput)) {
    emergency_log("ERROR: No input data");
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

emergency_log("Input data received, first 200 chars: " . substr($rawInput, 0, 200));

// 尝试解析JSON
$jsonData = json_decode($rawInput, true);
if ($jsonData) {
    emergency_log("JSON parsed successfully");
    
    if (isset($jsonData['encrypted']) && $jsonData['encrypted'] === true) {
        emergency_log("CLIENT ENCRYPTED FORMAT DETECTED!");
        emergency_log("Encrypted data length: " . strlen($jsonData['data']));
        
        // 尝试解密
        try {
            // 使用相同的密钥种子
            $seed = 'owrt_server_aes_key_seed_2025';
            $aesKey = hash('sha256', $seed, true);
            emergency_log("AES key generated from seed");
            
            $encryptedData = base64_decode($jsonData['data']);
            if ($encryptedData === false) {
                throw new Exception("Base64 decode failed");
            }
            
            emergency_log("Base64 decoded, length: " . strlen($encryptedData));
            
            // 解析格式: HMAC(32) + IV(16) + Ciphertext
            if (strlen($encryptedData) < 48) {
                throw new Exception("Encrypted data too short: " . strlen($encryptedData));
            }
            
            $hmac = substr($encryptedData, 0, 32);
            $iv = substr($encryptedData, 32, 16);
            $ciphertext = substr($encryptedData, 48);
            
            emergency_log("HMAC: " . strlen($hmac) . " bytes");
            emergency_log("IV: " . strlen($iv) . " bytes");
            emergency_log("Ciphertext: " . strlen($ciphertext) . " bytes");
            
            // 验证HMAC
            $payload = $iv . $ciphertext;
            $expectedHmac = hash_hmac('sha256', $payload, $aesKey, true);
            
            if (!hash_equals($hmac, $expectedHmac)) {
                throw new Exception("HMAC verification failed");
            }
            
            emergency_log("HMAC verification successful!");
            
            // 解密
            $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                throw new Exception("Decryption failed");
            }
            
            emergency_log("Decryption successful!");
            emergency_log("Decrypted data: " . $decrypted);
            
            $commandData = json_decode($decrypted, true);
            if (!$commandData) {
                throw new Exception("Failed to parse decrypted JSON");
            }
            
            emergency_log("Command data parsed successfully");
            
            // 提取关键信息
            $deviceId = $commandData['device_id'] ?? 'UNKNOWN';
            $commandId = $commandData['command_id'] ?? 'UNKNOWN';
            $exitCode = $commandData['exit_code'] ?? 'UNKNOWN';
            $output = $commandData['output'] ?? '';
            
            emergency_log("Device ID: $deviceId");
            emergency_log("Command ID: $commandId");
            emergency_log("Exit Code: $exitCode");
            emergency_log("Output length: " . strlen($output));
            
            // 尝试更新数据库
            try {
                $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                
                emergency_log("Database connection successful");
                
                $updateQuery = "UPDATE device_commands SET 
                                status = 'completed', 
                                exit_code = :exit_code, 
                                output = :output, 
                                completed_at = NOW() 
                                WHERE id = :command_id AND device_id = :device_id";
                
                $stmt = $pdo->prepare($updateQuery);
                $stmt->execute([
                    'exit_code' => $exitCode,
                    'output' => $output,
                    'command_id' => $commandId,
                    'device_id' => $deviceId
                ]);
                
                $rowCount = $stmt->rowCount();
                emergency_log("Database update result: $rowCount rows affected");
                
                if ($rowCount > 0) {
                    emergency_log("SUCCESS! Command $commandId updated successfully");
                    
                    // 返回成功响应
                    $response = ['status' => 'success', 'message' => 'Command result processed'];
                    echo json_encode($response);
                    
                    emergency_log("Response sent: " . json_encode($response));
                } else {
                    emergency_log("WARNING: No rows updated for command $commandId");
                    
                    // 检查命令是否存在
                    $checkStmt = $pdo->prepare("SELECT id, device_id, status FROM device_commands WHERE id = ?");
                    $checkStmt->execute([$commandId]);
                    $existing = $checkStmt->fetch();
                    
                    if ($existing) {
                        emergency_log("Command exists: " . json_encode($existing));
                    } else {
                        emergency_log("Command $commandId does not exist");
                    }
                    
                    echo json_encode(['status' => 'warning', 'message' => 'No rows updated']);
                }
                
            } catch (Exception $dbError) {
                emergency_log("Database error: " . $dbError->getMessage());
                echo json_encode(['status' => 'error', 'message' => 'Database error']);
            }
            
        } catch (Exception $cryptoError) {
            emergency_log("Crypto error: " . $cryptoError->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Decryption failed']);
        }
        
    } else {
        emergency_log("JSON data without encryption wrapper");
        echo json_encode(['status' => 'error', 'message' => 'Expected encrypted data']);
    }
} else {
    emergency_log("Failed to parse JSON");
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
}

emergency_log("=== END OF REQUEST ===");
?>
