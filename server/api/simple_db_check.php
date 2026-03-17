<?php
header('Content-Type: application/json');

try {
    // 直接连接数据库，不使用Database类
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // 查询最近的命令
    $stmt = $pdo->prepare("SELECT id, device_id, command, status, exit_code, output, created_at, sent_at, completed_at FROM device_commands ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $commands = $stmt->fetchAll();
    
    // 特别查询ID为25和26的命令
    $stmt2 = $pdo->prepare("SELECT id, device_id, command, status, exit_code, output, created_at, sent_at, completed_at FROM device_commands WHERE id IN (25, 26)");
    $stmt2->execute();
    $specificCommands = $stmt2->fetchAll();
    
    echo json_encode([
        'status' => 'success',
        'recent_commands' => $commands,
        'commands_25_26' => $specificCommands,
        'count' => count($commands)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}
?>
