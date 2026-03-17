<?php
header('Content-Type: text/plain');
header('Refresh: 3'); // 3秒自动刷新

echo "=== Command Result Debug Log (Auto-refresh every 3 seconds) ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

$debugLogFile = '/tmp/command_result_debug.log';

if (file_exists($debugLogFile)) {
    echo "=== Debug Log Content ===\n";
    $content = file_get_contents($debugLogFile);
    echo $content;
} else {
    echo "No debug log file found yet.\n";
    echo "Waiting for command_result.php to be called...\n";
}

echo "\n=== API Call Log ===\n";
$apiLogFile = '/tmp/owrt_api_calls.log';

if (file_exists($apiLogFile)) {
    $lines = file($apiLogFile);
    if ($lines) {
        $recentLines = array_slice($lines, -10);
        foreach ($recentLines as $line) {
            echo $line;
        }
    }
} else {
    echo "No API call log found.\n";
}
?>
