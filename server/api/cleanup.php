<?php
/**
 * 系统清理 API
 * 提供手动触发清理功能
 */

require_once '../classes/Database.php';

// 设置 CORS 头
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // 只允许 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        exit;
    }
    
    // 验证请求来源（可选：添加管理员权限验证）
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 简单的密钥验证（建议在生产环境中使用更安全的验证方式）
    $adminKey = $input['admin_key'] ?? '';
    if ($adminKey !== 'your_admin_secret_key') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $db = new Database();
    
    // 获取清理参数
    $daysToKeep = $input['days_to_keep'] ?? 1;
    $forceCleanup = $input['force_cleanup'] ?? false;
    
    $result = [];
    
    if ($forceCleanup) {
        // 强制清理所有心跳失败日志
        $deletedCount = $db->cleanupHeartbeatLogs(0);
        $result['heartbeat_logs_deleted'] = $deletedCount;
        $result['message'] = "Force cleanup completed. Deleted {$deletedCount} heartbeat_failed logs.";
    } else {
        // 清理指定天数前的心跳失败日志
        $deletedCount = $db->cleanupHeartbeatLogs($daysToKeep);
        $result['heartbeat_logs_deleted'] = $deletedCount;
        $result['message'] = "Cleanup completed. Deleted {$deletedCount} heartbeat_failed logs older than {$daysToKeep} days.";
    }
    
    echo json_encode(['status' => 'success'] + $result);
    
} catch (Exception $e) {
    error_log("Cleanup API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error: ' . $e->getMessage()]);
}
?>
