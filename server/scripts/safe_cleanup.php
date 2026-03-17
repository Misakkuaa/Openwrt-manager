<?php
/**
 * 临时禁用自动离线检查的清理脚本
 * 只执行其他清理任务，不标记设备离线
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入配置
require_once dirname(__DIR__) . '/config/config.php';

class SafeCleanupService {
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
     * 执行安全清理任务（不标记设备离线）
     */
    public function runSafeCleanup() {
        $this->log("开始执行安全清理任务（跳过设备离线检查）...");
        
        try {
            // 跳过离线设备标记
            $this->log("跳过设备离线检查");
            
            // 清理过期令牌
            $this->cleanExpiredTokens();
            
            // 清理旧日志
            $this->cleanOldLogs();
            
            // 清理旧指令记录
            $this->cleanOldCommands();
            
            // 优化数据库表
            $this->optimizeTables();
            
            $this->log("安全清理任务完成");
            
        } catch (Exception $e) {
            $this->log("清理任务出错: " . $e->getMessage());
        }
    }
    
    /**
     * 显示设备状态（不修改）
     */
    public function showDeviceStatus() {
        $this->log("=== 当前设备状态 ===");
        
        $sql = "SELECT device_id, status, last_seen, 
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago,
                NOW() as current_time
                FROM devices ORDER BY last_seen DESC";
        
        $stmt = $this->pdo->query($sql);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($devices as $device) {
            $this->log("设备 {$device['device_id']}: {$device['status']}, 最后心跳 {$device['last_seen']}, 距离现在 {$device['seconds_ago']} 秒");
        }
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
        $logFile = dirname(__DIR__) . '/logs/safe_cleanup.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// 如果直接运行此脚本
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    $cleanup = new SafeCleanupService();
    $cleanup->showDeviceStatus();
    $cleanup->runSafeCleanup();
}

// 如果通过Web访问
if (isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: application/json');
    
    $cleanup = new SafeCleanupService();
    
    if (isset($_GET['action']) && $_GET['action'] === 'run') {
        ob_start();
        $cleanup->showDeviceStatus();
        $cleanup->runSafeCleanup();
        $output = ob_get_clean();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Safe cleanup completed',
            'output' => $output
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Add ?action=run to execute safe cleanup'
        ]);
    }
}
