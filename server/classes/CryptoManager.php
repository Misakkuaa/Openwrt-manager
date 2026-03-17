<?php
/**
 * 加密管理器 - 处理AES加密/解密和密钥管理
 */

class CryptoManager {
    private const CIPHER_METHOD = 'AES-256-CBC';
    private const HASH_ALGO = 'sha256';
    
    /**
     * 生成随机密钥
     */
    public static function generateKey($length = 32) {
        return random_bytes($length);
    }
    
    /**
     * 生成随机IV
     */
    public static function generateIV() {
        return random_bytes(openssl_cipher_iv_length(self::CIPHER_METHOD));
    }
    
    /**
     * AES加密
     */
    public static function encrypt($data, $key, $iv = null) {
        if ($iv === null) {
            $iv = self::generateIV();
        }
        
        $encrypted = openssl_encrypt($data, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // 返回 IV + 加密数据 的组合
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * AES解密
     */
    public static function decrypt($encryptedData, $key) {
        $data = base64_decode($encryptedData);
        if ($data === false) {
            throw new Exception('Invalid base64 data');
        }
        
        $ivLength = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, self::CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return $decrypted;
    }
    
    /**
     * 计算HMAC
     */
    public static function hmac($data, $key) {
        return hash_hmac(self::HASH_ALGO, $data, $key, true);
    }
    
    /**
     * 验证HMAC
     */
    public static function verifyHmac($data, $hmac, $key) {
        $expectedHmac = self::hmac($data, $key);
        return hash_equals($expectedHmac, $hmac);
    }
    
    /**
     * 加密并签名消息
     */
    public static function encryptMessage($message, $encryptKey, $hmacKey) {
        $encrypted = self::encrypt($message, $encryptKey);
        $hmac = self::hmac($encrypted, $hmacKey);
        
        return [
            'data' => $encrypted,
            'hmac' => base64_encode($hmac),
            'timestamp' => time()
        ];
    }
    
    /**
     * 验证并解密消息
     */
    public static function decryptMessage($encryptedMessage, $encryptKey, $hmacKey) {
        if (!isset($encryptedMessage['data']) || !isset($encryptedMessage['hmac'])) {
            throw new Exception('Invalid encrypted message format');
        }
        
        $hmac = base64_decode($encryptedMessage['hmac']);
        if ($hmac === false) {
            throw new Exception('Invalid HMAC format');
        }
        
        // 验证HMAC
        if (!self::verifyHmac($encryptedMessage['data'], $hmac, $hmacKey)) {
            throw new Exception('HMAC verification failed');
        }
        
        // 检查时间戳（防重放攻击）
        if (isset($encryptedMessage['timestamp'])) {
            $age = time() - $encryptedMessage['timestamp'];
            if ($age > 300) { // 5分钟超时
                throw new Exception('Message too old');
            }
        }
        
        return self::decrypt($encryptedMessage['data'], $encryptKey);
    }
    
    /**
     * 从设备ID派生密钥
     */
    public static function deriveKey($deviceId, $masterKey, $salt = '') {
        $info = $deviceId . $salt;
        return hash_pbkdf2('sha256', $masterKey, $info, 10000, 32, true);
    }
    
    /**
     * 生成设备专用密钥对
     */
    public static function generateDeviceKeys($deviceId, $masterKey) {
        $encryptKey = self::deriveKey($deviceId, $masterKey, 'encrypt');
        $hmacKey = self::deriveKey($deviceId, $masterKey, 'hmac');
        
        return [
            'encrypt' => $encryptKey,
            'hmac' => $hmacKey
        ];
    }
}
