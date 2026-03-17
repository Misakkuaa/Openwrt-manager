<?php
// 立即检查命令ID 37的处理状态
require_once '../config/config.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== 检查命令ID 37的实时状态 ===\n";
    echo "客户端执行时间: 17:35:15-16\n";
    echo "命令: free -h && cat /proc/meminfo | head -10\n";
    echo "客户端报告: 成功发送到服务器\n\n";
    
    // 检查命令ID 37
    $stmt = $pdo->prepare("SELECT * FROM device_commands WHERE id = 37 AND device_id = 'dcd0b342'");
    $stmt->execute();
    $command = $stmt->fetch();
    
    if ($command) {
        echo "✅ 命令ID 37 找到！\n";
        echo "状态: {$command['status']}\n";
        echo "退出码: " . ($command['exit_code'] ?? 'NULL') . "\n";
        echo "创建时间: {$command['created_at']}\n";
        echo "发送时间: " . ($command['sent_at'] ?? 'NULL') . "\n";
        echo "完成时间: " . ($command['completed_at'] ?? 'NULL') . "\n";
        echo "输出长度: " . strlen($command['output'] ?? '') . " 字符\n\n";
        
        if ($command['status'] !== 'completed') {
            echo "❌ 命令还没有完成，服务器没有处理客户端的结果！\n\n";
            
            // 检查Web服务器错误日志
            echo "=== 检查PHP错误日志 ===\n";
            $errorLog = '/var/log/php_errors.log';
            if (file_exists($errorLog)) {
                $errors = shell_exec("tail -20 $errorLog | grep -i 'command_result\\|fatal\\|error'");
                if ($errors) {
                    echo "PHP错误:\n$errors\n";
                } else {
                    echo "没有找到相关的PHP错误\n";
                }
            } else {
                echo "PHP错误日志文件不存在: $errorLog\n";
            }
            
            // 检查Apache错误日志
            echo "\n=== 检查Apache错误日志 ===\n";
            $apacheErrorLog = '/var/log/apache2/error.log';
            if (file_exists($apacheErrorLog)) {
                $apacheErrors = shell_exec("tail -20 $apacheErrorLog | grep -i 'command_result\\|17:35'");
                if ($apacheErrors) {
                    echo "Apache错误:\n$apacheErrors\n";
                } else {
                    echo "没有找到17:35时间段的Apache错误\n";
                }
            } else {
                echo "Apache错误日志文件不存在: $apacheErrorLog\n";
            }
            
            // 检查访问日志
            echo "\n=== 检查访问日志 ===\n";
            $accessLog = '/var/log/apache2/access.log';
            if (file_exists($accessLog)) {
                $accessEntries = shell_exec("tail -50 $accessLog | grep 'command_result.php'");
                if ($accessEntries) {
                    echo "command_result.php访问记录:\n$accessEntries\n";
                } else {
                    echo "没有找到command_result.php的访问记录\n";
                }
            } else {
                echo "访问日志文件不存在: $accessLog\n";
            }
            
            // 检查我们的调试日志
            echo "\n=== 检查调试日志 ===\n";
            $debugLog = '/tmp/command_result_debug.log';
            if (file_exists($debugLog)) {
                $debugEntries = shell_exec("tail -50 $debugLog");
                if ($debugEntries) {
                    echo "调试日志:\n$debugEntries\n";
                } else {
                    echo "调试日志为空\n";
                }
            } else {
                echo "调试日志文件不存在\n";
            }
            
        } else {
            echo "🎉 命令ID 37已完成！\n";
        }
    } else {
        echo "❌ 命令ID 37 未找到\n";
    }
    
    // 手动测试command_result.php
    echo "\n=== 手动测试command_result.php ===\n";
    echo "测试PHP文件语法...\n";
    $syntaxCheck = shell_exec('php -l ../api/command_result.php 2>&1');
    echo $syntaxCheck;
    
    // 测试是否可以加载
    echo "\n测试文件加载...\n";
    ob_start();
    try {
        // 设置POST环境
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        
        // 模拟一个简单的POST
        $testData = json_encode(['test' => true]);
        file_put_contents('php://memory', $testData);
        
        echo "尝试包含command_result.php...\n";
        // 注意：这只是测试语法，不会真正执行
        
    } catch (Exception $e) {
        echo "包含错误: " . $e->getMessage() . "\n";
    }
    $output = ob_get_clean();
    echo $output;
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
