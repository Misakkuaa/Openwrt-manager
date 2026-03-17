<?php
// 查看PHP错误日志的脚本
header('Content-Type: text/plain');

echo "=== PHP Error Log ===\n";

// 常见的PHP错误日志位置
$logPaths = [
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    '/var/log/nginx/error.log',
    '/tmp/php_errors.log',
    ini_get('error_log')
];

foreach ($logPaths as $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        echo "\n--- Log file: $path ---\n";
        $lines = file($path);
        if ($lines) {
            // 显示最后50行
            $recentLines = array_slice($lines, -50);
            foreach ($recentLines as $line) {
                echo $line;
            }
        }
        break;
    }
}

echo "\n=== PHP Configuration ===\n";
echo "Error log: " . ini_get('error_log') . "\n";
echo "Log errors: " . (ini_get('log_errors') ? 'On' : 'Off') . "\n";
echo "Display errors: " . (ini_get('display_errors') ? 'On' : 'Off') . "\n";
?>
