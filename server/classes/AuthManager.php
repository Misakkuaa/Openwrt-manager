<?php
/**
 * Authentication and Security Manager
 */

require_once 'Database.php';

class AuthManager {
    private $db;
    private $secretKey;
    
    public function __construct() {
        $this->db = new Database();
        $this->secretKey = $this->getSecretKey();
    }
    
    /**
     * Generate authentication token for device
     */
    public function generateToken($deviceId) {
        $payload = [
            'device_id' => $deviceId,
            'issued_at' => time(),
            'expires_at' => time() + (24 * 60 * 60) // 24 hours
        ];
        
        $token = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $token, $this->secretKey);
        
        // Store token in database
        $this->storeToken($deviceId, $token, $signature);
        
        return $token . '.' . $signature;
    }
    
    /**
     * Validate authentication token
     */
    public function validateToken($deviceId, $tokenString) {
        if (empty($tokenString)) {
            return false;
        }
        
        $parts = explode('.', $tokenString);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($token, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $token, $this->secretKey);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Decode and validate payload
        $payload = json_decode(base64_decode($token), true);
        if (!$payload) {
            return false;
        }
        
        // Check device ID
        if ($payload['device_id'] !== $deviceId) {
            return false;
        }
        
        // Check expiration
        if (time() > $payload['expires_at']) {
            return false;
        }
        
        // Check if token exists in database
        return $this->isTokenValid($deviceId, $token);
    }
    
    /**
     * Generate command signature
     */
    public function signCommand($command) {
        $hash = hash('sha256', $command);
        return 'SIG_' . strtoupper(substr($hash, 0, 8));
    }
    
    /**
     * Verify command signature
     */
    public function verifyCommandSignature($command, $signature) {
        $expectedSignature = $this->signCommand($command);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Revoke device token
     */
    public function revokeToken($deviceId) {
        return $this->db->delete('device_tokens', 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    
    /**
     * Get or generate secret key
     */
    private function getSecretKey() {
        $configFile = dirname(__DIR__) . '/config/secret.key';
        
        if (file_exists($configFile)) {
            return file_get_contents($configFile);
        }
        
        // Generate new secret key
        $key = bin2hex(random_bytes(32));
        file_put_contents($configFile, $key);
        chmod($configFile, 0600);
        
        return $key;
    }
    
    /**
     * Store token in database
     */
    private function storeToken($deviceId, $token, $signature) {
        $data = [
            'device_id' => $deviceId,
            'token' => $token,
            'signature' => $signature,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + (24 * 60 * 60))
        ];
        
        // Delete existing tokens for this device
        $this->db->delete('device_tokens', 'device_id = :device_id', ['device_id' => $deviceId]);
        
        // Insert new token
        return $this->db->insert('device_tokens', $data);
    }
    
    /**
     * Check if token is valid in database
     */
    private function isTokenValid($deviceId, $token) {
        $result = $this->db->selectOne(
            "SELECT * FROM device_tokens WHERE device_id = :device_id AND token = :token AND expires_at > NOW()",
            ['device_id' => $deviceId, 'token' => $token]
        );
        
        return !empty($result);
    }
    
    /**
     * Clean expired tokens
     */
    public function cleanExpiredTokens() {
        return $this->db->delete('device_tokens', 'expires_at < NOW()');
    }
    
    /**
     * Get device sessions
     */
    public function getDeviceSessions($deviceId) {
        return $this->db->select(
            "SELECT * FROM device_tokens WHERE device_id = :device_id ORDER BY created_at DESC",
            ['device_id' => $deviceId]
        );
    }
    
    /**
     * Rate limiting check
     */
    public function checkRateLimit($deviceId, $action, $maxAttempts = 10, $timeWindow = 300) {
        $cutoffTime = date('Y-m-d H:i:s', time() - $timeWindow);
        
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM security_log 
             WHERE device_id = :device_id AND action = :action AND created_at > :cutoff_time",
            [
                'device_id' => $deviceId,
                'action' => $action,
                'cutoff_time' => $cutoffTime
            ]
        );
        
        return $result['count'] < $maxAttempts;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($deviceId, $action, $details = '', $ipAddress = '') {
        $data = [
            'device_id' => $deviceId,
            'action' => $action,
            'details' => $details,
            'ip_address' => $ipAddress,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        error_log("Logging security event - device_id: $deviceId, action: $action");
        
        return $this->db->insert('security_log', $data);
    }
}
