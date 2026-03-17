<?php
/**
 * Device Authentication API with AES Encryption Support
 */

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';
require_once '../classes/AuthManager.php';
require_once '../utils/CryptoUtils.php';

$deviceManager = new DeviceManager();
$authManager = new AuthManager();

// Get client IP
$clientIP = $_SERVER['REMOTE_ADDR'];
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $clientIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

try {
    // Get POST data
    $rawInput = file_get_contents('php://input');
    
    // 检查是否为加密数据
    $isEncrypted = false;
    $input = null;
    
    // 尝试解析为JSON
    $jsonData = json_decode($rawInput, true);
    
    if ($jsonData && isset($jsonData['encrypted']) && $jsonData['encrypted'] === true) {
        // 处理加密数据
        if (!isset($jsonData['data'])) {
            throw new Exception('Encrypted data is missing');
        }
        
        try {
            // 使用全局密钥解密（密钥交换时返回的就是全局密钥）
            $input = CryptoUtils::decryptWithKey($jsonData['data'], CryptoUtils::getAESKey());
            $isEncrypted = true;
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt data: ' . $e->getMessage());
        }
    } else if ($jsonData) {
        // 处理未加密数据（向后兼容）
        $input = $jsonData;
    } else {
        throw new Exception('Invalid JSON input');
    }
    
    $action = $input['action'] ?? '';
    $deviceId = $input['device_id'] ?? '';
    $systemInfo = $input['system_info'] ?? '';
    
    // 如果system_info是数组，转换为JSON字符串
    if (is_array($systemInfo)) {
        $systemInfo = json_encode($systemInfo);
    }
    
    if (empty($deviceId)) {
        throw new Exception('Device ID is required');
    }
    
    // Rate limiting
    if (!$authManager->checkRateLimit($deviceId, 'auth', 5, 300)) {
        throw new Exception('Too many authentication attempts');
    }
    
    switch ($action) {
        case 'authenticate':
            if (empty($systemInfo)) {
                throw new Exception('System info is required');
            }
            
            // Register or update device
            $deviceManager->registerDevice($deviceId, $systemInfo, $clientIP);
            
            // Generate authentication token
            $token = $authManager->generateToken($deviceId);
            
            // Log authentication
            $authManager->logSecurityEvent($deviceId, 'authenticate', 'Successful authentication', $clientIP);
            
            $response = [
                'status' => 'success',
                'token' => $token,
                'message' => 'Authentication successful'
            ];
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    // 如果输入是加密的，输出也进行加密
    if ($isEncrypted) {
        $encryptedResponse = CryptoUtils::encrypt($response);
        $finalResponse = [
            'encrypted' => true,
            'data' => $encryptedResponse
        ];
    } else {
        $finalResponse = $response;
    }
    
} catch (Exception $e) {
    // Log detailed error information
    error_log("Auth API Error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Log failed authentication
    if (isset($deviceId)) {
        try {
            $authManager->logSecurityEvent($deviceId, 'auth_failed', $e->getMessage(), $clientIP);
        } catch (Exception $logError) {
            error_log("Failed to log security event: " . $logError->getMessage());
        }
    }

    $errorResponse = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    // 如果输入是加密的，错误响应也加密
    if (isset($isEncrypted) && $isEncrypted) {
        try {
            $encryptedResponse = CryptoUtils::encrypt($errorResponse);
            $finalResponse = [
                'encrypted' => true,
                'data' => $encryptedResponse
            ];
        } catch (Exception $cryptoError) {
            // 如果加密失败，返回未加密的错误
            $finalResponse = $errorResponse;
        }
    } else {
        $finalResponse = $errorResponse;
    }

    http_response_code(400);
}

echo json_encode($finalResponse);
