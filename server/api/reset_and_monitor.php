<?php
// 清理并重新监控command_result.php调用
$debugLogFile = '/tmp/command_result_debug.log';
$apiLogFile = '/tmp/owrt_api_calls.log';

// 清空日志文件
file_put_contents($debugLogFile, "=== Debug log reset at " . date('Y-m-d H:i:s') . " ===\n");
file_put_contents($apiLogFile, "=== API log reset at " . date('Y-m-d H:i:s') . " ===\n");

echo "Logs reset. Monitoring for command_result.php calls...\n";
echo "Client should send command ID 34 result soon.\n";
echo "Check debug_log.php for real-time updates.\n";

// 记录当前时间以便追踪
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "Client log time: 16:49:14 (Sat Aug 23)\n";
echo "Expecting command result for ID 34...\n";
?>
