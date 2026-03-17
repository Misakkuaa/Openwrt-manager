# 宝塔面板部署指南

## 环境要求

- 宝塔面板 7.0+
- NGINX 1.18+
- PHP 7.4+ (推荐 PHP 8.0)
- MySQL 5.7+ 或 MariaDB 10.3+

## 部署步骤

### 1. 创建网站

在宝塔面板中：
1. 点击"网站" → "添加站点"
2. 域名填写您的服务器IP或域名（如：owrt.yourdomain.com）
3. 选择PHP版本（推荐8.0）
4. 数据库选择MySQL，记录数据库名、用户名和密码
5. 点击"提交"

### 2. 上传项目文件

1. 删除网站根目录下的默认文件
2. 将 `server` 目录下的所有文件上传到网站根目录
3. 目录结构应该如下：
```
/www/wwwroot/your-domain/
├── api/
├── web/
├── classes/
├── config/
├── sql/
└── scripts/
```

### 3. 配置数据库

1. 在宝塔面板中点击"数据库"
2. 找到刚创建的数据库，点击"管理"进入phpMyAdmin
3. 导入 `sql/init.sql` 文件

或使用命令行：
```bash
mysql -u数据库用户名 -p数据库密码 数据库名 < sql/init.sql
```

### 4. 配置PHP

在宝塔面板中：
1. 点击"软件商店" → "PHP设置"
2. 安装必需的扩展：
   - curl
   - json
   - mysqli
   - openssl
   - mbstring
3. 修改PHP配置：
   - `memory_limit = 256M`
   - `max_execution_time = 300`
   - `post_max_size = 100M`
   - `upload_max_filesize = 100M`

### 5. 配置NGINX

在宝塔面板网站设置中，点击"配置文件"，添加以下配置：

```nginx
# API路径重写
location /api/ {
    try_files $uri $uri/ /api/index.php?$query_string;
}

# 安全配置
location ~ /config/ {
    deny all;
}

location ~ /sql/ {
    deny all;
}

location ~ /scripts/ {
    deny all;
}

location ~ /classes/ {
    deny all;
}

# 防止访问敏感文件
location ~ \.(conf|key|log)$ {
    deny all;
}

# PHP处理
location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi-80.sock;
    fastcgi_index index.php;
    include fastcgi.conf;
}
```

### 6. 设置目录权限

在宝塔面板文件管理中：
1. 选择网站根目录
2. 右键 → "权限" → 设置为755
3. `config` 目录设置为777（用于写入配置）
4. 创建日志目录并设置权限：
   ```bash
   mkdir -p /www/wwwroot/your-domain/logs
   chmod 777 /www/wwwroot/your-domain/logs
   ```

### 7. 配置应用

编辑 `config/config.php` 文件，更新数据库连接信息：

```php
define('DB_HOST', 'localhost');
define('DB_USER', '您的数据库用户名');
define('DB_PASS', '您的数据库密码');
define('DB_NAME', '您的数据库名');
```

### 8. 配置SSL（推荐）

在宝塔面板中：
1. 点击网站设置 → "SSL"
2. 选择"Let's Encrypt"申请免费证书
3. 或上传自己的SSL证书
4. 开启"强制HTTPS"

### 9. 设置定时任务

在宝塔面板中：
1. 点击"计划任务"
2. 添加Shell脚本任务：
   - 任务名称：清理OpenWrt日志
   - 执行周期：每小时
   - 脚本内容：
   ```bash
   /usr/bin/php /www/wwwroot/your-domain/scripts/cleanup.php
   ```

### 10. 防火墙配置

在宝塔面板安全设置中：
1. 开放80端口（HTTP）
2. 开放443端口（HTTPS）
3. 如需要，可以限制管理员IP访问

## 验证安装

1. 访问 `http://your-domain/web/` 查看管理界面
2. 访问 `http://your-domain/api/test.php` 测试API
3. 检查数据库是否正确创建了表结构

## 故障排除

### 1. 500错误
- 检查PHP错误日志
- 确认数据库连接配置正确
- 检查目录权限

### 2. API无法访问
- 检查NGINX配置是否正确
- 确认PHP-FPM正常运行
- 查看NGINX错误日志

### 3. 数据库连接失败
- 确认数据库服务正常
- 检查用户名密码是否正确
- 确认数据库权限设置

## 性能优化

### 1. 启用NGINX缓存
在网站配置中添加：
```nginx
location ~* \.(css|js|png|jpg|jpeg|gif|ico)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

### 2. 启用Gzip压缩
```nginx
gzip on;
gzip_vary on;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
```

### 3. PHP OPcache
在PHP设置中启用OPcache扩展。

## 安全建议

1. 定期更新宝塔面板和PHP版本
2. 设置强密码
3. 启用宝塔面板的安全模块
4. 定期备份数据库和文件
5. 监控访问日志，发现异常及时处理

## 备份方案

在宝塔面板中设置：
1. 数据库自动备份（每日）
2. 网站文件备份（每周）
3. 备份文件异地存储
