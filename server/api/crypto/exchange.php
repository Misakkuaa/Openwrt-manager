<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../utils/CryptoUtils.php';

/**
 * 密钥交换端点
 * 为客户端提供AES加密密钥
 */

header('Content-Type: application/json');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // 获取请求数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    // 验证设备ID
    if (empty($data['device_id'])) {
        throw new Exception('Device ID is required');
    }
    
    // 生成AES密钥
    $aesKey = CryptoUtils::getAESKey();
    
    // 如果客户端提供了RSA公钥，使用RSA加密AES密钥
    if (isset($data['public_key'])) {
        $encryptedKey = CryptoUtils::encryptAESKeyWithRSA($aesKey, $data['public_key']);
        $response = [
            'success' => true,
            'encrypted_key' => $encryptedKey,
            'cipher' => AES_CIPHER,
            'key_id' => hash('sha256', $aesKey) // 密钥标识符
        ];
    } else {
        // 简化模式：直接返回Base64编码的密钥（仅用于测试）
        $response = [
            'success' => true,
            'key' => base64_encode($aesKey),
            'cipher' => AES_CIPHER,
            'key_id' => hash('sha256', $aesKey)
        ];
    }
    
    // 记录设备加密状态到数据库
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // 更新设备的加密状态
        $stmt = $pdo->prepare("
            UPDATE devices 
            SET encryption_enabled = TRUE,
                last_key_exchange = NOW(),
                encryption_cipher = :cipher,
                key_id = :key_id
            WHERE device_id = :device_id
        ");
        
        $stmt->execute([
            'cipher' => AES_CIPHER,
            'key_id' => hash('sha256', $aesKey),
            'device_id' => $data['device_id']
        ]);
        
        // 如果设备不存在，创建一个基本记录
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("
                INSERT INTO devices (device_id, encryption_enabled, last_key_exchange, encryption_cipher, key_id, first_seen, last_seen, status)
                VALUES (:device_id, TRUE, NOW(), :cipher, :key_id, NOW(), NOW(), 'online')
                ON DUPLICATE KEY UPDATE
                encryption_enabled = TRUE,
                last_key_exchange = NOW(),
                encryption_cipher = :cipher,
                key_id = :key_id
            ");
            
            $stmt->execute([
                'device_id' => $data['device_id'],
                'cipher' => AES_CIPHER,
                'key_id' => hash('sha256', $aesKey)
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Database error in key exchange: " . $e->getMessage());
        // 不影响密钥交换的成功，只记录错误
    }
    
    // 记录密钥交换日志
    error_log("Key exchange for device: " . $data['device_id'] . " (cipher: " . AES_CIPHER . ")");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Key exchange error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
