<?php
// 实时监控command_result.php的请求
require_once '../config/config.php';

// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置详细的日志
function detailed_log($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // 写入文件
    file_put_contents('/tmp/command_result_debug.log', $log_message, FILE_APPEND | LOCK_EX);
    
    // 也写入数据库
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $stmt = $pdo->prepare("INSERT INTO logs (level, message) VALUES (?, ?)");
        $stmt->execute(['DEBUG', $message]);
    } catch (Exception $e) {
        // 忽略数据库错误，继续处理
    }
    
    echo $log_message;
}

detailed_log("=== REAL-TIME COMMAND_RESULT MONITOR STARTED ===");

// 检查是否有POST数据
$rawInput = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$contentType = $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN';
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;

detailed_log("HTTP Method: $method");
detailed_log("Content-Type: $contentType");
detailed_log("Content-Length: $contentLength");
detailed_log("Raw Input Length: " . strlen($rawInput));

if (!empty($rawInput)) {
    detailed_log("Raw Input (first 200 chars): " . substr($rawInput, 0, 200));
    
    // 尝试解析JSON
    $jsonData = json_decode($rawInput, true);
    if ($jsonData) {
        detailed_log("JSON parsed successfully");
        if (isset($jsonData['encrypted']) && $jsonData['encrypted'] === true) {
            detailed_log("Found encrypted JSON wrapper format");
            detailed_log("Encrypted data length: " . strlen($jsonData['data']));
            
            // 尝试解密
            try {
                // 使用相同的密钥
                $seed = 'owrt_server_aes_key_seed_2025';
                $aesKey = hash('sha256', $seed, true);
                
                $encryptedData = base64_decode($jsonData['data']);
                if ($encryptedData === false) {
                    detailed_log("ERROR: Base64 decode failed");
                } else {
                    detailed_log("Base64 decoded, length: " . strlen($encryptedData));
                    
                    // 解析格式: HMAC(32) + IV(16) + Ciphertext
                    if (strlen($encryptedData) >= 48) {
                        $hmac = substr($encryptedData, 0, 32);
                        $iv = substr($encryptedData, 32, 16);
                        $ciphertext = substr($encryptedData, 48);
                        
                        detailed_log("HMAC length: " . strlen($hmac));
                        detailed_log("IV length: " . strlen($iv));
                        detailed_log("Ciphertext length: " . strlen($ciphertext));
                        
                        // 验证HMAC
                        $payload = $iv . $ciphertext;
                        $expectedHmac = hash_hmac('sha256', $payload, $aesKey, true);
                        
                        if (hash_equals($hmac, $expectedHmac)) {
                            detailed_log("HMAC verification SUCCESS!");
                            
                            // 解密
                            $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
                            if ($decrypted !== false) {
                                detailed_log("Decryption SUCCESS!");
                                detailed_log("Decrypted data: " . $decrypted);
                                
                                $commandData = json_decode($decrypted, true);
                                if ($commandData) {
                                    detailed_log("Command data parsed successfully");
                                    detailed_log("Device ID: " . ($commandData['device_id'] ?? 'MISSING'));
                                    detailed_log("Command ID: " . ($commandData['command_id'] ?? 'MISSING'));
                                    detailed_log("Exit Code: " . ($commandData['exit_code'] ?? 'MISSING'));
                                    detailed_log("Output length: " . strlen($commandData['output'] ?? ''));
                                    
                                    // 这里应该更新数据库
                                    detailed_log("This request should be processed by command_result.php");
                                } else {
                                    detailed_log("ERROR: Failed to parse command data JSON");
                                }
                            } else {
                                detailed_log("ERROR: Decryption failed");
                            }
                        } else {
                            detailed_log("ERROR: HMAC verification failed");
                            detailed_log("Expected HMAC: " . bin2hex($expectedHmac));
                            detailed_log("Received HMAC: " . bin2hex($hmac));
                        }
                    } else {
                        detailed_log("ERROR: Encrypted data too short: " . strlen($encryptedData));
                    }
                }
            } catch (Exception $e) {
                detailed_log("ERROR: Exception during decryption: " . $e->getMessage());
            }
        } else {
            detailed_log("JSON data without encryption wrapper");
        }
    } else {
        detailed_log("Failed to parse JSON, treating as raw base64");
    }
} else {
    detailed_log("No POST data received");
}

detailed_log("=== MONITOR COMPLETE ===");

// 返回一个简单的响应
http_response_code(200);
echo "Monitor logged request";
?>
