# OpenWrt 远程管理系统 - 项目结构

## 目录结构

```
Openwrt-manager/
├── docs/                           # 项目文档
│   ├── BUILD.md                   # 构建指南
│   ├── DEPLOYMENT.md              # 部署指南  
│   ├── MANUAL_INSTALL.md          # 手动安装指南
│   ├── PROJECT_SUMMARY.md         # 项目概览
│   ├── README.md                  # 项目说明
│   └── BAOTA_DEPLOYMENT.md        # 宝塔部署指南
│
├── owrt-remote-client/            # OpenWrt客户端 (主要客户端)
│   ├── files/                     # 配置文件
│   ├── include/                   # 头文件
│   ├── src/                       # 源码
│   ├── Makefile                   # 构建文件
│   ├── ENCRYPTION_UPGRADE.md      # 加密升级说明
│   └── LOG_MANAGEMENT.md          # 日志管理说明
│
├── server/                        # 服务器端
│   ├── api/                       # API接口
│   │   ├── auth.php              # 设备认证
│   │   ├── commands.php          # 指令管理
│   │   ├── devices.php           # 设备管理
│   │   ├── heartbeat.php         # 心跳检测
│   │   └── logs.php              # 日志管理
│   │
│   ├── classes/                   # 核心类
│   │   ├── Database.php          # 数据库类
│   │   ├── DeviceManager.php     # 设备管理类
│   │   └── CommandManager.php    # 指令管理类
│   │
│   ├── config/                    # 配置文件
│   │   └── database.php          # 数据库配置
│   │
│   ├── scripts/                   # 维护脚本
│   │   ├── cleanup.php           # 清理脚本
│   │   ├── daily_cleanup.php     # 每日清理
│   │   └── check_devices.php     # 设备检查
│   │
│   ├── sql/                       # 数据库脚本
│   │   └── schema.sql            # 数据库架构
│   │
│   ├── utils/                     # 工具类
│   │   └── CryptoUtils.php       # 加密工具
│   │
│   └── web/                       # Web界面
│       ├── index.html            # 主页面
│       ├── js/admin.js           # 管理脚本
│       └── css/                  # 样式文件
│
└── ENCRYPTION_DEPLOYMENT.md       # 加密部署说明
```

## 功能特性

### 已实现功能
- ✅ AES-256-CBC加密通信
- ✅ HMAC-SHA256完整性验证
- ✅ 设备自动注册和心跳检测
- ✅ Web管理界面
- ✅ 设备状态监控
- ✅ 加密状态显示
- ✅ 在线/离线状态管理 (120秒超时，适配60秒心跳)

### 技术栈
- **客户端**: C语言，OpenSSL加密，cURL网络通信
- **服务器**: PHP 7.4+，MySQL数据库
- **前端**: Bootstrap 5, JavaScript
- **加密**: AES-256-CBC + HMAC-SHA256

### 部署要求
- OpenWrt路由器 (支持USB存储或足够内存)
- PHP 7.4+ Web服务器
- MySQL 5.7+ 数据库
- 支持SSL的Web环境

### 配置说明
- 客户端心跳间隔: 60秒
- 服务器离线超时: 120秒 (2倍心跳间隔)
- 加密密钥: 服务器端固定种子生成
- 数据格式: Base64(HMAC + IV + Ciphertext)
