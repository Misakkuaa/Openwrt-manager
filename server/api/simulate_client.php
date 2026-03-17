<?php
// 模拟客户端发送命令结果
require_once '../config/config.php';
require_once '../utils/CryptoUtils.php';

try {
    // 模拟客户端发送的数据
    $commandData = [
        'device_id' => 'dcd0b342',
        'token' => 'eyJkZXZpY2VfaWQiOiJkY2QwYjM0MiIsImlzc3VlZF9hdCI6MTc1NTkxOTQzMiwiZXhwaXJlc19hdCI6MTc1NjAwNTgzMn0=.fb512fb04cc33e8e9ccbf6103ef0c929f66cbed496de8c98dac356cb515d75bf',
        'command_id' => 27,
        'command' => 'free -h && cat /proc/meminfo | head -10',
        'exit_code' => 0,
        'output' => 'Mock command output for testing purposes. This simulates the actual command execution result from the OpenWrt client.'
    ];
    
    echo "Simulating client request...\n";
    echo "Command data: " . json_encode($commandData) . "\n";
    
    // 加密数据（模拟客户端行为）
    $encryptedData = CryptoUtils::encrypt($commandData);
    echo "Encrypted data length: " . strlen($encryptedData) . "\n";
    echo "Encrypted data (first 100 chars): " . substr($encryptedData, 0, 100) . "...\n";
    
    // 发送到command_result.php
    $url = 'http://azurebt.mswifi.online/api/command_result.php';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encryptedData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/octet-stream',
        'Content-Length: ' . strlen($encryptedData)
    ]);
    
    echo "Sending request to server...\n";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Response Code: $httpCode\n";
    echo "Response: $response\n";
    
    if ($httpCode === 200) {
        echo "SUCCESS: Server accepted the request\n";
    } else {
        echo "ERROR: Server returned error code $httpCode\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
