<?php
/**
 * 设备时间问题诊断和修复脚本
 */

require_once '../config/config.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== 设备时间问题诊断 ===\n\n";
    
    // 1. 检查当前时区设置
    echo "1. 时区信息检查:\n";
    echo "PHP 时区: " . date_default_timezone_get() . "\n";
    echo "当前 PHP 时间: " . date('Y-m-d H:i:s') . "\n";
    
    // 检查数据库时区
    $stmt = $pdo->query("SELECT NOW() as db_time, @@time_zone as db_timezone");
    $result = $stmt->fetch();
    echo "数据库时区: " . $result['db_timezone'] . "\n";
    echo "数据库时间: " . $result['db_time'] . "\n\n";
    
    // 2. 检查设备表中的时间问题
    echo "2. 设备时间分析:\n";
    $stmt = $pdo->query("SELECT device_id, first_seen, last_seen, 
                         TIMESTAMPDIFF(HOUR, first_seen, NOW()) as hours_since_first,
                         TIMESTAMPDIFF(MINUTE, last_seen, NOW()) as minutes_since_last
                         FROM devices 
                         ORDER BY last_seen DESC 
                         LIMIT 10");
    
    $devices = $stmt->fetchAll();
    
    foreach ($devices as $device) {
        echo "设备: {$device['device_id']}\n";
        echo "  首次连接: {$device['first_seen']} ({$device['hours_since_first']} 小时前)\n";
        echo "  最近更新: {$device['last_seen']} ({$device['minutes_since_last']} 分钟前)\n";
        
        // 检查是否有时间异常
        if (abs($device['hours_since_first']) > 24 * 365) { // 超过一年
            echo "  ⚠️ 首次连接时间异常 (超过一年)\n";
        }
        if ($device['minutes_since_last'] < 0) {
            echo "  ⚠️ 最近更新时间在未来\n";
        }
        echo "\n";
    }
    
    // 3. 检查是否有时间在未来的记录
    echo "3. 时间异常检查:\n";
    
    // 检查未来时间
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM devices WHERE first_seen > NOW()");
    $futureFirst = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM devices WHERE last_seen > NOW()");
    $futureLast = $stmt->fetch()['count'];
    
    echo "首次连接时间在未来的设备: $futureFirst 个\n";
    echo "最近更新时间在未来的设备: $futureLast 个\n\n";
    
    // 检查极旧的时间
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM devices WHERE first_seen < '2020-01-01'");
    $oldFirst = $stmt->fetch()['count'];
    
    echo "首次连接时间早于2020年的设备: $oldFirst 个\n\n";
    
    // 4. 提供修复选项
    echo "4. 修复选项:\n";
    echo "如果发现时间问题，可以执行以下修复:\n\n";
    
    echo "选项 1: 设置正确的PHP时区 (推荐)\n";
    echo "在 config.php 中添加: date_default_timezone_set('Asia/Shanghai');\n\n";
    
    echo "选项 2: 修复异常的首次连接时间\n";
    echo "UPDATE devices SET first_seen = last_seen WHERE first_seen > NOW();\n";
    echo "UPDATE devices SET first_seen = '2025-01-01 00:00:00' WHERE first_seen < '2020-01-01';\n\n";
    
    echo "选项 3: 修复未来的最近更新时间\n";
    echo "UPDATE devices SET last_seen = NOW() WHERE last_seen > NOW();\n\n";
    
    // 5. 询问是否执行自动修复
    if ($futureFirst > 0 || $futureLast > 0 || $oldFirst > 0) {
        echo "发现时间异常，是否要自动修复？(这将修改数据库)\n";
        echo "要执行修复，请运行: php fix_device_times.php --fix\n";
    } else {
        echo "✅ 没有发现明显的时间异常\n";
    }
    
    // 6. 检查最近的设备活动
    echo "\n5. 最近设备活动:\n";
    $stmt = $pdo->query("SELECT device_id, last_seen, status,
                         CASE 
                             WHEN last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN '🟢 活跃'
                             WHEN last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN '🟡 最近活跃'
                             WHEN last_seen > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN '🟠 今日活跃'
                             ELSE '🔴 离线'
                         END as activity_status
                         FROM devices 
                         ORDER BY last_seen DESC");
    
    $activities = $stmt->fetchAll();
    foreach ($activities as $activity) {
        echo "{$activity['activity_status']} {$activity['device_id']} - {$activity['last_seen']} ({$activity['status']})\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
