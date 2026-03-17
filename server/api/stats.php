<?php
/**
 * 系统统计信息 API
 * 提供数据库统计信息
 */

require_once '../classes/Database.php';

// 设置 CORS 头
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 只允许 GET 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }
    
    $db = new Database();
    
    // 获取设备数量
    $deviceCount = $db->selectOne("SELECT COUNT(*) as count FROM devices");
    
    // 获取在线设备数量
    $onlineDevices = $db->selectOne("SELECT COUNT(*) as count FROM devices WHERE status = 'online'");
    
    // 获取总日志数量
    $totalLogs = $db->selectOne("SELECT COUNT(*) as count FROM security_log");
    
    // 获取心跳失败日志数量
    $heartbeatLogs = $db->selectOne("SELECT COUNT(*) as count FROM security_log WHERE action = 'heartbeat_failed'");
    
    // 获取今日心跳次数
    $todayHeartbeats = $db->selectOne("SELECT COUNT(*) as count FROM security_log WHERE action = 'heartbeat' AND DATE(created_at) = CURDATE()");
    
    // 获取今日执行指令数量
    $todayCommands = $db->selectOne("SELECT COUNT(*) as count FROM security_log WHERE action = 'command_executed' AND DATE(created_at) = CURDATE()");
    
    // 获取最近的清理记录
    $lastCleanup = $db->selectOne("SELECT created_at FROM security_log WHERE action = 'system_cleanup' ORDER BY created_at DESC LIMIT 1");
    
    // 计算平均响应时间（模拟数据，可以根据实际需求调整）
    $avgResponseTime = rand(50, 200); // 毫秒
    
    $result = [
        'device_count' => (int)$deviceCount['count'],
        'online_devices' => (int)$onlineDevices['count'],
        'total_logs' => (int)$totalLogs['count'],
        'heartbeat_logs' => (int)$heartbeatLogs['count'],
        'today_heartbeats' => (int)$todayHeartbeats['count'],
        'today_commands' => (int)$todayCommands['count'],
        'active_connections' => (int)$onlineDevices['count'], // 使用在线设备数作为活跃连接数
        'avg_response_time' => $avgResponseTime,
        'last_cleanup' => $lastCleanup ? $lastCleanup['created_at'] : null
    ];
    
    echo json_encode(['status' => 'success'] + $result);
    
} catch (Exception $e) {
    error_log("Stats API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>
