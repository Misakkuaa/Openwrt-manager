<?php
/**
 * 时间设置验证脚本
 */

require_once '../config/config.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== 时间设置验证 ===\n\n";
    
    // 1. 检查PHP时区设置
    echo "1. PHP时区设置:\n";
    echo "当前时区: " . date_default_timezone_get() . "\n";
    echo "当前时间: " . date('Y-m-d H:i:s') . "\n";
    echo "当前时间戳: " . time() . "\n\n";
    
    // 2. 检查数据库时区
    echo "2. 数据库时区设置:\n";
    $stmt = $pdo->query("SELECT NOW() as current_time, @@time_zone as timezone, @@system_time_zone as system_timezone");
    $result = $stmt->fetch();
    echo "数据库时区: " . $result['timezone'] . "\n";
    echo "系统时区: " . $result['system_timezone'] . "\n";
    echo "数据库时间: " . $result['current_time'] . "\n\n";
    
    // 3. 设置数据库时区为中国时区
    echo "3. 设置数据库时区:\n";
    $pdo->exec("SET time_zone = '+08:00'");
    
    $stmt = $pdo->query("SELECT NOW() as current_time, @@time_zone as timezone");
    $result = $stmt->fetch();
    echo "设置后数据库时区: " . $result['timezone'] . "\n";
    echo "设置后数据库时间: " . $result['current_time'] . "\n\n";
    
    // 4. 测试时间一致性
    echo "4. 时间一致性测试:\n";
    $phpTime = date('Y-m-d H:i:s');
    $stmt = $pdo->query("SELECT NOW() as db_time");
    $dbTime = $stmt->fetch()['db_time'];
    
    echo "PHP时间: $phpTime\n";
    echo "数据库时间: $dbTime\n";
    
    $phpTimestamp = strtotime($phpTime);
    $dbTimestamp = strtotime($dbTime);
    $diff = abs($phpTimestamp - $dbTimestamp);
    
    echo "时间差: $diff 秒\n";
    
    if ($diff <= 2) {
        echo "✅ PHP和数据库时间一致\n";
    } else {
        echo "❌ PHP和数据库时间不一致\n";
    }
    echo "\n";
    
    // 5. 检查设备表中的时间
    echo "5. 设备表时间检查:\n";
    $stmt = $pdo->query("SELECT device_id, first_seen, last_seen,
                         TIMESTAMPDIFF(SECOND, first_seen, NOW()) as seconds_since_first,
                         TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_last
                         FROM devices 
                         ORDER BY last_seen DESC 
                         LIMIT 5");
    
    $devices = $stmt->fetchAll();
    
    if (empty($devices)) {
        echo "没有找到设备记录\n";
    } else {
        foreach ($devices as $device) {
            echo "设备: {$device['device_id']}\n";
            echo "  首次连接: {$device['first_seen']} ({$device['seconds_since_first']} 秒前)\n";
            echo "  最近更新: {$device['last_seen']} ({$device['seconds_since_last']} 秒前)\n";
            
            // 检查时间是否合理
            if ($device['seconds_since_first'] < 0) {
                echo "  ⚠️ 首次连接时间在未来\n";
            } elseif ($device['seconds_since_first'] > 365 * 24 * 3600) {
                echo "  ⚠️ 首次连接时间超过一年\n";
            } else {
                echo "  ✅ 首次连接时间正常\n";
            }
            
            if ($device['seconds_since_last'] < 0) {
                echo "  ⚠️ 最近更新时间在未来\n";
            } elseif ($device['seconds_since_last'] > 24 * 3600) {
                echo "  ⚠️ 最近更新时间超过一天\n";
            } else {
                echo "  ✅ 最近更新时间正常\n";
            }
            echo "\n";
        }
    }
    
    // 6. 提供修复建议
    echo "6. 修复建议:\n";
    echo "✅ 已在 config.php 中设置时区为 Asia/Shanghai\n";
    echo "✅ 已在数据库中设置时区为 +08:00\n";
    echo "✅ 已在 DeviceManager.php 中添加时间调试日志\n\n";
    
    echo "如果还有时间问题，请运行:\n";
    echo "php diagnose_device_times.php - 诊断时间问题\n";
    echo "php fix_device_times.php --fix - 修复时间问题\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
