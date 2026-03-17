<?php
// 最终验证系统状态
try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== FINAL SYSTEM STATUS VERIFICATION ===\n\n";
    
    // 检查最近的命令状态
    echo "Recent commands for device dcd0b342:\n";
    $stmt = $pdo->prepare("SELECT id, command, status, exit_code, output, created_at, completed_at FROM device_commands WHERE device_id = 'dcd0b342' ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $commands = $stmt->fetchAll();
    
    foreach ($commands as $cmd) {
        $outputPreview = $cmd['output'] ? substr($cmd['output'], 0, 50) . '...' : 'NULL';
        echo "ID: {$cmd['id']}\n";
        echo "  Command: {$cmd['command']}\n";
        echo "  Status: {$cmd['status']}\n";
        echo "  Exit Code: {$cmd['exit_code']}\n";
        echo "  Output: $outputPreview\n";
        echo "  Created: {$cmd['created_at']}\n";
        echo "  Completed: {$cmd['completed_at']}\n";
        echo "  ---\n";
    }
    
    // 统计命令状态
    echo "\nCommand Status Summary:\n";
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM device_commands WHERE device_id = 'dcd0b342' GROUP BY status");
    $stmt->execute();
    $stats = $stmt->fetchAll();
    
    foreach ($stats as $stat) {
        echo "- {$stat['status']}: {$stat['count']} commands\n";
    }
    
    // 检查最近的日志
    echo "\nRecent logs:\n";
    $stmt = $pdo->prepare("SELECT type, message, timestamp FROM logs WHERE device_id = 'dcd0b342' ORDER BY timestamp DESC LIMIT 5");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    foreach ($logs as $log) {
        echo "- [{$log['timestamp']}] {$log['type']}: {$log['message']}\n";
    }
    
    echo "\n=== ANALYSIS ===\n";
    $completedCount = 0;
    $sentCount = 0;
    
    foreach ($commands as $cmd) {
        if ($cmd['status'] === 'completed') $completedCount++;
        if ($cmd['status'] === 'sent') $sentCount++;
    }
    
    echo "Total commands checked: " . count($commands) . "\n";
    echo "Completed commands: $completedCount\n";
    echo "Sent (not completed) commands: $sentCount\n";
    
    if ($completedCount > 0) {
        echo "\n✅ SUCCESS: Command result processing is working!\n";
        echo "Commands are being executed and results are being stored in the database.\n";
    } else {
        echo "\n❌ ISSUE: No completed commands found.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
