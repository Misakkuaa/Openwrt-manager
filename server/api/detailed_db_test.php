<?php
// 详细检查数据库连接和表状态
require_once '../config/config.php';
require_once '../classes/Database.php';

try {
    echo "=== DATABASE CONNECTION TEST ===\n\n";
    
    echo "Config constants:\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_USER: " . DB_USER . "\n\n";
    
    // 测试Database类
    echo "Testing Database class...\n";
    $db = new Database();
    echo "Database class initialized successfully\n";
    
    // 获取PDO对象来检查
    $pdo = $db->getPDO();
    echo "PDO object obtained\n";
    
    // 显示当前数据库
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "Current database: " . $result['current_db'] . "\n\n";
    
    // 显示所有表
    echo "Tables in current database:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // 特别检查logs表
    if (in_array('logs', $tables)) {
        echo "\n✅ logs table exists\n";
        
        // 检查表结构
        echo "logs table structure:\n";
        $stmt = $pdo->query("DESCRIBE logs");
        $columns = $stmt->fetchAll();
        foreach ($columns as $col) {
            echo "- {$col['Field']}: {$col['Type']}\n";
        }
    } else {
        echo "\n❌ logs table does NOT exist\n";
        echo "Creating logs table...\n";
        
        $createSQL = "
        CREATE TABLE logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            device_id VARCHAR(20) NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_id (device_id),
            INDEX idx_type (type),
            INDEX idx_timestamp (timestamp)
        )";
        
        $pdo->exec($createSQL);
        echo "✅ logs table created successfully\n";
    }
    
    // 测试Database类的insert方法
    echo "\nTesting Database class insert method...\n";
    try {
        $logId = $db->insert('logs', [
            'device_id' => 'test_device',
            'type' => 'test',
            'message' => 'Test log entry',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Insert successful, log ID: $logId\n";
        
        // 删除测试记录
        $db->delete('logs', 'id = :id', ['id' => $logId]);
        echo "✅ Test log entry cleaned up\n";
        
    } catch (Exception $e) {
        echo "❌ Insert failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
