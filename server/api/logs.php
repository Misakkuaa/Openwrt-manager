<?php
require_once '../config/config.php';
require_once '../classes/Database.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    $db = new Database();
    
    // 获取查询参数
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
    $action_filter = isset($_GET['action']) ? trim($_GET['action']) : '';
    
    // 限制最大查询数量
    $limit = min($limit, 500);
    
    // 构建查询条件
    $where_conditions = [];
    $params = [];
    
    if (!empty($device_id)) {
        $where_conditions[] = "device_id = ?";
        $params[] = $device_id;
    }
    
    if (!empty($action_filter)) {
        $where_conditions[] = "action = ?";
        $params[] = $action_filter;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // 查询日志数据
    $sql = "
        SELECT 
            id,
            device_id,
            action,
            details,
            ip_address,
            created_at
        FROM security_log 
        $where_clause
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 查询总数
    $count_sql = "SELECT COUNT(*) as total FROM security_log $where_clause";
    $count_params = array_slice($params, 0, -2); // 移除limit和offset参数
    
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 查询可用的操作类型
    $actions_stmt = $db->prepare("SELECT DISTINCT action FROM security_log ORDER BY action");
    $actions_stmt->execute();
    $available_actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 查询可用的设备ID
    $devices_stmt = $db->prepare("
        SELECT DISTINCT device_id 
        FROM security_log 
        WHERE device_id IS NOT NULL 
        ORDER BY device_id
    ");
    $devices_stmt->execute();
    $available_devices = $devices_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'status' => 'success',
        'logs' => $logs,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset,
        'available_actions' => $available_actions,
        'available_devices' => $available_devices
    ]);
    
} catch (Exception $e) {
    error_log("Logs API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load logs'
    ]);
}
?>
