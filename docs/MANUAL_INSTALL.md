# OpenWrt远程管理系统 - 手动安装指南

## 前置条件

- 已安装宝塔面板
- NGINX 1.18+
- PHP 7.4+ (推荐 PHP 8.0)
- MySQL 5.7+ 或 MariaDB 10.3+

## 第一步：创建网站

### 1.1 在宝塔面板添加站点
1. 登录宝塔面板
2. 点击左侧菜单 "网站" → "添加站点"
3. 填写域名或IP地址（例：owrt.yourdomain.com）
4. 选择PHP版本（推荐 8.0）
5. 创建数据库，记录以下信息：
   - 数据库名：`owrt_management`
   - 用户名：`owrt_user`
   - 密码：`生成的强密码`

### 1.2 目录结构确认
创建后的网站目录应为：`/www/wwwroot/your-domain/`

## 第二步：上传项目文件

### 2.1 文件上传
1. 将 `server` 目录下的所有文件上传到网站根目录
2. 确保目录结构如下：
```
/www/wwwroot/your-domain/
├── api/           # API接口文件
├── web/           # Web管理界面
├── classes/       # PHP类库
├── config/        # 配置文件
├── sql/           # 数据库脚本
├── scripts/       # 系统脚本
└── logs/          # 日志目录（需创建）
```

### 2.2 创建必要目录
```bash
mkdir -p /www/wwwroot/your-domain/logs
```

### 2.3 设置文件权限
```bash
chown -R www:www /www/wwwroot/your-domain
chmod -R 755 /www/wwwroot/your-domain
chmod -R 777 /www/wwwroot/your-domain/config
chmod -R 777 /www/wwwroot/your-domain/logs
```

## 第三步：配置数据库

### 3.1 导入数据库结构
1. 在宝塔面板点击 "数据库"
2. 找到创建的数据库，点击 "管理" 进入phpMyAdmin
3. 选择数据库，点击 "导入"
4. 选择 `sql/init.sql` 文件并执行

或使用命令行：
```bash
mysql -u owrt_user -p owrt_management < /www/wwwroot/your-domain/sql/init.sql
```

### 3.2 验证数据库表
确认以下表已创建：
- `devices` - 设备信息表
- `device_commands` - 指令历史表
- `device_tokens` - 认证令牌表
- `command_templates` - 指令模板表
- `security_log` - 安全日志表
- `system_config` - 系统配置表

## 第四步：配置PHP

### 4.1 安装PHP扩展
在宝塔面板：
1. 点击 "软件商店" → 找到对应PHP版本 → "设置"
2. 安装以下扩展：
   - ✅ curl
   - ✅ json
   - ✅ mysqli
   - ✅ openssl
   - ✅ mbstring
   - ✅ xml

### 4.2 修改PHP配置
在PHP设置中修改：
```ini
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
date.timezone = Asia/Shanghai
```

## 第五步：配置NGINX

### 5.1 修改网站配置
在宝塔面板网站设置中，点击 "配置文件"，添加以下配置：

```nginx
# 在 server 块中添加以下配置

# API路径处理
location /api/ {
    try_files $uri $uri/ =404;
}

# 安全配置 - 禁止访问敏感目录
location ~ ^/(config|sql|scripts|classes|logs)/ {
    deny all;
    return 403;
}

# 禁止访问敏感文件
location ~ \.(conf|key|log|sql)$ {
    deny all;
    return 403;
}

# 禁止访问隐藏文件
location ~ /\. {
    deny all;
    return 403;
}

# 静态文件缓存
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary Accept-Encoding;
}

# Gzip压缩
gzip on;
gzip_vary on;
gzip_comp_level 6;
gzip_min_length 1000;
gzip_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/json
    application/javascript
    application/xml+rss
    application/atom+xml
    image/svg+xml;
```

### 5.2 重载NGINX配置
保存配置后，NGINX会自动重载

## 第六步：配置应用

### 6.1 修改数据库配置
编辑 `/www/wwwroot/your-domain/config/config.php`：

