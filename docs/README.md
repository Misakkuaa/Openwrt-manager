# OpenWrt 远程管理系统

这是一个完整的OpenWrt远程管理解决方案，包含OpenWrt端客户端和Ubuntu服务器端管理系统。

## 项目结构

```
Openwrt-manager/
├── openwrt-client/          # OpenWrt端C语言客户端
│   ├── src/                 # 源代码目录
│   ├── include/             # 头文件目录
│   ├── Makefile            # OpenWrt Makefile
│   └── Config.in           # menuconfig配置文件
├── server/                  # Ubuntu服务器端PHP程序
│   ├── api/                # API接口
│   ├── web/                # Web管理界面
│   ├── config/             # 配置文件
│   └── classes/            # PHP类文件
└── docs/                   # 文档目录
```

## 功能特性

### OpenWrt端
- 支持MIPS、ARM、X86等多架构
- HTTP协议与服务器通信
- 执行服务器指令并返回结果
- 唯一设备标识生成
- 自动连接服务器
- 安全鉴权机制

### 服务器端
- Web管理界面
- 远程指令管理
- 设备冲突检测
- 设备备注管理
- 安全通信保障

## 安装和部署

### 📖 详细安装文档

- **🔧 手动安装指南**: [docs/MANUAL_INSTALL.md](docs/MANUAL_INSTALL.md) - **推荐**
- **🐋 宝塔面板部署**: [docs/BAOTA_DEPLOYMENT.md](docs/BAOTA_DEPLOYMENT.md) 
- **🏗️ 编译指南**: [docs/BUILD.md](docs/BUILD.md)
- **� 编译故障排除**: [docs/BUILD_TROUBLESHOOT.md](docs/BUILD_TROUBLESHOOT.md) - **解决编译错误**
- **�📋 项目架构**: [docs/PROJECT_SUMMARY.md](docs/PROJECT_SUMMARY.md)

### ⚡ 快速开始

1. **服务器端部署**：按照 [手动安装指南](docs/MANUAL_INSTALL.md) 在宝塔面板中部署
2. **客户端编译**：参考 [编译指南](docs/BUILD.md) 编译OpenWrt客户端
3. **配置连接**：配置客户端连接服务器
4. **开始管理**：通过Web界面管理您的OpenWrt设备
