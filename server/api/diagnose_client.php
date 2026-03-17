<?php
// 分析客户端问题的诊断脚本
require_once '../config/config.php';

echo "=== CLIENT BEHAVIOR ANALYSIS ===\n\n";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    // 1. 检查最近的命令状态
    echo "1. RECENT COMMANDS STATUS:\n";
    $stmt = $pdo->prepare("SELECT id, device_id, command, status, exit_code, created_at, sent_at, completed_at FROM device_commands WHERE device_id = 'dcd0b342' ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $commands = $stmt->fetchAll();
    
    foreach ($commands as $cmd) {
        echo "Command {$cmd['id']}: {$cmd['status']} - '{$cmd['command']}'\n";
        echo "  Created: {$cmd['created_at']}\n";
        echo "  Sent: {$cmd['sent_at']}\n";
        echo "  Completed: {$cmd['completed_at']}\n";
        echo "  Exit Code: {$cmd['exit_code']}\n\n";
    }
    
    // 2. 检查设备最后活动时间
    echo "2. DEVICE LAST ACTIVITY:\n";
    $stmt = $pdo->prepare("SELECT device_id, last_seen, status FROM devices WHERE device_id = 'dcd0b342'");
    $stmt->execute();
    $device = $stmt->fetch();
    
    if ($device) {
        echo "Device: {$device['device_id']}\n";
        echo "Last Seen: {$device['last_seen']}\n";
        echo "Status: {$device['status']}\n";
        
        $lastSeen = new DateTime($device['last_seen']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $lastSeen->getTimestamp();
        echo "Seconds since last seen: $diff\n\n";
        
        if ($diff > 300) {
            echo "❌ CLIENT SEEMS OFFLINE (last seen > 5 minutes ago)\n\n";
        } else {
            echo "✅ Client recently active\n\n";
        }
    } else {
        echo "❌ Device not found in devices table\n\n";
    }
    
    // 3. 检查服务器日志
    echo "3. RECENT SERVER LOGS:\n";
    $stmt = $pdo->prepare("SELECT created_at, level, message FROM logs WHERE message LIKE '%dcd0b342%' OR message LIKE '%command_result%' ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    if (empty($logs)) {
        echo "No recent logs found for device dcd0b342\n\n";
    } else {
        foreach ($logs as $log) {
            echo "[{$log['created_at']}] {$log['level']}: {$log['message']}\n";
        }
        echo "\n";
    }
    
    // 4. 分析客户端可能的问题
    echo "4. POTENTIAL CLIENT ISSUES:\n";
    
    // 检查是否有pending命令长时间未被picked up
    $stmt = $pdo->prepare("SELECT id, command, created_at FROM device_commands WHERE device_id = 'dcd0b342' AND status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    $stuckCommands = $stmt->fetchAll();
    
    if (!empty($stuckCommands)) {
        echo "❌ STUCK PENDING COMMANDS (not picked up by client):\n";
        foreach ($stuckCommands as $cmd) {
            echo "  Command {$cmd['id']}: '{$cmd['command']}' (pending since {$cmd['created_at']})\n";
        }
        echo "  -> Client might not be polling for commands\n\n";
    }
    
    // 检查是否有sent命令长时间未完成
    $stmt = $pdo->prepare("SELECT id, command, sent_at FROM device_commands WHERE device_id = 'dcd0b342' AND status = 'sent' AND sent_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute();
    $stuckSent = $stmt->fetchAll();
    
    if (!empty($stuckSent)) {
        echo "❌ STUCK SENT COMMANDS (client picked up but never returned result):\n";
        foreach ($stuckSent as $cmd) {
            echo "  Command {$cmd['id']}: '{$cmd['command']}' (sent since {$cmd['sent_at']})\n";
        }
        echo "  -> Client executes commands but fails to send results back\n\n";
    }
    
    // 5. 创建测试命令来验证客户端响应
    echo "5. CREATING NEW TEST COMMAND:\n";
    $testCommand = 'echo "Test command at ' . date('Y-m-d H:i:s') . '"';
    $stmt = $pdo->prepare("INSERT INTO device_commands (device_id, command, status) VALUES ('dcd0b342', ?, 'pending')");
    $stmt->execute([$testCommand]);
    $testCommandId = $pdo->lastInsertId();
    
    echo "Created test command ID: $testCommandId\n";
    echo "Command: $testCommand\n";
    echo "Monitor this command to see if client picks it up and executes it\n\n";
    
    // 6. 客户端诊断建议
    echo "6. CLIENT DIAGNOSTICS CHECKLIST:\n";
    echo "□ Check if client process is running\n";
    echo "□ Check client logs for errors\n";
    echo "□ Verify client configuration (server URL, device ID, encryption settings)\n";
    echo "□ Test client network connectivity to server\n";
    echo "□ Check client's heartbeat/polling mechanism\n";
    echo "□ Verify client's encryption key matches server\n";
    echo "□ Monitor command ID $testCommandId for client response\n\n";
    
    echo "7. EXPECTED CLIENT BEHAVIOR:\n";
    echo "1. Client polls server every ~30 seconds for new commands\n";
    echo "2. When command found, status changes: pending -> sent\n";
    echo "3. Client executes command\n";
    echo "4. Client sends encrypted result back to server\n";
    echo "5. Server processes result, status changes: sent -> completed\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
