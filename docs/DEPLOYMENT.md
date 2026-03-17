# OpenWrt远程管理系统部署指南

## 系统概述

OpenWrt远程管理系统由两部分组成：
- **服务器端**: 运行在Ubuntu服务器上的PHP Web应用
- **客户端**: 运行在OpenWrt路由器上的C语言守护进程

## 服务器端部署

### 系统要求

- Ubuntu 18.04+ 或 Debian 10+
- 至少2GB RAM
- 至少10GB可用磁盘空间
- 固定IP地址或域名

### 自动安装

使用提供的安装脚本：

```bash
# 下载项目
git clone <repository-url>
cd Openwrt-manager

# 运行安装脚本
sudo chmod +x install_server.sh
sudo ./install_server.sh
```

### 手动安装

#### 1. 安装依赖

```bash
sudo apt update
sudo apt install -y apache2 mysql-server php php-mysql php-json php-curl php-mbstring php-xml libapache2-mod-php
```

#### 2. 配置数据库

```bash
# 安全安装MySQL
sudo mysql_secure_installation

# 创建数据库
sudo mysql -u root -p
```

```sql
CREATE DATABASE owrt_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'owrt_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON owrt_management.* TO 'owrt_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# 导入数据库结构
mysql -u owrt_user -p owrt_management < server/sql/init.sql
```

#### 3. 配置Web服务器

```bash
# 复制文件
sudo cp -r server /var/www/html/owrt-server
sudo chown -R www-data:www-data /var/www/html/owrt-server

# 配置Apache虚拟主机
sudo nano /etc/apache2/sites-available/owrt-server.conf
```

添加以下内容：

```apache
<VirtualHost *:80>
    ServerName your-server-ip
    DocumentRoot /var/www/html/owrt-server/web
    
    <Directory /var/www/html/owrt-server/web>
        AllowOverride All
        Require all granted
    </Directory>
    
    Alias /api /var/www/html/owrt-server/api
    <Directory /var/www/html/owrt-server/api>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/owrt-server_error.log
    CustomLog ${APACHE_LOG_DIR}/owrt-server_access.log combined
</VirtualHost>
```

```bash
# 启用站点和模块
sudo a2ensite owrt-server.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 4. 配置PHP

```bash
# 编辑配置文件
sudo nano /var/www/html/owrt-server/config/config.php
```

更新数据库连接信息：

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'owrt_user');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'owrt_management');
```

#### 5. 设置权限

```bash
sudo chown -R www-data:www-data /var/www/html/owrt-server
sudo chmod -R 755 /var/www/html/owrt-server
sudo chmod -R 777 /var/www/html/owrt-server/config
```

### SSL配置（推荐）

#### 使用Let's Encrypt

```bash
# 安装Certbot
sudo apt install certbot python3-certbot-apache

# 获取证书
sudo certbot --apache -d your-domain.com
```

#### 使用自签名证书

```bash
# 生成证书
sudo mkdir /etc/ssl/owrt
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/owrt/server.key \
    -out /etc/ssl/owrt/server.crt

# 配置HTTPS虚拟主机
sudo nano /etc/apache2/sites-available/owrt-server-ssl.conf
```

### 防火墙配置

```bash
# 安装ufw
sudo apt install ufw

# 配置防火墙
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

## OpenWrt客户端部署

### 方式一：使用预编译包

```bash
# 下载对应架构的ipk包
wget http://your-server.com/downloads/owrt-remote-client_1.0-1_mips_24kc.ipk

# 安装依赖
opkg update
opkg install libcurl libjson-c libopenssl

# 安装客户端
opkg install owrt-remote-client_1.0-1_mips_24kc.ipk
```

### 方式二：使用安装脚本

```bash
# 复制安装脚本到路由器
scp install_client.sh root@192.168.1.1:/tmp/

# 在路由器上运行
ssh root@192.168.1.1
chmod +x /tmp/install_client.sh
/tmp/install_client.sh
```

### 方式三：手动安装

#### 1. 安装依赖

```bash
opkg update
opkg install libcurl libjson-c libopenssl
```

#### 2. 复制客户端程序

```bash
# 编译完成后复制到路由器
scp owrt_client root@192.168.1.1:/usr/sbin/
ssh root@192.168.1.1 "chmod +x /usr/sbin/owrt_client"
```

#### 3. 创建配置文件

```bash
ssh root@192.168.1.1
cat > /etc/owrt_client.conf << EOF
server_url=http://your-server-ip/api
heartbeat_interval=30
max_retries=3
auth_token=
EOF
```

#### 4. 创建启动脚本

```bash
cat > /etc/init.d/owrt-client << 'EOF'
#!/bin/sh /etc/rc.common

START=99
STOP=15

USE_PROCD=1
PROG=/usr/sbin/owrt_client

start_service() {
    procd_open_instance
    procd_set_param command $PROG
    procd_set_param respawn
    procd_close_instance
}
EOF

chmod +x /etc/init.d/owrt-client
/etc/init.d/owrt-client enable
```

## 系统配置

### 服务器端配置

#### 1. 数据库优化

```sql
-- 创建索引优化查询性能
ALTER TABLE devices ADD INDEX idx_last_seen_status (last_seen, status);
ALTER TABLE device_commands ADD INDEX idx_device_status (device_id, status);

