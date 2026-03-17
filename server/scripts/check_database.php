<?php
/**
 * 检查数据库触发器和事件
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入配置
require_once dirname(__DIR__) . '/config/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "=== 数据库诊断 ===\n";
    echo "检查时间: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 检查触发器
    echo "--- 触发器检查 ---\n";
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'devices'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($triggers)) {
        echo "没有发现与devices表相关的触发器\n";
    } else {
        foreach ($triggers as $trigger) {
            echo "触发器: {$trigger['Trigger']}\n";
            echo "事件: {$trigger['Event']}\n";
            echo "定义: {$trigger['Statement']}\n\n";
        }
    }
    
    // 检查事件
    echo "--- 事件调度器检查 ---\n";
    $stmt = $pdo->query("SHOW EVENTS");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "没有发现事件调度器\n";
    } else {
        foreach ($events as $event) {
            echo "事件: {$event['Name']}\n";
            echo "状态: {$event['Status']}\n";
            echo "定义: {$event['Event_definition']}\n\n";
        }
    }
    
    // 检查事件调度器是否开启
    echo "--- 事件调度器状态 ---\n";
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'event_scheduler'");
    $eventScheduler = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($eventScheduler) {
        echo "事件调度器状态: {$eventScheduler['Value']}\n";
    }
    
    // 显示当前设备状态
    echo "\n--- 当前设备状态 ---\n";
    $stmt = $pdo->query("SELECT device_id, status, last_seen, 
                         TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_ago 
                         FROM devices ORDER BY last_seen DESC");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($devices as $device) {
        echo "设备 {$device['device_id']}: {$device['status']}, 最后心跳: {$device['last_seen']} ({$device['seconds_ago']}秒前)\n";
    }
    
} catch (PDOException $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
    exit(1);
}
