<?php
/**
 * 设备时间修复脚本
 */

require_once '../config/config.php';

// 检查是否传入了修复参数
$shouldFix = isset($argv[1]) && $argv[1] === '--fix';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=management;charset=utf8mb4', 'misakku', 'misakkupassword', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "=== 设备时间修复工具 ===\n\n";
    
    if (!$shouldFix) {
        echo "这是预览模式，不会修改数据库\n";
        echo "要执行修复，请运行: php " . basename(__FILE__) . " --fix\n\n";
    } else {
        echo "⚠️ 修复模式 - 将修改数据库\n\n";
    }
    
    // 1. 设置正确的时区
    echo "1. 设置时区为 Asia/Shanghai\n";
    if ($shouldFix) {
        date_default_timezone_set('Asia/Shanghai');
        $pdo->exec("SET time_zone = '+08:00'");
        echo "✅ 时区已设置\n";
    } else {
        echo "预览: 将设置时区为 Asia/Shanghai\n";
    }
    echo "\n";
    
    // 2. 修复未来的首次连接时间
    echo "2. 修复未来的首次连接时间\n";
    $stmt = $pdo->query("SELECT device_id, first_seen FROM devices WHERE first_seen > NOW()");
    $futureDevices = $stmt->fetchAll();
    
    if (empty($futureDevices)) {
        echo "✅ 没有发现未来的首次连接时间\n";
    } else {
        echo "发现 " . count($futureDevices) . " 个设备的首次连接时间在未来:\n";
        foreach ($futureDevices as $device) {
            echo "  - {$device['device_id']}: {$device['first_seen']}\n";
        }
        
        if ($shouldFix) {
            $stmt = $pdo->prepare("UPDATE devices SET first_seen = last_seen WHERE first_seen > NOW()");
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "✅ 已修复 $affected 个设备的首次连接时间\n";
        } else {
            echo "预览: 将把这些时间设置为对应的最近更新时间\n";
        }
    }
    echo "\n";
    
    // 3. 修复过早的首次连接时间
    echo "3. 修复过早的首次连接时间 (早于2020年)\n";
    $stmt = $pdo->query("SELECT device_id, first_seen FROM devices WHERE first_seen < '2020-01-01'");
    $oldDevices = $stmt->fetchAll();
    
    if (empty($oldDevices)) {
        echo "✅ 没有发现过早的首次连接时间\n";
    } else {
        echo "发现 " . count($oldDevices) . " 个设备的首次连接时间过早:\n";
        foreach ($oldDevices as $device) {
            echo "  - {$device['device_id']}: {$device['first_seen']}\n";
        }
        
        if ($shouldFix) {
            $stmt = $pdo->prepare("UPDATE devices SET first_seen = '2025-01-01 00:00:00' WHERE first_seen < '2020-01-01'");
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "✅ 已修复 $affected 个设备的首次连接时间\n";
        } else {
            echo "预览: 将把这些时间设置为 2025-01-01 00:00:00\n";
        }
    }
    echo "\n";
    
    // 4. 修复未来的最近更新时间
    echo "4. 修复未来的最近更新时间\n";
    $stmt = $pdo->query("SELECT device_id, last_seen FROM devices WHERE last_seen > NOW()");
    $futureLastSeen = $stmt->fetchAll();
    
    if (empty($futureLastSeen)) {
        echo "✅ 没有发现未来的最近更新时间\n";
    } else {
        echo "发现 " . count($futureLastSeen) . " 个设备的最近更新时间在未来:\n";
        foreach ($futureLastSeen as $device) {
            echo "  - {$device['device_id']}: {$device['last_seen']}\n";
        }
        
        if ($shouldFix) {
            $stmt = $pdo->prepare("UPDATE devices SET last_seen = NOW() WHERE last_seen > NOW()");
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "✅ 已修复 $affected 个设备的最近更新时间\n";
        } else {
            echo "预览: 将把这些时间设置为当前时间\n";
        }
    }
    echo "\n";
    
    // 5. 确保首次连接时间不晚于最近更新时间
    echo "5. 修复首次连接时间晚于最近更新时间的问题\n";
    $stmt = $pdo->query("SELECT device_id, first_seen, last_seen FROM devices WHERE first_seen > last_seen");
    $invalidOrder = $stmt->fetchAll();
    
    if (empty($invalidOrder)) {
        echo "✅ 所有设备的时间顺序正确\n";
    } else {
        echo "发现 " . count($invalidOrder) . " 个设备的时间顺序错误:\n";
        foreach ($invalidOrder as $device) {
            echo "  - {$device['device_id']}: 首次 {$device['first_seen']} > 最近 {$device['last_seen']}\n";
        }
        
        if ($shouldFix) {
            $stmt = $pdo->prepare("UPDATE devices SET first_seen = last_seen WHERE first_seen > last_seen");
            $stmt->execute();
            $affected = $stmt->rowCount();
            echo "✅ 已修复 $affected 个设备的时间顺序\n";
        } else {
            echo "预览: 将把首次连接时间设置为最近更新时间\n";
        }
    }
    echo "\n";
    
    // 6. 显示修复后的状态
    if ($shouldFix) {
        echo "=== 修复完成后的状态 ===\n";
        $stmt = $pdo->query("SELECT 
                                COUNT(*) as total_devices,
                                COUNT(CASE WHEN first_seen > NOW() THEN 1 END) as future_first,
                                COUNT(CASE WHEN last_seen > NOW() THEN 1 END) as future_last,
                                COUNT(CASE WHEN first_seen < '2020-01-01' THEN 1 END) as old_first,
                                COUNT(CASE WHEN first_seen > last_seen THEN 1 END) as invalid_order
                             FROM devices");
        
        $stats = $stmt->fetch();
        echo "总设备数: {$stats['total_devices']}\n";
        echo "首次连接时间在未来: {$stats['future_first']}\n";
        echo "最近更新时间在未来: {$stats['future_last']}\n";
        echo "首次连接时间过早: {$stats['old_first']}\n";
        echo "时间顺序错误: {$stats['invalid_order']}\n";
        
        if ($stats['future_first'] == 0 && $stats['future_last'] == 0 && 
            $stats['old_first'] == 0 && $stats['invalid_order'] == 0) {
            echo "🎉 所有时间问题已修复！\n";
        } else {
            echo "⚠️ 仍有一些问题需要手动检查\n";
        }
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
