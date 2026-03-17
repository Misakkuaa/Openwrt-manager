<?php
// 插入测试命令的脚本
require_once '../classes/Database.php';

try {
    $db = new Database();
    
    // 检查设备dcd0b342是否存在
    $device = $db->selectOne("SELECT * FROM devices WHERE device_id = 'dcd0b342'");
    
    if (!$device) {
        echo "Device dcd0b342 not found, creating it...\n";
        $db->insert('devices', [
            'device_id' => 'dcd0b342',
            'name' => 'Xiaomi Mi Router 4',
            'status' => 'online',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "Device created.\n";
    } else {
        echo "Device found: " . json_encode($device) . "\n";
    }
    
    // 插入一个新的测试命令
    $commandId = $db->insert('device_commands', [
        'device_id' => 'dcd0b342',
        'command' => 'uptime && free -h',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo "Test command inserted with ID: $commandId\n";
    
    // 查看当前所有命令
    $commands = $db->select("SELECT * FROM device_commands WHERE device_id = 'dcd0b342' ORDER BY created_at DESC LIMIT 5");
    echo "Current commands for device dcd0b342:\n";
    foreach ($commands as $cmd) {
        echo "ID: {$cmd['id']}, Status: {$cmd['status']}, Command: {$cmd['command']}, Created: {$cmd['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
