-- 只需要创建设备冲突表，其他字段都已经存在了
-- 在 phpMyAdmin 中执行以下语句

CREATE TABLE IF NOT EXISTS device_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    conflict_type ENUM('duplicate_id', 'ip_mismatch', 'simultaneous_online') NOT NULL,
    conflicting_device_id VARCHAR(64),
    device_ip VARCHAR(45),
    conflicting_ip VARCHAR(45),
    server_ip VARCHAR(45),
    details TEXT,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device_id (device_id),
    INDEX idx_conflict_type (conflict_type),
    INDEX idx_resolved (resolved),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- 迁移现有 ip_address 数据到 external_ip（如果还没有迁移）
UPDATE devices SET external_ip = ip_address WHERE ip_address IS NOT NULL AND (external_ip IS NULL OR external_ip = '');

-- 执行完成后，设备管理界面应该可以正常加载了
