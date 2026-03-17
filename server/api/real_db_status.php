<?php
// 检查真实的数据库状态
try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== REAL DATABASE STATUS ===\n\n";
    
    // 检查最近的命令，特别是25-28
    echo "Commands 25-28 for device dcd0b342:\n";
    $stmt = $pdo->prepare("SELECT id, command, status, exit_code, output, created_at, sent_at, completed_at FROM device_commands WHERE device_id = 'dcd0b342' AND id BETWEEN 25 AND 28 ORDER BY id");
    $stmt->execute();
    $commands = $stmt->fetchAll();
    
    foreach ($commands as $cmd) {
        echo "ID: {$cmd['id']}\n";
        echo "  Status: {$cmd['status']}\n";
        echo "  Exit Code: " . ($cmd['exit_code'] ?? 'NULL') . "\n";
        echo "  Output: " . ($cmd['output'] ? 'YES (' . strlen($cmd['output']) . ' chars)' : 'NULL') . "\n";
        echo "  Created: {$cmd['created_at']}\n";
        echo "  Sent: " . ($cmd['sent_at'] ?? 'NULL') . "\n";
        echo "  Completed: " . ($cmd['completed_at'] ?? 'NULL') . "\n";
        echo "  ---\n";
    }
    
    // 检查是否有任何completed状态的命令
    echo "\nAll completed commands for device dcd0b342:\n";
    $stmt = $pdo->prepare("SELECT id, status, exit_code, completed_at FROM device_commands WHERE device_id = 'dcd0b342' AND status = 'completed' ORDER BY id DESC");
    $stmt->execute();
    $completedCommands = $stmt->fetchAll();
    
    if (empty($completedCommands)) {
        echo "❌ NO COMPLETED COMMANDS FOUND!\n";
    } else {
        foreach ($completedCommands as $cmd) {
            echo "- ID: {$cmd['id']}, Exit: {$cmd['exit_code']}, Completed: {$cmd['completed_at']}\n";
        }
    }
    
    // 检查最新的日志
    echo "\nRecent API calls in logs:\n";
    $stmt = $pdo->prepare("SELECT message, timestamp FROM logs WHERE device_id = 'dcd0b342' AND type = 'command_result' ORDER BY timestamp DESC LIMIT 5");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "❌ NO COMMAND_RESULT LOGS FOUND!\n";
        echo "This suggests command_result.php is not successfully processing requests.\n";
    } else {
        foreach ($logs as $log) {
            echo "- [{$log['timestamp']}] {$log['message']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
