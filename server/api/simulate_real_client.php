<?php
// 模拟真实客户端行为的测试脚本
require_once '../config/config.php';

echo "=== SIMULATING REAL CLIENT BEHAVIOR ===\n\n";

// 使用客户端的真实配置
$deviceId = 'dcd0b342';
$serverUrl = 'http://azurebt.mswifi.online';

// 模拟客户端使用种子生成密钥的方式（而不是从文件读取）
$aesKeySeed = 'owrt_server_aes_key_seed_2025';
$aesKeyRaw = hash('sha256', $aesKeySeed, true);
$aesKeyBase64 = base64_encode($aesKeyRaw);

echo "1. CLIENT CONFIGURATION SIMULATION:\n";
echo "Device ID: $deviceId\n";
echo "Server URL: $serverUrl\n";
echo "AES Key (seed-generated): " . substr($aesKeyBase64, 0, 20) . "...\n";
echo "Use Encryption: true\n\n";

// 模拟客户端的加密方式
function client_encrypt($data, $aesKeyBase64) {
    // 解码base64密钥
    $aesKey = base64_decode($aesKeyBase64);
    if (strlen($aesKey) !== 32) {
        throw new Exception("Invalid AES key length: " . strlen($aesKey));
    }
    
    $json = json_encode($data);
    $iv = openssl_random_pseudo_bytes(16);
    $ciphertext = openssl_encrypt($json, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
    
    // 客户端格式：HMAC(IV+密文) + IV + 密文
    $payload = $iv . $ciphertext;
    $hmac = hash_hmac('sha256', $payload, $aesKey, true);
    $finalData = $hmac . $payload;
    
    // 包装在JSON中
    return json_encode([
        'encrypted' => true,
        'data' => base64_encode($finalData)
    ]);
}

try {
    // 2. 模拟客户端认证流程
    echo "2. SIMULATING CLIENT AUTHENTICATION:\n";
    
    $authData = [
        'device_id' => $deviceId,
        'timestamp' => time()
    ];
    
    $encryptedAuthData = client_encrypt($authData, $aesKeyBase64);
    echo "Encrypted auth data created: " . strlen($encryptedAuthData) . " bytes\n";
    
    // 发送认证请求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl . '/api/authenticate.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedAuthData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $authResponse = curl_exec($ch);
    $authHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Auth HTTP Code: $authHttpCode\n";
    echo "Auth Response: " . substr($authResponse, 0, 100) . "...\n\n";
    
    // 3. 模拟客户端心跳
    echo "3. SIMULATING CLIENT HEARTBEAT:\n";
    
    $heartbeatData = [
        'device_id' => $deviceId,
        'token' => 'eyJkZXZpY2VfaWQiOiJkY2QwYjM0MiIsImlzc3VlZF9hdCI6MTc1NTkzNzA5NCwiZXhwaXJlc19hdCI6MTc1NjAyMzQ5NH0=.43ef2e15386eb48088b0b046149a5fa7a6c6410545e2a3be9230948f4808f51c',  // 从日志中的token
        'status' => 'online',
        'timestamp' => time()
    ];
    
    $encryptedHeartbeat = client_encrypt($heartbeatData, $aesKeyBase64);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl . '/api/heartbeat.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedHeartbeat);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $heartbeatResponse = curl_exec($ch);
    $heartbeatHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Heartbeat HTTP Code: $heartbeatHttpCode\n";
    echo "Heartbeat Response: " . substr($heartbeatResponse, 0, 100) . "...\n\n";
    
    // 4. 检查是否有待执行的命令
    echo "4. CHECKING FOR PENDING COMMANDS:\n";
    
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $stmt = $pdo->prepare("SELECT id, command FROM device_commands WHERE device_id = ? AND status = 'pending' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$deviceId]);
    $pendingCommand = $stmt->fetch();
    
    if ($pendingCommand) {
        echo "Found pending command: ID {$pendingCommand['id']}\n";
        echo "Command: {$pendingCommand['command']}\n";
        
        // 标记为已发送
        $stmt = $pdo->prepare("UPDATE device_commands SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->execute([$pendingCommand['id']]);
        echo "Marked as sent\n\n";
        
        // 5. 模拟命令执行和结果发送
        echo "5. SIMULATING COMMAND EXECUTION:\n";
        
        // 模拟执行命令
        echo "Executing: {$pendingCommand['command']}\n";
        $output = shell_exec($pendingCommand['command'] . ' 2>&1');
        $exitCode = 0; // 假设成功
        
        echo "Exit Code: $exitCode\n";
        echo "Output: " . substr($output, 0, 100) . "...\n\n";
        
        // 6. 发送结果
        echo "6. SENDING COMMAND RESULT:\n";
        
        $resultData = [
            'device_id' => $deviceId,
            'token' => 'eyJkZXZpY2VfaWQiOiJkY2QwYjM0MiIsImlzc3VlZF9hdCI6MTc1NTkzNzA5NCwiZXhwaXJlc19hdCI6MTc1NjAyMzQ5NH0=.43ef2e15386eb48088b0b046149a5fa7a6c6410545e2a3be9230948f4808f51c',
            'command_id' => (int)$pendingCommand['id'],
            'command' => $pendingCommand['command'],
            'exit_code' => $exitCode,
            'output' => $output ?: ''
        ];
        
        $encryptedResult = client_encrypt($resultData, $aesKeyBase64);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serverUrl . '/api/command_result.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedResult);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $resultResponse = curl_exec($ch);
        $resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "Result HTTP Code: $resultHttpCode\n";
        echo "Result Response: " . substr($resultResponse, 0, 100) . "...\n\n";
        
        // 7. 验证数据库更新
        echo "7. VERIFYING DATABASE UPDATE:\n";
        
        $stmt = $pdo->prepare("SELECT status, exit_code, output, completed_at FROM device_commands WHERE id = ?");
        $stmt->execute([$pendingCommand['id']]);
        $updatedCommand = $stmt->fetch();
        
        if ($updatedCommand) {
            echo "Updated Status: {$updatedCommand['status']}\n";
            echo "Exit Code: {$updatedCommand['exit_code']}\n";
            echo "Has Output: " . ($updatedCommand['output'] ? 'YES' : 'NO') . "\n";
            echo "Completed At: {$updatedCommand['completed_at']}\n";
            
            if ($updatedCommand['status'] === 'completed') {
                echo "🎉 FULL CLIENT SIMULATION SUCCESSFUL!\n";
            } else {
                echo "❌ Command not marked as completed\n";
            }
        } else {
            echo "❌ Command not found after result submission\n";
        }
        
    } else {
        echo "No pending commands found\n";
        
        // 创建一个测试命令
        $testCommand = 'echo "Client simulation test at ' . date('Y-m-d H:i:s') . '"';
        $stmt = $pdo->prepare("INSERT INTO device_commands (device_id, command, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$deviceId, $testCommand]);
        $newCommandId = $pdo->lastInsertId();
        
        echo "Created new test command ID: $newCommandId\n";
        echo "Run this script again to simulate processing this command\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
