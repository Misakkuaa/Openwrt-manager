#!/bin/bash

# 自动清理设置脚本
# 用于设置每日清理的 cron 任务

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVER_DIR="$(dirname "$SCRIPT_DIR")"
CLEANUP_SCRIPT="$SCRIPT_DIR/daily_cleanup.php"

echo "OpenWrt Management Server - 设置每日清理任务"
echo "============================================"

# 检查 PHP 是否可用
if ! command -v php &> /dev/null; then
    echo "错误: PHP 未安装或不在 PATH 中"
    exit 1
fi

# 检查清理脚本是否存在
if [ ! -f "$CLEANUP_SCRIPT" ]; then
    echo "错误: 清理脚本不存在: $CLEANUP_SCRIPT"
    exit 1
fi

# 测试清理脚本
echo "测试清理脚本..."
php "$CLEANUP_SCRIPT"
if [ $? -ne 0 ]; then
    echo "错误: 清理脚本测试失败"
    exit 1
fi

echo "清理脚本测试成功"

# 添加到 crontab
CRON_JOB="0 2 * * * /usr/bin/php $CLEANUP_SCRIPT >> /var/log/owrt_cleanup.log 2>&1"

# 检查是否已经存在相同的任务
if crontab -l 2>/dev/null | grep -q "$CLEANUP_SCRIPT"; then
    echo "Cron 任务已存在，跳过添加"
else
    # 添加新的 cron 任务
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    if [ $? -eq 0 ]; then
        echo "成功添加 Cron 任务: 每天凌晨 2:00 执行清理"
        echo "日志文件: /var/log/owrt_cleanup.log"
    else
        echo "错误: 添加 Cron 任务失败"
        exit 1
    fi
fi

# 创建日志目录（如果不存在）
sudo touch /var/log/owrt_cleanup.log
sudo chmod 644 /var/log/owrt_cleanup.log

echo ""
echo "设置完成！"
echo "- 每日清理时间: 每天凌晨 2:00"
echo "- 清理内容: 删除1天前的 heartbeat_failed 日志"
echo "- 日志位置: /var/log/owrt_cleanup.log"
echo ""
echo "查看当前 cron 任务: crontab -l"
echo "手动执行清理: php $CLEANUP_SCRIPT"
echo "查看清理日志: tail -f /var/log/owrt_cleanup.log"
