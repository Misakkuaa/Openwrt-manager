<?php
/**
 * Device Conflicts API
 * 处理设备冲突的查询和管理
 */

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';

$deviceManager = new DeviceManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // 获取冲突列表
            $includeResolved = isset($_GET['include_resolved']) ? (bool)$_GET['include_resolved'] : false;
            
            try {
                $conflicts = $deviceManager->getAllConflicts($includeResolved);
            } catch (Exception $e) {
                // 如果新方法不存在，使用旧方法
                $conflicts = $deviceManager->checkDeviceConflicts();
            }
            
            $response = [
                'status' => 'success',
                'conflicts' => $conflicts,
                'count' => count($conflicts)
            ];
            break;
            
        case 'POST':
            // 解决冲突
            $input = json_decode(file_get_contents('php://input'), true);
            $conflictId = $input['conflict_id'] ?? 0;
            
            if (!$conflictId) {
                throw new Exception('Conflict ID is required');
            }
            
            if (method_exists($deviceManager, 'resolveConflict')) {
                $deviceManager->resolveConflict($conflictId);
                $response = [
                    'status' => 'success',
                    'message' => 'Conflict resolved successfully'
                ];
            } else {
                throw new Exception('Conflict resolution not implemented');
            }
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
?>
