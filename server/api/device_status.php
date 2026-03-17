<?php
/**
 * 实时设备状态API
 * 提供设备状态的实时视图，包含最新的在线/离线状态
 */

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';

$deviceManager = new DeviceManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        throw new Exception('Only GET method is allowed');
    }
    
    // 获取设备状态，包含实时的在线判断
    $devices = $deviceManager->getDevicesWithRealtimeStatus();
    
    $response = [
        'status' => 'success',
        'devices' => $devices,
        'online_count' => count(array_filter($devices, function($device) {
            return $device['status'] === 'online';
        })),
        'timestamp' => time()
    ];
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
