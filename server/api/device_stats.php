<?php
/**
 * 设备统计API - 获取设备数量和基本统计信息
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../classes/DeviceManager.php';

try {
    $deviceManager = new DeviceManager();
    
    // 获取总设备数量（不限制）
    $totalDevices = $deviceManager->getTotalDeviceCount();
    
    // 获取在线设备数量
    $onlineDevices = $deviceManager->getOnlineDevicesCount();
    
    // 获取离线设备数量
    $offlineDevices = $totalDevices - $onlineDevices;
    
    // 获取最近24小时内活跃的设备数量
    $recentActiveDevices = $deviceManager->getRecentActiveDevicesCount(24);
    
    // 获取最近7天内首次连接的新设备数量
    $newDevicesThisWeek = $deviceManager->getNewDevicesCount(7);
    
    $response = [
        'status' => 'success',
        'statistics' => [
            'total_devices' => $totalDevices,
            'online_devices' => $onlineDevices,
            'offline_devices' => $offlineDevices,
            'recent_active_devices' => $recentActiveDevices,
            'new_devices_this_week' => $newDevicesThisWeek,
            'online_percentage' => $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100, 1) : 0
        ],
        'timestamp' => time(),
        'generated_at' => date('Y-m-d H:i:s')
    ];
    
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'status' => 'error',
        'message' => '获取设备统计失败: ' . $e->getMessage()
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
