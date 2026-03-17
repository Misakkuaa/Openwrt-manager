# 时区问题修复说明

## 问题描述
设备的首次连接时间显示比北京时间慢了8个小时，这是由于系统没有正确设置时区导致的。

## 修复内容

### 1. DeviceManager类 (classes/DeviceManager.php)
```php
public function __construct() {
    // 设置时区为北京时间
    date_default_timezone_set('Asia/Shanghai');
    $this->db = new Database();
}
```

### 2. Database类 (classes/Database.php)
```php
private function connect() {
    // ... PDO连接代码 ...
    $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
    
    // 设置MySQL时区为北京时间
    $this->pdo->exec("SET time_zone = '+08:00'");
    
    // ... 其他代码 ...
}
```

### 3. 所有API文件
在每个API文件的开头添加：
```php
// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');
```

修复的文件：
- `api/devices.php`
- `api/device_detail.php`
- `api/heartbeat.php`
- `api/check_device_changes.php`

### 4. 新增测试文件
- `api/test_timezone.php` - PHP时区测试
- `api/test_db_timezone.php` - 数据库时区测试
- `test_timezone.html` - 时区测试页面

## 修复效果

### 修复前：
- 设备首次连接时间: 2024-12-04 01:30:00 (UTC时间)
- 北京时间实际是: 2024-12-04 09:30:00

### 修复后：
- 设备首次连接时间: 2024-12-04 09:30:00 (北京时间)
- 与实际北京时间一致

## 验证方法

### 1. 访问时区测试页面
```
http://your-server/test_timezone.html
```

### 2. 检查API响应
```bash
curl http://your-server/api/test_timezone.php
curl http://your-server/api/test_db_timezone.php
```

### 3. 查看设备列表
```
http://your-server/simple_device_list.html
```
检查设备的首次连接时间是否显示正确的北京时间。

## 技术说明

### PHP时区设置
- `date_default_timezone_set('Asia/Shanghai')` 设置PHP脚本的默认时区
- 影响所有的 `date()`, `time()`, `strtotime()` 等函数

### MySQL时区设置
- `SET time_zone = '+08:00'` 设置当前连接的时区
- 影响 `NOW()`, `CURRENT_TIMESTAMP` 等MySQL时间函数
- 确保数据库存储和读取的时间都是北京时间

### 一致性保证
1. **PHP层面**: 所有时间计算使用北京时区
2. **数据库层面**: MySQL时间函数使用北京时区
3. **前端显示**: JavaScript使用浏览器本地时区（通常也是北京时区）

## 注意事项

### 1. 已有数据的时区问题
如果数据库中已经有使用UTC时间存储的设备记录，可能需要数据迁移：

```sql
-- 将已有的UTC时间转换为北京时间（+8小时）
UPDATE devices 
SET first_seen = DATE_ADD(first_seen, INTERVAL 8 HOUR),
    last_seen = DATE_ADD(last_seen, INTERVAL 8 HOUR)
WHERE first_seen < '2024-12-04 08:00:00'; -- 假设这个时间之前的都是UTC时间
```

### 2. 服务器系统时区
建议也设置服务器系统时区为北京时间：
```bash
# Linux系统
sudo timedatectl set-timezone Asia/Shanghai

# 或者
sudo ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime
```

### 3. 前端时区处理
前端JavaScript中使用 `toLocaleString('zh-CN')` 确保时间显示符合中文习惯：
```javascript
const time = new Date(device.first_seen).toLocaleString('zh-CN');
```

## 故障排除

### 1. 时间仍然不正确
- 检查 `test_timezone.html` 页面的测试结果
- 确认PHP和MySQL时区都已正确设置
- 检查服务器系统时间是否正确

### 2. API返回时间格式异常
- 确认所有API文件都添加了时区设置
- 检查 `date_default_timezone_get()` 返回值
- 验证数据库连接是否成功设置时区

### 3. 前端显示时间不一致
- 检查浏览器的时区设置
- 确认JavaScript时间格式化函数
- 验证API返回的时间格式

## 更新历史
- 2024-12-04: 修复时区问题，统一使用北京时间
- 添加时区测试页面和API
- 更新所有相关代码文件
