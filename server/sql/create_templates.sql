-- 创建指令模板表
CREATE TABLE IF NOT EXISTS command_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    command TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'custom',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_name (name)
);

-- 插入一些示例模板
INSERT INTO command_templates (name, description, command, category) VALUES 
('查看系统信息', '显示系统基本信息', 'uname -a && cat /proc/cpuinfo | grep "model name" | head -1 && free -h', 'system'),
('重启系统', '立即重启设备', 'reboot', 'system'),
('查看内存使用', '显示内存使用情况', 'free -h && cat /proc/meminfo | head -10', 'monitoring'),
('查看磁盘空间', '显示磁盘使用情况', 'df -h', 'monitoring'),
('查看网络接口', '显示网络接口配置', 'ifconfig', 'network'),
('查看路由表', '显示路由表信息', 'route -n', 'network'),
('查看进程', '显示运行进程', 'ps aux | head -20', 'monitoring'),
('查看系统负载', '显示系统负载信息', 'uptime && cat /proc/loadavg', 'monitoring'),
('清理日志', '清理系统日志文件', 'logread | tail -100 > /tmp/recent.log && logread -c', 'maintenance'),
('更新软件包列表', '更新opkg软件包列表', 'opkg update', 'maintenance');
