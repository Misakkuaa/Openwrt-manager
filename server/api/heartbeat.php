<?php
/**
 * Device Heartbeat API with AES Encryption Support
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';
require_once '../classes/AuthManager.php';
require_once '../classes/CommandManager.php';
require_once '../utils/CryptoUtils.php';

$deviceManager = new DeviceManager();
$authManager = new AuthManager();
$commandManager = new CommandManager();

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
    
    $deviceId = $input['device_id'] ?? '';
    $token = $input['token'] ?? '';
    $timestamp = $input['timestamp'] ?? '';
    $systemInfo = $input['system_info'] ?? '';
    
    // 如果system_info是数组，转换为JSON字符串
    if (is_array($systemInfo)) {
        $systemInfo = json_encode($systemInfo);
    }
    
    if (empty($deviceId) || empty($token)) {
        throw new Exception('Device ID and token are required');
    }
    
    // Validate authentication token
    if (!$authManager->validateToken($deviceId, $token)) {
        throw new Exception('Invalid or expired token');
    }
    
    // Update device heartbeat and system info if provided
    $deviceManager->updateHeartbeat($deviceId, $clientIP, $systemInfo);
    
    // Update system info if provided (保持向后兼容)
    if (!empty($systemInfo)) {
        $deviceManager->updateSystemInfo($deviceId, $systemInfo);
    }
    
    // Check for pending commands
    $pendingCommand = $commandManager->getPendingCommand($deviceId);
    
    $response = [
        'status' => 'success',
        'timestamp' => time(),
        'message' => 'Heartbeat received'
    ];
    
    // Include command if available - 使用加密传输确保指令安全
    if ($pendingCommand) {
        $response['command'] = $pendingCommand['command'];
        $response['command_id'] = $pendingCommand['id'];
        // AES加密已经提供了安全保护，不需要额外的指令签名
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
    // Log failed heartbeat
    if (isset($deviceId)) {
        $authManager->logSecurityEvent($deviceId, 'heartbeat_failed', $e->getMessage(), $clientIP);
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
