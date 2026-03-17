# OpenWrt客户端编译指南

## 环境准备

### 1. 安装OpenWrt SDK

根据您的目标设备架构下载对应的SDK：

```bash
# 示例：下载支持ar71xx架构的SDK
wget https://downloads.openwrt.org/releases/21.02.3/targets/ar71xx/generic/openwrt-sdk-21.02.3-ar71xx-generic_gcc-8.4.0_musl.Linux-x86_64.tar.xz

# 解压SDK
tar -xf openwrt-sdk-21.02.3-ar71xx-generic_gcc-8.4.0_musl.Linux-x86_64.tar.xz
cd openwrt-sdk-21.02.3-ar71xx-generic_gcc-8.4.0_musl.Linux-x86_64
```

### 2. 安装依赖包

```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install build-essential ccache ecj fastjar file g++ gawk \
gettext git java-propose-classpath libelf-dev libncurses5-dev \
libncursesw5-dev libssl-dev python2.7-dev python3 unzip wget \
python3-distutils python3-setuptools rsync subversion swig time \
xsltproc zlib1g-dev

# CentOS/RHEL
sudo yum groupinstall "Development Tools"
sudo yum install ncurses-devel zlib-devel openssl-devel
```

## 编译步骤

### 1. 复制源码到SDK

```bash
# 将客户端源码复制到SDK的package目录
cp -r /path/to/openwrt-client $SDK_PATH/package/owrt-remote-client
```

### 2. 更新feeds

```bash
./scripts/feeds update -a
./scripts/feeds install -a
```

### 3. 配置编译选项

```bash
make menuconfig
```

在菜单中找到：
```
Utilities → owrt-remote-client
```

选中要编译的选项。

### 4. 编译

```bash
# 编译单个包
make package/owrt-remote-client/compile

# 或者编译所有包
make
```

### 5. 查找编译结果

编译完成后，在以下目录查找生成的ipk包：
```bash
find bin/ -name "*owrt-remote-client*"
```

## 交叉编译（手动编译）

如果您需要手动交叉编译：

### 1. 设置交叉编译环境

```bash
# 设置交叉编译器路径
export PATH=$SDK_PATH/staging_dir/toolchain-mips_24kc_gcc-8.4.0_musl/bin:$PATH
export STAGING_DIR=$SDK_PATH/staging_dir
export CC=mips-openwrt-linux-gcc
export CXX=mips-openwrt-linux-g++
export AR=mips-openwrt-linux-ar
export STRIP=mips-openwrt-linux-strip
```

### 2. 编译依赖库

```bash
# 编译libcurl
cd $SDK_PATH/build_dir/target-mips_24kc_musl/libcurl-*
make && make install

# 编译json-c
cd $SDK_PATH/build_dir/target-mips_24kc_musl/json-c-*
make && make install
```

### 3. 编译客户端

```bash
cd openwrt-client/src
make CC=$CC CFLAGS="-I$STAGING_DIR/usr/include" LDFLAGS="-L$STAGING_DIR/usr/lib"
```

## 多架构编译

### 支持的架构

- **MIPS**: ar71xx, ath79, ramips
- **ARM**: armv7, armv8 (aarch64)
- **x86**: x86_64, i386

### 批量编译脚本

创建 `build_all.sh`：

```bash
#!/bin/bash

ARCHITECTURES=(
    "ar71xx-generic"
    "ath79-generic" 
    "ramips-mt7621"
    "armvirt-64"
    "x86-64"
)

for arch in "${ARCHITECTURES[@]}"; do
    echo "Building for $arch..."
    
    # 下载对应SDK
    SDK_URL="https://downloads.openwrt.org/releases/21.02.3/targets/${arch}/openwrt-sdk-*"
    wget $SDK_URL
    
    # 解压并编译
    tar -xf openwrt-sdk-*${arch}*.tar.xz
    cd openwrt-sdk-*${arch}*
    
    # 复制源码
    cp -r ../openwrt-client package/
    
    # 编译
    make package/owrt-remote-client/compile
    
    # 复制结果
    mkdir -p ../releases/$arch
    cp bin/packages/*/base/owrt-remote-client*.ipk ../releases/$arch/
    
    cd ..
    rm -rf openwrt-sdk-*${arch}*
done
```

## 包安装

### 使用opkg安装

```bash
# 复制到路由器
scp owrt-remote-client_1.0-1_mips_24kc.ipk root@192.168.1.1:/tmp/

# 在路由器上安装
ssh root@192.168.1.1
opkg install /tmp/owrt-remote-client_1.0-1_mips_24kc.ipk
```

### 集成到固件

如果要将客户端集成到OpenWrt固件中：

1. 将源码放入OpenWrt源码树的 `package/utils/` 目录
2. 运行 `make menuconfig` 选择包
3. 编译完整固件：`make`

## 调试编译

### 启用调试信息

在 `Config.in` 中启用调试选项：

```
config OWRT_CLIENT_ENABLE_DEBUG
    bool "Enable debug logging"
    default y
```

### 编译调试版本

```bash
make package/owrt-remote-client/compile CONFIG_OWRT_CLIENT_ENABLE_DEBUG=y
```

### 查看编译日志

```bash
make package/owrt-remote-client/compile V=s
```

## 常见问题

### 1. 找不到头文件

确保在 `Makefile` 中正确设置了包含路径：

```makefile
TARGET_CFLAGS += -I$(STAGING_DIR)/usr/include/json-c
```

### 2. 链接错误

检查库的依赖关系在 `Makefile` 中的 `DEPENDS` 字段：

```makefile
DEPENDS:=+libcurl +libjson-c +libopenssl
```

### 3. 运行时错误

检查库文件是否正确安装：

```bash
opkg list-installed | grep -E "(curl|json|ssl)"
```

## 性能优化

### 编译优化

```makefile
TARGET_CFLAGS += -Os -ffunction-sections -fdata-sections
TARGET_LDFLAGS += -Wl,--gc-sections
```

### 减小二进制大小

```bash
# 使用strip去除调试信息
mips-openwrt-linux-strip owrt_client

# 使用UPX压缩（可选）
upx --best owrt_client
```
