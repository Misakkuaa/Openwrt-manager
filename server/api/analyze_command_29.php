<?php
// 详细检查命令ID 29的情况
try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== DETAILED COMMAND ID 29 ANALYSIS ===\n\n";
    
    // 检查所有的命令ID 29
    echo "All commands with ID 29:\n";
    $stmt = $pdo->prepare("SELECT id, device_id, command, status, exit_code, output, created_at, sent_at, completed_at FROM device_commands WHERE id = 29");
    $stmt->execute();
    $commands = $stmt->fetchAll();
    
    foreach ($commands as $cmd) {
        echo "ID: {$cmd['id']}\n";
        echo "  Device: {$cmd['device_id']}\n";
        echo "  Status: {$cmd['status']}\n";
        echo "  Exit Code: " . ($cmd['exit_code'] ?? 'NULL') . "\n";
        echo "  Output: " . ($cmd['output'] ? 'YES (' . strlen($cmd['output']) . ' chars)' : 'NO') . "\n";
        echo "  Created: {$cmd['created_at']}\n";
        echo "  Sent: " . ($cmd['sent_at'] ?? 'NULL') . "\n";
        echo "  Completed: " . ($cmd['completed_at'] ?? 'NULL') . "\n";
        echo "  ---\n";
    }
    
    // 检查最新的日志
    echo "\nAll logs for command_result today:\n";
    $stmt = $pdo->prepare("SELECT device_id, message, timestamp FROM logs WHERE type = 'command_result' AND DATE(timestamp) = CURDATE() ORDER BY timestamp DESC");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    foreach ($logs as $log) {
        echo "- [{$log['timestamp']}] {$log['device_id']}: {$log['message']}\n";
    }
    
    // 检查是否有新的命令被创建
    echo "\nCommands created in the last hour:\n";
    $stmt = $pdo->prepare("SELECT id, device_id, status, created_at FROM device_commands WHERE device_id = 'dcd0b342' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY created_at DESC");
    $stmt->execute();
    $recentCommands = $stmt->fetchAll();
    
    foreach ($recentCommands as $cmd) {
        echo "- ID: {$cmd['id']}, Status: {$cmd['status']}, Created: {$cmd['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
