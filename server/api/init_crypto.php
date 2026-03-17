<?php
/**
 * 初始化AES密钥API
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../utils/CryptoUtils.php';

try {
    // 强制初始化密钥
    $key = CryptoUtils::getAESKey();
    
    // 确保密钥文件存在
    if (!file_exists(AES_KEY_FILE)) {
        // 手动创建密钥文件
        $keyDir = dirname(AES_KEY_FILE);
        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0755, true);
        }
        
        file_put_contents(AES_KEY_FILE, $key);
        chmod(AES_KEY_FILE, 0600);
    }
    
    $response = [
        'status' => 'success',
        'message' => 'AES key initialized',
        'key_file' => AES_KEY_FILE,
        'key_exists' => file_exists(AES_KEY_FILE),
        'key_size' => strlen($key),
        'key_base64' => base64_encode($key),
        'key_hash' => hash('sha256', $key)
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
