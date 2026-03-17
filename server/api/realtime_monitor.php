<?php
// 实时监控脚本
header('Content-Type: text/plain');
header('Cache-Control: no-cache');

echo "=== REAL-TIME MONITORING ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 检查调试日志
$debugLogFile = '/tmp/command_result_debug.log';
$apiLogFile = '/tmp/owrt_api_calls.log';

echo "=== Recent API Calls ===\n";
if (file_exists($apiLogFile)) {
    $apiContent = file_get_contents($apiLogFile);
    $lines = explode("\n", $apiContent);
    $recentLines = array_slice($lines, -10);
    foreach ($recentLines as $line) {
        if (trim($line)) echo $line . "\n";
    }
} else {
    echo "No API log file found.\n";
}

echo "\n=== Recent Debug Output ===\n";
if (file_exists($debugLogFile)) {
    $debugContent = file_get_contents($debugLogFile);
    $lines = explode("\n", $debugContent);
    $recentLines = array_slice($lines, -20);
    foreach ($recentLines as $line) {
        if (trim($line)) echo $line . "\n";
    }
} else {
    echo "No debug log file found.\n";
}

// 检查最新的命令状态
echo "\n=== Command ID 34 Status ===\n";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $stmt = $pdo->prepare("SELECT status, exit_code, output, completed_at FROM device_commands WHERE id = 34 AND device_id = 'dcd0b342'");
    $stmt->execute();
    $cmd = $stmt->fetch();
    
    if ($cmd) {
        echo "Status: {$cmd['status']}\n";
        echo "Exit Code: " . ($cmd['exit_code'] ?? 'NULL') . "\n";
        echo "Output: " . ($cmd['output'] ? 'YES (' . strlen($cmd['output']) . ' chars)' : 'NO') . "\n";
        echo "Completed: " . ($cmd['completed_at'] ?? 'NULL') . "\n";
        
        if ($cmd['status'] === 'completed') {
            echo "🎉 SUCCESS! Command 34 is completed!\n";
        } else {
            echo "⏳ Still waiting for command 34 result...\n";
        }
    } else {
        echo "❌ Command 34 not found\n";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Instructions ===\n";
echo "Refresh this page to see updates.\n";
echo "Watch for the next client heartbeat and command execution.\n";
echo "Client sends heartbeats approximately every 5 minutes.\n";
?>
