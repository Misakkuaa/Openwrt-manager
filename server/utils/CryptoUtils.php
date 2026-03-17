<?php

class CryptoUtils {
    private static $aesKey = null;
    
    /**
     * 获取或生成AES密钥
     */
    public static function getAESKey() {
        if (self::$aesKey === null) {
            // 检查是否定义了AES_KEY_FILE常量
            $keyFile = defined('AES_KEY_FILE') ? AES_KEY_FILE : dirname(__DIR__) . '/config/aes.key';
            
            if (file_exists($keyFile)) {
                self::$aesKey = file_get_contents($keyFile);
            } else {
                // 使用固定的种子生成一致的密钥（临时解决方案）
                // 这确保了在同一服务器上始终生成相同的密钥
                $seed = 'owrt_server_aes_key_seed_2025';
                self::$aesKey = hash('sha256', $seed, true);
                
                // 尝试创建密钥文件（如果失败也不影响使用）
                try {
                    $keyDir = dirname($keyFile);
                    if (!is_dir($keyDir)) {
                        @mkdir($keyDir, 0755, true);
                    }
                    @file_put_contents($keyFile, self::$aesKey);
                    @chmod($keyFile, 0600);
                } catch (Exception $e) {
                    // 忽略文件写入错误，使用内存中的密钥
                }
            }
        }
        return self::$aesKey;
    }
    
    /**
     * 生成随机AES密钥
     */
    private static function generateAESKey() {
        return random_bytes(AES_KEY_LENGTH);
    }
    
    /**
     * 加密数据
     */
    public static function encrypt($data) {
        $key = self::getAESKey();
        $iv = random_bytes(AES_IV_LENGTH);
        $encrypted = openssl_encrypt(json_encode($data), AES_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // 组合IV和加密数据
        $result = $iv . $encrypted;
        
        // 添加HMAC验证
        $hmac = hash_hmac('sha256', $result, $key, true);
        
        return base64_encode($hmac . $result);
    }
    
    /**
     * 解密数据
     */
    public static function decrypt($encryptedData) {
        $key = self::getAESKey();
        $data = base64_decode($encryptedData);
        
        if ($data === false || strlen($data) < 32 + AES_IV_LENGTH) {
            throw new Exception('Invalid encrypted data');
        }
        
        // 分离HMAC和数据
        $hmac = substr($data, 0, 32);
        $payload = substr($data, 32);
        
        // 验证HMAC
        $expectedHmac = hash_hmac('sha256', $payload, $key, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            throw new Exception('HMAC verification failed');
        }
        
        // 分离IV和加密数据
        $iv = substr($payload, 0, AES_IV_LENGTH);
        $encrypted = substr($payload, AES_IV_LENGTH);
        
        $decrypted = openssl_decrypt($encrypted, AES_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return json_decode($decrypted, true);
    }
    
    /**
     * 使用指定密钥解密数据（用于设备特定的解密）
     */
    public static function decryptWithKey($encryptedData, $key) {
        $data = base64_decode($encryptedData);
        
        if ($data === false || strlen($data) < 32 + AES_IV_LENGTH) {
            throw new Exception('Invalid encrypted data');
        }
        
        // 分离HMAC和数据
        $hmac = substr($data, 0, 32);
        $payload = substr($data, 32);
        
        // 验证HMAC
        $expectedHmac = hash_hmac('sha256', $payload, $key, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            throw new Exception('HMAC verification failed');
        }
        
        // 分离IV和加密数据
        $iv = substr($payload, 0, AES_IV_LENGTH);
        $encrypted = substr($payload, AES_IV_LENGTH);
        
        $decrypted = openssl_decrypt($encrypted, AES_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return json_decode($decrypted, true);
    }
    
    /**
     * 使用指定密钥加密数据（用于设备特定的加密）
     */
    public static function encryptWithKey($data, $key) {
        $iv = random_bytes(AES_IV_LENGTH);
        $encrypted = openssl_encrypt($data, AES_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // 组合IV和加密数据
        $payload = $iv . $encrypted;
        
        // 添加HMAC验证
        $hmac = hash_hmac('sha256', $payload, $key, true);
        
        return base64_encode($hmac . $payload);
    }

    /**
     * 生成客户端AES密钥（用于密钥交换）
     */
    public static function generateClientKey() {
        return base64_encode(random_bytes(AES_KEY_LENGTH));
    }
    
    /**
     * 使用RSA加密AES密钥（用于安全传输）
     */
    public static function encryptAESKeyWithRSA($aesKey, $publicKey) {
        $encrypted = '';
        if (openssl_public_encrypt($aesKey, $encrypted, $publicKey)) {
            return base64_encode($encrypted);
        }
        throw new Exception('RSA encryption failed');
    }
}
