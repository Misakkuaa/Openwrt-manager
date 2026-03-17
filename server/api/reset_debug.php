<?php
// 清理并重置调试日志
$debugLogFile = '/tmp/command_result_debug.log';

// 清空调试日志
file_put_contents($debugLogFile, "=== Debug log reset at " . date('Y-m-d H:i:s') . " ===\n");

echo "Debug log has been reset.\n";
echo "Now waiting for next client command execution...\n";
echo "Check debug_log.php to see real-time updates.\n";
?>
