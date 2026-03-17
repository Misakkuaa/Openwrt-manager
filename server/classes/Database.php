<?php
/**
 * OpenWrt Remote Management Server
 * Database Connection Class
 */

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $pdo = null;
    
    public function __construct() {
        // 加载配置文件
        $this->loadConfig();
        $this->connect();
    }
    
    private function loadConfig() {
        $configFile = dirname(__DIR__) . '/config/config.php';
        if (file_exists($configFile)) {
            require_once $configFile;
            $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $this->username = defined('DB_USER') ? DB_USER : 'owrt_user';
            $this->password = defined('DB_PASS') ? DB_PASS : 'your_secure_password';
            $this->database = defined('DB_NAME') ? DB_NAME : 'owrt_management';
        } else {
            // 默认配置
            $this->host = 'localhost';
            $this->username = 'owrt_user';
            $this->password = 'your_secure_password';
            $this->database = 'owrt_management';
        }
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // 设置MySQL时区为北京时间
            $this->pdo->exec("SET time_zone = '+08:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function prepare($sql) {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            error_log("Database prepare failed: " . $e->getMessage());
            throw new Exception("Database prepare failed: " . $e->getMessage());
        }
    }
    
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $key) {
            $setParts[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * 清理过期的心跳失败日志
     * @param int $daysToKeep 保留天数，默认保留1天
     * @return int 删除的记录数
     */
    public function cleanupHeartbeatLogs($daysToKeep = 1) {
        try {
            $sql = "DELETE FROM security_log 
                    WHERE action = 'heartbeat_failed' 
                    AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->query($sql, ['days' => $daysToKeep]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to cleanup heartbeat logs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * 执行每日清理任务
     */
    public function runDailyCleanup() {
        try {
            // 清理心跳失败日志（保留1天）
            $deletedHeartbeat = $this->cleanupHeartbeatLogs(1);
            
            // 记录清理结果
            $this->insert('security_log', [
                'device_id' => 0,
                'action' => 'system_cleanup',
                'details' => "Daily cleanup completed. Deleted {$deletedHeartbeat} heartbeat_failed logs.",
                'ip_address' => 'system',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            error_log("Daily cleanup completed: {$deletedHeartbeat} heartbeat_failed logs deleted");
            return true;
        } catch (Exception $e) {
            error_log("Daily cleanup failed: " . $e->getMessage());
            return false;
        }
    }
}
