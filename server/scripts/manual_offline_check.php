<?php
/**
 * 手动设备离线检查脚本
 * 只有手动运行时才检查设备离线状态
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入配置
require_once dirname(__DIR__) . '/config/config.php';

class ManualOfflineChecker {
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
     * 检查设备离线状态
     */
    public function checkOfflineDevices($timeout = 120) {
        $this->log("开始手动检查设备离线状态 (超时: {$timeout}秒)...");
        
        // 先显示当前设备状态
        $this->showCurrentStatus();
        
        // 查询会被标记为离线的设备
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
            $this->log("发现超时设备 (>{$timeout}秒没有心跳):");
            foreach ($devicesToOffline as $device) {
                $this->log("  设备 {$device['device_id']}: 最后心跳 {$device['last_seen']}, 距离现在 {$device['seconds_ago']} 秒");
            }
            
            // 询问是否执行离线标记
            if (php_sapi_name() === 'cli') {
                echo "是否标记这些设备为离线? (y/n): ";
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                fclose($handle);
                
                if (trim($line) !== 'y') {
                    $this->log("用户取消操作");
                    return;
                }
            }
            
            // 执行离线标记
            $sql = "UPDATE devices SET status = 'offline' 
                    WHERE status = 'online' 
                    AND last_seen < DATE_SUB(NOW(), INTERVAL ? SECOND)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$timeout]);
            
            $affected = $stmt->rowCount();
            $this->log("已标记 {$affected} 个设备为离线状态");
        } else {
            $this->log("没有超时设备需要标记为离线");
        }
        
        // 显示更新后的状态
        $this->log("\n=== 更新后的设备状态 ===");
        $this->showCurrentStatus();
    }
    
    /**
     * 显示当前设备状态
     */
    private function showCurrentStatus() {
        $sql = "SELECT device_id, status, last_seen, 
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago
                FROM devices ORDER BY last_seen DESC";
        
        $stmt = $this->pdo->query($sql);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->log("当前设备状态:");
        foreach ($devices as $device) {
            $this->log("  {$device['device_id']}: {$device['status']}, 最后心跳: {$device['last_seen']} ({$device['seconds_ago']}秒前)");
        }
    }
    
    /**
     * 强制设置设备为在线状态
     */
    public function forceOnline($deviceId) {
        $sql = "UPDATE devices SET status = 'online', last_seen = NOW() WHERE device_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$deviceId]);
        
        $this->log("强制设置设备 {$deviceId} 为在线状态");
    }
    
    /**
     * 记录日志
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        echo $logMessage;
    }
}

// 命令行使用
if (php_sapi_name() === 'cli') {
    $checker = new ManualOfflineChecker();
    
    if ($argc > 1) {
        switch ($argv[1]) {
            case 'check':
                $timeout = isset($argv[2]) ? intval($argv[2]) : 60;
                $checker->checkOfflineDevices($timeout);
                break;
            case 'status':
                $checker->showCurrentStatus();
                break;
            case 'force-online':
                if (isset($argv[2])) {
                    $checker->forceOnline($argv[2]);
                } else {
                    echo "用法: php manual_offline_check.php force-online <device_id>\n";
                }
                break;
            default:
                echo "用法:\n";
                echo "  php manual_offline_check.php check [timeout_seconds]  - 检查离线设备\n";
                echo "  php manual_offline_check.php status                   - 显示设备状态\n";
                echo "  php manual_offline_check.php force-online <device_id> - 强制设备在线\n";
        }
    } else {
        echo "用法:\n";
        echo "  php manual_offline_check.php check [timeout_seconds]  - 检查离线设备\n";
        echo "  php manual_offline_check.php status                   - 显示设备状态\n";
        echo "  php manual_offline_check.php force-online <device_id> - 强制设备在线\n";
    }
}

// Web访问
if (isset($_SERVER['HTTP_HOST'])) {
    header('Content-Type: application/json');
    
    $checker = new ManualOfflineChecker();
    $action = $_GET['action'] ?? 'status';
    
    ob_start();
    
    switch ($action) {
        case 'check':
            $timeout = isset($_GET['timeout']) ? intval($_GET['timeout']) : 120;
            $checker->checkOfflineDevices($timeout);
            break;
        case 'force-online':
            if (isset($_GET['device_id'])) {
                $checker->forceOnline($_GET['device_id']);
            }
            break;
        default:
            $checker->showCurrentStatus();
    }
    
    $output = ob_get_clean();
    
    echo json_encode([
        'status' => 'success',
        'output' => $output
    ]);
}