-- 配置自动清理
CREATE EVENT cleanup_old_logs
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

#### 2. Apache优化

编辑 `/etc/apache2/apache2.conf`：

```apache
# 性能优化
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 15

# 内存限制
StartServers 2
MinSpareServers 2
MaxSpareServers 5
MaxRequestWorkers 100
```

#### 3. PHP优化

编辑 `/etc/php/*/apache2/php.ini`：

```ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
```

### 客户端配置

#### 1. 网络配置

确保客户端可以访问服务器：

```bash
# 测试连接
ping your-server-ip
curl -I http://your-server-ip/api/auth.php
```

#### 2. 防火墙配置

```bash
# 添加防火墙规则
uci add firewall rule
uci set firewall.@rule[-1].name='OWRT Client'
uci set firewall.@rule[-1].src='lan'
uci set firewall.@rule[-1].proto='tcp'
uci set firewall.@rule[-1].dest_port='80 443'
uci set firewall.@rule[-1].target='ACCEPT'
uci commit firewall
/etc/init.d/firewall restart
```

## 监控和维护

### 日志管理

#### 服务器端

```bash
# 查看Apache日志
sudo tail -f /var/log/apache2/owrt-server_access.log
sudo tail -f /var/log/apache2/owrt-server_error.log

# 查看应用日志
sudo tail -f /var/log/owrt-server.log

# 查看MySQL日志
sudo tail -f /var/log/mysql/error.log
```

#### 客户端

```bash
# 查看系统日志
logread | grep owrt_client

# 查看实时日志
logread -f | grep owrt_client

# 查看进程状态
ps | grep owrt_client
```

### 性能监控

#### 服务器监控脚本

创建 `/usr/local/bin/owrt_monitor.sh`：

```bash
#!/bin/bash

# 检查服务状态
systemctl is-active apache2 mysql

# 检查数据库连接
mysql -u owrt_user -p$DB_PASSWORD -e "SELECT COUNT(*) FROM owrt_management.devices;" 2>/dev/null

# 检查磁盘空间
df -h | grep -E "(/$|/var)"

# 检查内存使用
free -h
```

#### 设置定时任务

```bash
sudo crontab -e
```

添加：

```cron
# 每小时检查系统状态
0 * * * * /usr/local/bin/owrt_monitor.sh

# 每天备份数据库
0 2 * * * mysqldump -u owrt_user -p$DB_PASSWORD owrt_management > /backup/owrt_$(date +\%Y\%m\%d).sql

# 每周清理旧日志
0 0 * * 0 find /var/log -name "*.log" -mtime +7 -delete
```

### 故障排除

#### 常见问题

1. **客户端无法连接服务器**
   ```bash
   # 检查网络连通性
   ping server-ip
   
   # 检查防火墙
   iptables -L
   
   # 检查服务状态
   /etc/init.d/owrt-client status
   ```

2. **服务器响应慢**
   ```bash
   # 检查数据库性能
   mysql -u root -p -e "SHOW PROCESSLIST;"
   
   # 检查Apache状态
   apache2ctl status
   
   # 检查系统负载
   top
   htop
   ```

3. **认证失败**
   ```bash
   # 检查时间同步
   date
   ntpdate -s pool.ntp.org
   
   # 重新生成设备ID
   rm /etc/owrt_device_id
   /etc/init.d/owrt-client restart
   ```

### 备份和恢复

#### 数据库备份

```bash
# 创建备份脚本
cat > /usr/local/bin/backup_owrt.sh << 'EOF'
#!/bin/bash
BACKUP_DIR="/backup/owrt"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# 备份数据库
mysqldump -u owrt_user -p$DB_PASSWORD owrt_management > $BACKUP_DIR/owrt_db_$DATE.sql

# 备份配置文件
tar -czf $BACKUP_DIR/owrt_config_$DATE.tar.gz /var/www/html/owrt-server/config/

# 删除7天前的备份
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
EOF

chmod +x /usr/local/bin/backup_owrt.sh
```

#### 恢复数据

```bash
# 恢复数据库
mysql -u owrt_user -p$DB_PASSWORD owrt_management < backup_file.sql

# 恢复配置
tar -xzf owrt_config_backup.tar.gz -C /
```

## 安全加固

### 服务器安全

1. **更新系统**
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```

2. **配置SSH**
   ```bash
   # 禁用root登录
   sudo nano /etc/ssh/sshd_config
   # 设置 PermitRootLogin no
   ```

3. **安装fail2ban**
   ```bash
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   ```

### 通信安全

1. **使用HTTPS**
   - 强制所有API调用使用HTTPS
   - 配置HTTP重定向到HTTPS

2. **API密钥管理**
   - 定期轮换密钥
   - 使用强随机密钥

3. **输入验证**
   - 严格验证所有输入
   - 使用白名单过滤命令

这个部署指南提供了完整的系统部署流程，包括自动和手动安装选项，以及详细的配置、监控和维护说明。
