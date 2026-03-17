<?php
/**
 * 数据库和系统清理脚本
 * 用于宝塔面板定时任务
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入配置
require_once dirname(__DIR__) . '/config/config.php';

class CleanupService {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            $this->log("Database connection failed: " . $e->getMessage());
            exit(1);
        }
    }
    
    /**
     * 执行所有清理任务
     */
    public function runCleanup() {
        $this->log("开始执行清理任务...");
        
        try {
            // 清理离线设备
            $this->markOfflineDevices();
            
            // 清理过期令牌
            $this->cleanExpiredTokens();
            
            // 清理旧日志
            $this->cleanOldLogs();
            
            // 清理旧指令记录
            $this->cleanOldCommands();
            
            // 优化数据库表
            $this->optimizeTables();
            
            $this->log("清理任务完成");
            
        } catch (Exception $e) {
            $this->log("清理任务出错: " . $e->getMessage());
        }
    }
    
    /**
     * 标记离线设备
     * 临时禁用自动离线检查，避免设备被过早标记为离线
     */
    private function markOfflineDevices() {
        $this->log("自动离线检查已禁用 - 避免设备被过早标记为离线");
        return;
        
        // 以下代码已禁用
        /*
        $timeout = 30; // 增加到30秒超时，给网络延迟留出缓冲
        
        // 先查询会被标记为离线的设备，进行调试
        $debugSql = "SELECT device_id, last_seen, 
                     TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago,
                     NOW() as current_time
                     FROM devices 
                     WHERE status = 'online' 
                     AND last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $debugStmt = $this->pdo->prepare($debugSql);
        $debugStmt->execute([$timeout]);
        $devicesToOffline = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($devicesToOffline)) {
            $this->log("准备标记离线的设备 (超时>{$timeout}秒):");
            foreach ($devicesToOffline as $device) {
                $this->log("  设备 {$device['device_id']}: 最后心跳 {$device['last_seen']}, 距离现在 {$device['seconds_ago']} 秒, 当前时间 {$device['current_time']}");
            }
        }
        
        // 执行离线标记
        $sql = "UPDATE devices SET status = 'offline' 
                WHERE status = 'online' 
                AND last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$timeout]);
        
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $this->log("标记 {$affected} 个设备为离线状态");
        } else {
            $this->log("没有设备需要标记为离线");
        }
        */
    }
    
    /**
     * 清理过期令牌
     */
    private function cleanExpiredTokens() {
        $sql = "DELETE FROM device_tokens WHERE expires_at < NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $this->log("清理 {$affected} 个过期令牌");
        }
    }
    
    /**
     * 清理旧日志
     */
    private function cleanOldLogs() {
        $days = 30; // 保留30天
        $sql = "DELETE FROM security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $this->log("清理 {$affected} 条安全日志记录");
        }
    }
    
    /**
     * 清理旧指令记录
     */
    private function cleanOldCommands() {
        $days = 90; // 保留90天
        $sql = "DELETE FROM device_commands 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status IN ('completed', 'failed', 'cancelled')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        
        $affected = $stmt->rowCount();
        if ($affected > 0) {
            $this->log("清理 {$affected} 条指令历史记录");
        }
    }
    
    /**
     * 优化数据库表
     */
    private function optimizeTables() {
        $tables = ['devices', 'device_commands', 'device_tokens', 'security_log'];
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("OPTIMIZE TABLE {$table}");
                $this->log("优化表 {$table}");
            } catch (PDOException $e) {
                $this->log("优化表 {$table} 失败: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 记录日志
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // 输出到控制台
        echo $logMessage;
        
        // 写入日志文件
        $logFile = dirname(__DIR__) . '/logs/cleanup.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 获取统计信息
     */
    public function getStats() {
        $stats = [];
        
        // 设备统计
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM devices GROUP BY status");
        $deviceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['devices'] = $deviceStats;
        
        // 指令统计
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM device_commands WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) GROUP BY status");
        $commandStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['commands_24h'] = $commandStats;
        
        // 令牌统计
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM device_tokens WHERE expires_at > NOW()");
        $tokenCount = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['active_tokens'] = $tokenCount['count'];
        
        return $stats;
    }
}

// 如果直接运行此脚本
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $cleanup = new CleanupService();
    $cleanup->runCleanup();
    
    // 显示统计信息
    echo "\n=== 系统统计 ===\n";
    $stats = $cleanup->getStats();
    echo "设备状态统计:\n";
    foreach ($stats['devices'] as $stat) {
        echo "  {$stat['status']}: {$stat['count']}\n";
    }
    
    echo "24小时指令统计:\n";
    foreach ($stats['commands_24h'] as $stat) {
        echo "  {$stat['status']}: {$stat['count']}\n";
    }
    
    echo "活跃令牌数: {$stats['active_tokens']}\n";
}

// 如果通过Web访问
if (isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: application/json');
    
    $cleanup = new CleanupService();
    
    if (isset($_GET['action']) && $_GET['action'] === 'run') {
        ob_start();
        $cleanup->runCleanup();
        $output = ob_get_clean();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Cleanup completed',
            'output' => $output,
            'stats' => $cleanup->getStats()
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'stats' => $cleanup->getStats(),
            'message' => 'Add ?action=run to execute cleanup'
        ]);
    }
}
