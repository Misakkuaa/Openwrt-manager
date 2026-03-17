<?php
/**
 * Device Detail API
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';
require_once '../classes/CommandManager.php';

$deviceManager = new DeviceManager();
$commandManager = new CommandManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        throw new Exception('Only GET method is allowed');
    }
    
    $deviceId = $_GET['device_id'] ?? '';
    
    if (empty($deviceId)) {
        throw new Exception('Device ID is required');
    }
    
    // Get device information with uptime details
    $device = $deviceManager->getDeviceDetail($deviceId);
    
    if (!$device) {
        throw new Exception('Device not found');
    }
    
    // Get command history
    $commandHistory = $commandManager->getCommandHistory($deviceId, 20);
    
    $response = [
        'status' => 'success',
        'device' => $device,
        'command_history' => $commandHistory
    ];
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
