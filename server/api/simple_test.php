<?php
// 创建一个简单的测试端点来验证服务器接收
header('Content-Type: text/plain');

$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN';
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
$rawInput = file_get_contents('php://input');

$logMessage = "[$timestamp] Method: $method, Content-Type: $contentType, Length: $contentLength, Input: " . substr($rawInput, 0, 100) . "\n";

// 写入多个位置确保能记录到
file_put_contents('/tmp/simple_test.log', $logMessage, FILE_APPEND | LOCK_EX);
file_put_contents('/var/log/simple_test.log', $logMessage, FILE_APPEND | LOCK_EX);
file_put_contents('./simple_test.log', $logMessage, FILE_APPEND | LOCK_EX);

echo "Request logged at $timestamp\n";
echo "Method: $method\n";
echo "Content-Type: $contentType\n";
echo "Content-Length: $contentLength\n";
echo "Input received: " . strlen($rawInput) . " bytes\n";

if (!empty($rawInput)) {
    echo "First 200 chars: " . substr($rawInput, 0, 200) . "\n";
}

// 如果是客户端的加密数据，尝试解密
if (!empty($rawInput) && strlen($rawInput) > 100) {
    try {
        $jsonData = json_decode($rawInput, true);
        if ($jsonData && isset($jsonData['encrypted']) && $jsonData['encrypted'] === true) {
            echo "Detected encrypted client data!\n";
            echo "Encrypted data length: " . strlen($jsonData['data']) . "\n";
            
            // 记录特殊日志
            file_put_contents('/tmp/client_request_detected.log', 
                "[$timestamp] ENCRYPTED CLIENT REQUEST DETECTED!\n" . 
                "Data length: " . strlen($jsonData['data']) . "\n" .
                "Raw input: " . substr($rawInput, 0, 500) . "\n\n", 
                FILE_APPEND | LOCK_EX);
        }
    } catch (Exception $e) {
        echo "JSON parse error: " . $e->getMessage() . "\n";
    }
}
?>
