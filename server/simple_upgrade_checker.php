<?php
/**
 * 简化的数据库升级检查和执行器
 * 不需要MySQL函数创建权限
 */

require_once '../classes/Database.php';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线时长功能 - 数据库升级</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .step { background: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin: 10px 0; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .sql-block { background: #f4f4f4; padding: 10px; margin: 10px 0; border-radius: 4px; font-family: monospace; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        button:disabled { background: #6c757d; cursor: not-allowed; }
    </style>
</head>
<body>
    <h1>🕐 在线时长功能数据库升级</h1>
    
    <?php
    try {
        $db = new Database();
        
        echo "<div class='step'>✅ 数据库连接成功</div>\n";
        
        // 检查当前表结构
        echo "<h2>当前数据库状态检查</h2>\n";
        
        $columns = $db->select("SHOW COLUMNS FROM devices");
        $hasUptimeFields = false;
        $existingFields = [];
        
        foreach ($columns as $column) {
            if (in_array($column['Field'], ['online_session_start', 'total_online_duration', 'last_offline_time'])) {
                $hasUptimeFields = true;
                $existingFields[] = $column['Field'];
            }
        }
        
        if ($hasUptimeFields) {
            echo "<div class='step warning'>⚠️ 检测到已存在在线时长字段: " . implode(', ', $existingFields) . "</div>\n";
            echo "<p>如果您想重新升级，请先备份数据，然后删除这些字段后重新执行。</p>\n";
        } else {
            echo "<div class='step'>📝 数据库需要升级，缺少在线时长字段</div>\n";
        }
        
        // 显示手动执行步骤
        echo "<h2>升级方案</h2>\n";
        echo "<p>由于您的环境可能没有创建MySQL函数的权限，我们提供两种升级方案：</p>\n";
        
        echo "<h3>方案一：自动升级（推荐）</h3>\n";
        echo "<div class='step'>\n";
        echo "<p>点击下面的按钮自动执行升级（跳过函数创建）：</p>\n";
        echo "<button onclick='autoUpgrade()' id='auto-upgrade-btn'>🚀 自动执行升级</button>\n";
        echo "<div id='auto-upgrade-result'></div>\n";
        echo "</div>\n";
        
        echo "<h3>方案二：手动升级</h3>\n";
        echo "<div class='step'>\n";
        echo "<p>如果自动升级失败，您可以在phpMyAdmin中手动执行以下SQL语句：</p>\n";
        
        $manualSteps = [
            "1. 添加在线时长字段" => "ALTER TABLE devices ADD COLUMN online_session_start DATETIME NULL, ADD COLUMN total_online_duration INT DEFAULT 0, ADD COLUMN last_offline_time DATETIME NULL;",
            "2. 添加索引优化性能" => "ALTER TABLE devices ADD INDEX idx_online_session_start (online_session_start), ADD INDEX idx_total_online_duration (total_online_duration);",
            "3. 初始化在线设备数据" => "UPDATE devices SET online_session_start = last_seen WHERE status = 'online' AND last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND);",
            "4. 创建统计视图" => "CREATE VIEW device_uptime_stats AS SELECT device_id, status, first_seen, last_seen, online_session_start, total_online_duration, CASE WHEN status = 'online' AND online_session_start IS NOT NULL THEN total_online_duration + TIMESTAMPDIFF(SECOND, online_session_start, NOW()) ELSE total_online_duration END as current_total_online_duration FROM devices;"
        ];
        
        foreach ($manualSteps as $title => $sql) {
            echo "<h4>{$title}</h4>\n";
            echo "<div class='sql-block'>{$sql}</div>\n";
        }
        
        echo "</div>\n";
        
        // 检查升级状态
        if ($hasUptimeFields) {
            echo "<h2>功能验证</h2>\n";
            
            // 检查统计视图
            try {
                $viewCheck = $db->selectOne("SELECT COUNT(*) as count FROM device_uptime_stats");
                echo "<div class='step success'>✅ 统计视图工作正常，共有 {$viewCheck['count']} 个设备</div>\n";
            } catch (Exception $e) {
                echo "<div class='step error'>❌ 统计视图不存在或无法访问</div>\n";
            }
            
            // 测试时长计算
            $testDevices = $db->select("SELECT device_id, total_online_duration, online_session_start FROM devices LIMIT 3");
            if ($testDevices) {
                echo "<div class='step success'>✅ 在线时长字段可以正常访问</div>\n";
                echo "<h4>示例设备数据：</h4>\n";
                foreach ($testDevices as $device) {
                    echo "<p>设备 {$device['device_id']}: 累计在线 {$device['total_online_duration']} 秒";
                    if ($device['online_session_start']) {
                        echo ", 当前会话开始于 {$device['online_session_start']}";
                    }
                    echo "</p>\n";
                }
            }
            
            echo "<h3>🎉 升级完成！</h3>\n";
            echo "<p>您现在可以访问以下页面体验新功能：</p>\n";
            echo "<ul>\n";
            echo "<li><a href='devices_smart_refresh.html' target='_blank'>智能设备列表（含在线时长）</a></li>\n";
            echo "<li><a href='uptime_leaderboard.html' target='_blank'>在线时长排行榜</a></li>\n";
            echo "<li><a href='test_uptime_features.html' target='_blank'>功能测试页面</a></li>\n";
            echo "</ul>\n";
        }
        
    } catch (Exception $e) {
        echo "<div class='step error'>❌ 数据库连接失败: " . $e->getMessage() . "</div>\n";
        echo "<p>请检查数据库配置和连接设置。</p>\n";
    }
    ?>
    
    <script>
        async function autoUpgrade() {
            const button = document.getElementById('auto-upgrade-btn');
            const resultDiv = document.getElementById('auto-upgrade-result');
            
            button.disabled = true;
            button.textContent = '升级中...';
            
            try {
                const response = await fetch('simple_upgrade_executor.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ action: 'upgrade' })
                });
                
                const result = await response.text();
                resultDiv.innerHTML = result;
                
                if (result.includes('成功')) {
                    button.textContent = '✅ 升级完成';
                    button.style.background = '#28a745';
                } else {
                    button.textContent = '❌ 升级失败';
                    button.style.background = '#dc3545';
                }
                
            } catch (error) {
                resultDiv.innerHTML = `<div class="step error">❌ 升级失败: ${error.message}</div>`;
                button.textContent = '❌ 升级失败';
                button.style.background = '#dc3545';
            }
            
            button.disabled = false;
        }
    </script>
</body>
</html>
