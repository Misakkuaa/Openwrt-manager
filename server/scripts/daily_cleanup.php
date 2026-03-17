<?php
/**
 * 每日清理脚本
 * 用于清理过期的心跳失败日志
 * 
 * 使用方法：
 * 1. 手动执行: php daily_cleanup.php
 * 2. Cron 任务: 0 2 * * * /usr/bin/php /path/to/daily_cleanup.php
 */

// 设置脚本目录
$scriptDir = dirname(__FILE__);
$serverDir = dirname($scriptDir);

// 引入必要的类
require_once $serverDir . '/classes/Database.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting daily cleanup...\n";
    
    // 创建数据库连接
    $db = new Database();
    
    // 执行每日清理
    $result = $db->runDailyCleanup();
    
    if ($result) {
        echo "[" . date('Y-m-d H:i:s') . "] Daily cleanup completed successfully\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Daily cleanup failed\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script finished\n";
?>
