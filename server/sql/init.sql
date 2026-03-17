-- OpenWrt Remote Management System Database Schema
-- MySQL/MariaDB

-- 使用现有的management数据库
USE management;

-- 设备表
CREATE TABLE devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL UNIQUE,
    system_info TEXT,
    ip_address VARCHAR(45),
    first_seen DATETIME NOT NULL,
    last_seen DATETIME NOT NULL,
    status ENUM('online', 'offline') DEFAULT 'offline',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
) ENGINE=InnoDB;

-- 设备令牌表
CREATE TABLE device_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    token TEXT NOT NULL,
    signature VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    
    INDEX idx_device_id (device_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 设备指令表
CREATE TABLE device_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64) NOT NULL,
    command TEXT NOT NULL,
    status ENUM('pending', 'sent', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    exit_code INT NULL,
    output LONGTEXT,
    user_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    completed_at DATETIME NULL,
    
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 指令模板表
CREATE TABLE command_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    command TEXT NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'custom',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- 安全日志表
CREATE TABLE security_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(64),
    action VARCHAR(50) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_device_id (device_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB;

-- 系统配置表
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB;

-- 插入默认指令模板
INSERT INTO command_templates (name, command, description, category) VALUES
('查看系统信息', 'uname -a && cat /proc/cpuinfo | grep "model name" | head -1 && free -h', '显示系统基本信息', 'system'),
('重启系统', 'reboot', '立即重启设备', 'system'),
('查看内存使用', 'free -h && cat /proc/meminfo | head -10', '显示内存使用情况', 'monitoring'),
('查看磁盘空间', 'df -h', '显示磁盘使用情况', 'monitoring'),
('查看网络接口', 'ifconfig', '显示网络接口配置', 'network'),
('查看路由表', 'route -n', '显示路由表信息', 'network'),
('查看进程', 'ps aux | head -20', '显示运行进程', 'monitoring'),
('查看系统负载', 'uptime && cat /proc/loadavg', '显示系统负载信息', 'monitoring'),
('清理日志', 'logread | tail -100 > /tmp/recent.log && logread -c', '清理系统日志文件', 'maintenance'),
('更新软件包列表', 'opkg update', '更新opkg软件包列表', 'maintenance'),
('UCI系统配置', 'uci show system', '显示UCI系统配置信息', 'system'),
('UCI网络配置', 'uci show network', '显示UCI网络配置', 'network'),
('UCI无线配置', 'uci show wireless', '显示UCI无线配置', 'network'),
('防火墙规则', 'iptables -L -n', '显示防火墙规则', 'network'),
('已安装软件包', 'opkg list-installed', '显示已安装的软件包', 'system'),
('重启网络服务', '/etc/init.d/network restart', '重启网络服务', 'maintenance'),
('重启无线服务', '/etc/init.d/wireless restart', '重启无线服务', 'maintenance'),
('重启防火墙服务', '/etc/init.d/firewall restart', '重启防火墙服务', 'maintenance'),
('CPU详细信息', 'cat /proc/cpuinfo', '显示CPU详细信息', 'monitoring'),
('网络连接状态', 'netstat -tulpn', '显示网络连接状态', 'monitoring');

-- 插入默认系统配置
INSERT INTO system_config (config_key, config_value, description) VALUES
('heartbeat_timeout', '300', '设备心跳超时时间（秒）'),
('max_command_history', '1000', '每个设备保留的最大指令历史数量'),
('auth_token_lifetime', '86400', '认证令牌有效期（秒）'),
('rate_limit_window', '300', '速率限制时间窗口（秒）'),
('rate_limit_max_attempts', '10', '速率限制最大尝试次数'),
('auto_cleanup_days', '30', '自动清理多少天前的数据'),
('conflict_check_interval', '60', '设备冲突检查间隔（秒）');

-- 创建数据库用户（可选）
-- CREATE USER 'owrt_user'@'localhost' IDENTIFIED BY 'your_secure_password';
-- GRANT ALL PRIVILEGES ON owrt_management.* TO 'owrt_user'@'localhost';
-- FLUSH PRIVILEGES;
