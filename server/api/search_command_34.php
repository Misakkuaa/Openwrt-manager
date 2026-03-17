<?php
// 专门检查命令ID 34
try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== COMMAND ID 34 SEARCH ===\n\n";
    
    // 搜索命令ID 34
    echo "Searching for command ID 34:\n";
    $stmt = $pdo->prepare("SELECT id, device_id, command, status, exit_code, output, created_at, sent_at, completed_at FROM device_commands WHERE id = 34");
    $stmt->execute();
    $command34 = $stmt->fetch();
    
    if ($command34) {
        echo "✅ Command ID 34 found:\n";
        echo "  Device: {$command34['device_id']}\n";
        echo "  Status: {$command34['status']}\n";
        echo "  Command: {$command34['command']}\n";
        echo "  Exit Code: " . ($command34['exit_code'] ?? 'NULL') . "\n";
        echo "  Output: " . ($command34['output'] ? 'YES (' . strlen($command34['output']) . ' chars)' : 'NO') . "\n";
        echo "  Created: {$command34['created_at']}\n";
        echo "  Sent: " . ($command34['sent_at'] ?? 'NULL') . "\n";
        echo "  Completed: " . ($command34['completed_at'] ?? 'NULL') . "\n";
    } else {
        echo "❌ Command ID 34 NOT FOUND in database\n";
        echo "This suggests the heartbeat API is not creating command records properly.\n";
    }
    
    // 查看最高的命令ID
    echo "\nHighest command ID in database:\n";
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM device_commands");
    $result = $stmt->fetch();
    echo "Max ID: " . $result['max_id'] . "\n";
    
    // 查看最近创建的命令
    echo "\nCommands created in last 2 hours:\n";
    $stmt = $pdo->prepare("SELECT id, device_id, status, created_at FROM device_commands WHERE device_id = 'dcd0b342' AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY created_at DESC");
    $stmt->execute();
    $recentCommands = $stmt->fetchAll();
    
    foreach ($recentCommands as $cmd) {
        echo "- ID: {$cmd['id']}, Status: {$cmd['status']}, Created: {$cmd['created_at']}\n";
    }
    
    // 检查心跳API的日志
    echo "\nHeartbeat related logs today:\n";
    $stmt = $pdo->prepare("SELECT type, message, timestamp FROM logs WHERE device_id = 'dcd0b342' AND (type LIKE '%heartbeat%' OR message LIKE '%heartbeat%') AND DATE(timestamp) = CURDATE() ORDER BY timestamp DESC LIMIT 5");
    $stmt->execute();
    $heartbeatLogs = $stmt->fetchAll();
    
    if (empty($heartbeatLogs)) {
        echo "❌ No heartbeat logs found\n";
    } else {
        foreach ($heartbeatLogs as $log) {
            echo "- [{$log['timestamp']}] {$log['type']}: {$log['message']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
