-- 添加冲突检测相关字段到设备表
ALTER TABLE devices 
ADD COLUMN internal_ip VARCHAR(45) AFTER ip_address,
ADD COLUMN external_ip VARCHAR(45) AFTER internal_ip,
ADD COLUMN conflict_status ENUM('none', 'potential', 'confirmed') DEFAULT 'none' AFTER status,
ADD COLUMN conflict_detected_at DATETIME NULL AFTER conflict_status,
ADD COLUMN conflict_details TEXT NULL AFTER conflict_detected_at;

-- 为新字段添加索引
ALTER TABLE devices 
ADD INDEX idx_conflict_status (conflict_status),
ADD INDEX idx_internal_ip (internal_ip),
ADD INDEX idx_external_ip (external_ip);

-- 创建设备冲突表用于记录冲突历史
CREATE TABLE device_conflicts (
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