```php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'owrt_user');           // 你的数据库用户名
define('DB_PASS', 'your_secure_password'); // 你的数据库密码
define('DB_NAME', 'owrt_management');      // 你的数据库名
```

### 6.2 配置其他参数（可选）
根据需要修改其他配置项：
```php
// 系统配置
define('MAX_COMMAND_LENGTH', 1024);
define('MAX_OUTPUT_LENGTH', 1024 * 1024); // 1MB
define('HEARTBEAT_TIMEOUT', 300); // 5分钟
```

## 第七步：SSL配置（推荐）

### 7.1 申请SSL证书
在宝塔面板网站设置中：
1. 点击 "SSL" 标签
2. 选择 "Let's Encrypt" 申请免费证书
3. 或上传自己的SSL证书文件
4. 开启 "强制HTTPS"

### 7.2 配置安全头
如果使用HTTPS，在NGINX配置中添加：
```nginx
# HTTPS安全头
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Frame-Options DENY always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "1; mode=block" always;
```

## 第八步：设置定时任务

### 8.1 添加系统清理任务
在宝塔面板：
1. 点击 "计划任务"
2. 添加Shell脚本任务：
   - **任务名称**：OpenWrt系统清理
   - **执行周期**：每小时
   - **脚本内容**：
   ```bash
   /usr/bin/php /www/wwwroot/your-domain/scripts/cleanup.php
   ```

### 8.2 添加数据库备份任务
1. 任务类型选择 "备份数据库"
2. 执行周期：每天凌晨2点
3. 选择要备份的数据库：`owrt_management`

## 第九步：防火墙和安全

### 9.1 开放端口
在宝塔面板安全设置中确保开放：
- 80 端口（HTTP）
- 443 端口（HTTPS）

### 9.2 安全设置（可选）
- 启用宝塔面板的安全插件
- 设置管理员IP白名单
- 启用暴力破解防护

## 第十步：测试安装

### 10.1 访问测试页面
打开浏览器访问：`http://your-domain/api/test.php`

应该看到类似以下的JSON响应：
```json
{
    "status": "success",
    "message": "OpenWrt Management API Test",
    "overall_status": "OK"
}
```

### 10.2 访问管理界面
访问：`http://your-domain/web/`

应该看到OpenWrt管理系统的Web界面

### 10.3 检查日志
如果有问题，检查以下日志：
- NGINX错误日志：`/www/wwwlogs/your-domain.error.log`
- PHP错误日志：`/www/server/php/版本/var/log/php-fpm.log`
- 应用日志：`/www/wwwroot/your-domain/logs/`

## 第十一步：客户端配置

### 11.1 编译OpenWrt客户端
参考文档：`docs/BUILD.md`

### 11.2 配置客户端连接
在OpenWrt设备上修改 `/etc/owrt_client.conf`：
```
server_url=https://your-domain/api
heartbeat_interval=30
max_retries=3
```

## 故障排除

### 常见问题：

**1. 500内部服务器错误**
- 检查PHP错误日志
- 确认数据库连接配置正确
- 检查文件权限

**2. API无法访问**
- 检查NGINX配置
- 确认PHP-FPM正常运行
- 检查防火墙设置

**3. 数据库连接失败**
- 验证数据库用户名密码
- 确认数据库服务正常运行
- 检查数据库权限设置

**4. 权限错误**
```bash
# 重新设置权限
chown -R www:www /www/wwwroot/your-domain
chmod -R 755 /www/wwwroot/your-domain
chmod -R 777 /www/wwwroot/your-domain/config
chmod -R 777 /www/wwwroot/your-domain/logs
```

## 完成！

安装完成后，您将拥有：
- ✅ 功能完整的Web管理界面
- ✅ 安全的API接口
- ✅ 自动化清理任务
- ✅ 完整的日志记录
- ✅ SSL加密传输

下一步可以开始编译和部署OpenWrt客户端到您的路由器设备上。
