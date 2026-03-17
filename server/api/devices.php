<?php
/**
 * 设备API端点 - 为设备列表页面提供数据
 */

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../classes/DeviceManager.php';

$deviceManager = new DeviceManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // 获取设备列表
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000; // 增加默认限制到1000
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $search = isset($_GET['search']) ? $_GET['search'] : null;
            
            if ($search) {
                $devices = $deviceManager->searchDevices($search);
            } elseif ($status) {
                $devices = $deviceManager->getDevicesByStatus($status);
            } else {
                // 使用新的带实时状态的排序方法，传递limit参数
                $devices = $deviceManager->getDevicesWithRealtimeStatus($limit, $offset);
            }
            
            // 计算详细统计信息
            $onlineCount = 0;
            $totalCount = count($devices);
            
            foreach ($devices as $device) {
                if (isset($device['is_online']) && $device['is_online'] == 1) {
                    $onlineCount++;
                }
            }
            
            $response = [
                'status' => 'success',
                'devices' => $devices,
                'stats' => [
                    'online_count' => $onlineCount,
                    'total_count' => $totalCount,
                    'timestamp' => time()
                ]
            ];
            break;
            
        case 'PUT':
            // 更新设备备注
            $input = json_decode(file_get_contents('php://input'), true);
            $deviceId = $input['device_id'] ?? '';
            $notes = $input['notes'] ?? '';
            
            if (empty($deviceId)) {
                throw new Exception('Device ID is required');
            }
            
            $deviceManager->updateDeviceNotes($deviceId, $notes);
            
            $response = [
                'status' => 'success',
                'message' => 'Device notes updated successfully'
            ];
            break;
            
        case 'DELETE':
            // 删除设备
            $input = json_decode(file_get_contents('php://input'), true);
            $deviceId = $input['device_id'] ?? '';
            
            if (empty($deviceId)) {
                throw new Exception('Device ID is required');
            }
            
            $deviceManager->deleteDevice($deviceId);
            
            $response = [
                'status' => 'success',
                'message' => 'Device deleted successfully'
            ];
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
