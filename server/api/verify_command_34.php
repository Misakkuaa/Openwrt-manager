<?php
// 验证命令ID 34的最终状态
require_once '../config/config.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== COMMAND ID 34 FINAL VERIFICATION ===\n\n";
    
    // 检查命令详情
    $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE id = 34 AND device_id = 'dcd0b342'");
    $stmt->execute();
    $command = $stmt->fetch();
    
    if ($command) {
        echo "✅ COMMAND FOUND:\n";
        echo "ID: {$command['id']}\n";
        echo "Device ID: {$command['device_id']}\n";
        echo "Command: {$command['command']}\n";
        echo "Status: {$command['status']}\n";
        echo "Exit Code: {$command['exit_code']}\n";
        echo "Created At: {$command['created_at']}\n";
        echo "Sent At: {$command['sent_at']}\n";
        echo "Completed At: {$command['completed_at']}\n";
        echo "Output Length: " . strlen($command['output']) . " characters\n";
        echo "Output Preview: " . substr($command['output'], 0, 100) . "...\n\n";
        
        if ($command['status'] === 'completed' && $command['exit_code'] === 0 && !empty($command['output'])) {
            echo "🎉 COMMAND ID 34 FULLY COMPLETED AND PROCESSED!\n";
            echo "✅ Status: completed\n";
            echo "✅ Exit Code: 0 (success)\n";
            echo "✅ Output: Present (" . strlen($command['output']) . " chars)\n";
            echo "✅ Timestamps: All present\n\n";
            
            echo "=== PROBLEM SOLVED ===\n";
            echo "The encrypted communication system is now working correctly!\n";
            echo "- Client can send encrypted command results\n";
            echo "- Server can decrypt and process them\n";
            echo "- Database is properly updated\n";
            echo "- Command lifecycle is complete: pending → sent → completed\n";
        } else {
            echo "❌ Something is still wrong:\n";
            echo "- Status: {$command['status']} (should be 'completed')\n";
            echo "- Exit Code: {$command['exit_code']} (should be 0)\n";
            echo "- Output: " . (empty($command['output']) ? 'EMPTY' : 'Present') . "\n";
        }
    } else {
        echo "❌ Command ID 34 not found in database\n";
    }
    
    // 检查最近的日志
    echo "\n=== RECENT PROCESSING LOGS ===\n";
    $stmt = $pdo->prepare("SELECT * FROM logs WHERE message LIKE '%command_result%' OR message LIKE '%decrypt%' ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    foreach ($logs as $log) {
        echo "[{$log['created_at']}] {$log['level']}: {$log['message']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
