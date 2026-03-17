<?php
// 实时查看API调用情况
header('Content-Type: text/plain');
header('Refresh: 5'); // 5秒自动刷新

echo "=== API Call Monitor (Auto-refresh every 5 seconds) ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// 创建临时日志文件记录API调用
$logFile = '/tmp/owrt_api_calls.log';

// 如果日志文件存在，显示最近的记录
if (file_exists($logFile)) {
    echo "=== Recent API Calls ===\n";
    $lines = file($logFile);
    if ($lines) {
        // 显示最后20行
        $recentLines = array_slice($lines, -20);
        foreach ($recentLines as $line) {
            echo $line;
        }
    }
} else {
    echo "No API call log file found.\n";
}

echo "\n=== Current Process ===\n";
echo "Waiting for API calls...\n";
echo "Files being monitored:\n";
echo "- heartbeat.php\n";
echo "- command_result.php\n";
echo "- authenticate.php\n";
?>
