<?php
/**
 * Device Filters API - 设备筛选和统计功能
 * 提供设备筛选、统计信息和数据聚合功能
 */

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';

$deviceManager = new DeviceManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        throw new Exception('Only GET method is allowed');
    }
    
    // 获取筛选参数
    $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
    $model = isset($_GET['model']) ? $_GET['model'] : null;
    $version = isset($_GET['version']) ? $_GET['version'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $action = isset($_GET['action']) ? $_GET['action'] : 'filter';
    
    switch ($action) {
        case 'stats':
            // 获取统计信息
            $stats = $deviceManager->getDeviceStats();
            $response = [
                'status' => 'success',
                'stats' => $stats
            ];
            break;
            
        case 'filter':
            // 筛选设备
            $filters = [
                'device_id' => $deviceId,
                'model' => $model,
                'version' => $version,
                'status' => $status
            ];
            
            $devices = $deviceManager->getFilteredDevices($filters);
            $count = $deviceManager->getFilteredDevicesCount($filters);
            
            $response = [
                'status' => 'success',
                'devices' => $devices,
                'count' => $count,
                'filters_applied' => array_filter($filters)
            ];
            break;
            
        case 'values':
            // 获取所有可能的筛选值及其数量
            $field = isset($_GET['field']) ? $_GET['field'] : null;
            if (!$field) {
                throw new Exception('Field parameter is required for values action');
            }
            
            $values = $deviceManager->getFieldValues($field);
            $response = [
                'status' => 'success',
                'field' => $field,
                'values' => $values
            ];
            break;
            
        default:
            throw new Exception('Invalid action parameter');
    }
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
?>
