<?php
/**
 * 简单的数据库升级执行器
 * 不需要MySQL函数权限
 */

header('Content-Type: text/html; charset=utf-8');

require_once '../classes/Database.php';

try {
    $db = new Database();
    
    echo "<div class='step'>🔄 开始执行数据库升级...</div>\n";
    
    $steps = [
        [
            'title' => '添加在线时长字段',
            'sql' => "ALTER TABLE devices 
                     ADD COLUMN online_session_start DATETIME NULL COMMENT '当前在线会话开始时间',
                     ADD COLUMN total_online_duration INT DEFAULT 0 COMMENT '累计在线时长（秒）',
                     ADD COLUMN last_offline_time DATETIME NULL COMMENT '最后一次离线时间'"
        ],
        [
            'title' => '添加性能优化索引',
            'sql' => "ALTER TABLE devices 
                     ADD INDEX idx_online_session_start (online_session_start),
                     ADD INDEX idx_total_online_duration (total_online_duration)"
        ],
        [
            'title' => '初始化在线设备数据',
            'sql' => "UPDATE devices 
                     SET online_session_start = last_seen 
                     WHERE status = 'online' 
                     AND last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND)
                     AND online_session_start IS NULL"
        ],
        [
            'title' => '创建统计视图',
            'sql' => "CREATE OR REPLACE VIEW device_uptime_stats AS
                     SELECT 
                         device_id,
                         status,
                         first_seen,
                         last_seen,
                         online_session_start,
                         total_online_duration,
                         CASE 
                             WHEN status = 'online' AND online_session_start IS NOT NULL THEN
                                 total_online_duration + TIMESTAMPDIFF(SECOND, online_session_start, NOW())
                             ELSE 
                                 total_online_duration
                         END as current_total_online_duration,
                         CASE 
                             WHEN status = 'online' AND online_session_start IS NOT NULL THEN
                                 TIMESTAMPDIFF(SECOND, online_session_start, NOW())
                             ELSE 
                                 0
                         END as current_session_duration
                     FROM devices"
        ]
    ];
    
    $successCount = 0;
    $totalSteps = count($steps);
    
    foreach ($steps as $index => $step) {
        echo "<div class='step'>📝 步骤 " . ($index + 1) . ": {$step['title']}</div>\n";
        
        try {
            $db->execute($step['sql']);
            echo "<div class='step success'>✅ {$step['title']} - 执行成功</div>\n";
            $successCount++;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            
            // 检查是否是"已存在"类型的错误（可以忽略）
            if (strpos($errorMsg, 'Duplicate column name') !== false ||
                strpos($errorMsg, 'Duplicate key name') !== false) {
                echo "<div class='step warning'>⚠️ {$step['title']} - 已存在，跳过</div>\n";
                $successCount++;
            } else {
                echo "<div class='step error'>❌ {$step['title']} - 失败: {$errorMsg}</div>\n";
            }
        }
        
        // 添加一点延迟，让页面更新更流畅
        usleep(500000); // 0.5秒
    }
    
    echo "<div class='step'>📊 升级进度: {$successCount}/{$totalSteps} 步骤完成</div>\n";
    
    if ($successCount == $totalSteps) {
        echo "<div class='step success'>🎉 数据库升级完全成功！</div>\n";
        
        // 验证升级结果
        echo "<div class='step'>🔍 验证升级结果...</div>\n";
        
        try {
            $deviceCount = $db->selectOne("SELECT COUNT(*) as count FROM device_uptime_stats");
            echo "<div class='step success'>✅ 统计视图工作正常，共有 {$deviceCount['count']} 个设备</div>\n";
        } catch (Exception $e) {
            echo "<div class='step error'>❌ 统计视图验证失败</div>\n";
        }
        
        echo "<div class='step success'>\n";
        echo "<h3>🚀 功能已就绪！</h3>\n";
        echo "<p>您现在可以使用以下新功能：</p>\n";
        echo "<ul>\n";
        echo "<li>设备在线时长统计</li>\n";
        echo "<li>在线时长排行榜</li>\n";
        echo "<li>实时会话监控</li>\n";
        echo "</ul>\n";
        echo "<p><strong>下一步：</strong></p>\n";
        echo "<ol>\n";
        echo "<li><a href='devices_smart_refresh.html' target='_blank'>查看智能设备列表</a></li>\n";
        echo "<li><a href='uptime_leaderboard.html' target='_blank'>查看在线时长排行榜</a></li>\n";
        echo "<li><a href='test_uptime_features.html' target='_blank'>测试所有功能</a></li>\n";
        echo "</ol>\n";
        echo "</div>\n";
        
    } else {
        echo "<div class='step warning'>⚠️ 升级部分完成，请检查失败的步骤</div>\n";
        echo "<p>您可以尝试在phpMyAdmin中手动执行失败的SQL语句。</p>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='step error'>❌ 升级执行失败: " . $e->getMessage() . "</div>\n";
}

?>

<style>
.step {
    background: #f8f9fa;
    border-left: 4px solid #007bff;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
}
.success {
    border-left-color: #28a745;
    background: #d4edda;
}
.warning {
    border-left-color: #ffc107;
    background: #fff3cd;
}
.error {
    border-left-color: #dc3545;
    background: #f8d7da;
}
</style>
