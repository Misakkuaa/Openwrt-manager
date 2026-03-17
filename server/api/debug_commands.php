<?php
require_once '../classes/Database.php';

header('Content-Type: application/json');

try {
    $db = new Database();
    
    // 查询最近的命令
    $commands = $db->query(
        "SELECT id, device_id, command, status, exit_code, output, created_at, sent_at, completed_at 
         FROM device_commands 
         ORDER BY created_at DESC 
         LIMIT 10"
    );
    
    echo json_encode([
        'status' => 'success',
        'commands' => $commands,
        'count' => count($commands)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
