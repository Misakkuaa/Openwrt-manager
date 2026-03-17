<?php
// 为客户端创建一个新的测试命令
require_once '../config/config.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== 创建新的测试命令 ===\n";
    
    $deviceId = 'dcd0b342';
    $testCommand = 'whoami && pwd && date';
    
    // 创建新命令
    $stmt = $pdo->prepare("INSERT INTO device_commands (device_id, command, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$deviceId, $testCommand]);
    $newCommandId = $pdo->lastInsertId();
    
    echo "✅ 新命令创建成功！\n";
    echo "命令ID: $newCommandId\n";
    echo "设备ID: $deviceId\n";
    echo "命令: $testCommand\n";
    echo "状态: pending\n\n";
    
    echo "现在等待客户端处理这个命令...\n";
    echo "客户端应该在下次心跳时接收并执行这个命令\n\n";
    
    echo "监控命令:\n";
    echo "数据库: SELECT * FROM device_commands WHERE id = $newCommandId;\n";
    echo "客户端日志: logread -f | grep owrt_client\n";
    echo "服务器日志: tail -f /tmp/command_result_debug.log\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
