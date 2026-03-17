-- =============================================
-- OpenWrt 设备管理系统 - 在线时长功能升级
-- 手动执行版本（适用于 phpMyAdmin 等工具）
-- =============================================

-- 第1步：添加新字段
-- 请逐条执行以下SQL语句

-- 1.1 添加在线会话开始时间字段
ALTER TABLE devices 
ADD COLUMN online_session_start DATETIME NULL COMMENT '当前在线会话开始时间';

-- 1.2 添加累计在线时长字段
ALTER TABLE devices 
ADD COLUMN total_online_duration INT DEFAULT 0 COMMENT '累计在线时长（秒）';

-- 1.3 添加最后离线时间字段
ALTER TABLE devices 
ADD COLUMN last_offline_time DATETIME NULL COMMENT '最后一次离线时间';

-- 第2步：添加索引（可选，提升查询性能）

-- 2.1 为在线会话开始时间添加索引
ALTER TABLE devices 
ADD INDEX idx_online_session_start (online_session_start);

-- 2.2 为累计在线时长添加索引
ALTER TABLE devices 
ADD INDEX idx_total_online_duration (total_online_duration);

-- 第3步：初始化现有设备数据

-- 3.1 为当前在线的设备设置会话开始时间
UPDATE devices 
SET online_session_start = last_seen 
WHERE status = 'online' 
AND last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND)
AND online_session_start IS NULL;

-- 第4步：创建统计视图

-- 4.1 创建在线时长统计视图
CREATE OR REPLACE VIEW device_uptime_stats AS
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
FROM devices;

-- 第5步：验证升级结果

-- 5.1 检查新字段是否添加成功
SHOW COLUMNS FROM devices;

-- 5.2 检查统计视图是否工作
SELECT COUNT(*) as total_devices FROM device_uptime_stats;

-- 5.3 查看前5个设备的在线时长信息
SELECT 
    device_id,
    status,
    online_session_start,
    total_online_duration,
    current_session_duration,
    current_total_online_duration
FROM device_uptime_stats 
ORDER BY current_total_online_duration DESC 
LIMIT 5;

-- =============================================
-- 升级完成！
-- 
-- 注意事项：
-- 1. 如果某个步骤失败，请检查错误信息
-- 2. 如果字段已存在，会提示 "Duplicate column name" 错误，可以忽略
-- 3. 如果索引已存在，会提示 "Duplicate key name" 错误，可以忽略
-- 4. 不需要创建MySQL函数，PHP代码会处理时长格式化
-- =============================================
