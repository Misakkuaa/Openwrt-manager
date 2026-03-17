<?php
/**
 * 设备在线时长统计API
 * 提供在线时长排行榜和统计信息
 */

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
    
    $action = $_GET['action'] ?? 'leaderboard';
    
    switch ($action) {
        case 'leaderboard':
            // 获取在线时长排行榜
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $leaderboard = $deviceManager->getUptimeLeaderboard($limit);
            
            echo json_encode([
                'status' => 'success',
                'data' => $leaderboard,
                'total_devices' => count($leaderboard)
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'stats':
            // 获取在线时长统计信息
            $devices = $deviceManager->getDevicesWithRealtimeStatus(1000); // 获取所有设备
            
            $stats = [
                'total_devices' => count($devices),
                'online_devices' => 0,
                'total_uptime' => 0,
                'average_uptime' => 0,
                'max_uptime' => 0,
                'devices_with_uptime' => 0
            ];
            
            foreach ($devices as $device) {
                if ($device['realtime_status'] == 'online') {
                    $stats['online_devices']++;
                }
                
                if (isset($device['uptime_info']['total_duration'])) {
                    $uptime = $device['uptime_info']['total_duration'];
                    $stats['total_uptime'] += $uptime;
                    $stats['max_uptime'] = max($stats['max_uptime'], $uptime);
                    if ($uptime > 0) {
                        $stats['devices_with_uptime']++;
                    }
                }
            }
            
            if ($stats['devices_with_uptime'] > 0) {
                $stats['average_uptime'] = round($stats['total_uptime'] / $stats['devices_with_uptime']);
            }
            
            // 格式化时长
            $stats['total_uptime_formatted'] = $deviceManager->formatDuration($stats['total_uptime']);
            $stats['average_uptime_formatted'] = $deviceManager->formatDuration($stats['average_uptime']);
            $stats['max_uptime_formatted'] = $deviceManager->formatDuration($stats['max_uptime']);
            
            echo json_encode([
                'status' => 'success',
                'stats' => $stats
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'device':
            // 获取特定设备的在线时长详情
            $deviceId = $_GET['device_id'] ?? '';
            if (empty($deviceId)) {
                throw new Exception('设备ID不能为空');
            }
            
            $devices = $deviceManager->getDevicesWithRealtimeStatus(1000);
            $targetDevice = null;
            
            foreach ($devices as $device) {
                if ($device['device_id'] == $deviceId) {
                    $targetDevice = $device;
                    break;
                }
            }
            
            if (!$targetDevice) {
                throw new Exception('设备未找到');
            }
            
            echo json_encode([
                'status' => 'success',
                'device' => $targetDevice,
                'uptime_info' => $targetDevice['uptime_info'] ?? null
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('不支持的操作');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
