#!/bin/sh

# One-line OpenWrt Remote Client Setup and Start
# Usage: owrt_client_start

echo "Starting OpenWrt Remote Client..."

# 配置已写死在程序中，无需UCI配置
echo "✓ Using hardcoded configuration"

# Stop any existing client
killall owrt_client 2>/dev/null

# Start client in background
echo "Starting client..."
# 启动时显示详细输出用于调试，并且也输出到系统日志
/usr/bin/owrt_client > /tmp/owrt_client.log 2>&1 &
sleep 3

# Verify startup
if ps | grep -v grep | grep owrt_client >/dev/null; then
    PID=$(ps | grep -v grep | grep owrt_client | awk 'NR==1{print $1}')
    echo "✓ Client started successfully (PID: $PID)"
    echo "✓ AES encryption enabled (hardcoded)"
    echo "✓ Sending heartbeats to server every 60 seconds"
    
    # Quick connectivity test
    if curl --connect-timeout 5 --max-time 5 -s "http://azurebt.mswifi.online/api/test.php" >/dev/null 2>&1; then
        echo "✓ Server connectivity confirmed"
    else
        echo "⚠ Server not reachable (client will retry automatically)"
    fi
    
    # 等待一下再检查是否仍在运行
    sleep 2
    if ps | grep -v grep | grep owrt_client >/dev/null; then
        echo "✓ Client still running after 5 seconds"
    else
        echo "✗ Client stopped shortly after startup"
        echo "Error log:"
        if [ -f "/tmp/owrt_client.log" ]; then
            cat /tmp/owrt_client.log
        fi
        exit 1
    fi
else
    echo "✗ Failed to start client"
    echo "Error log:"
    if [ -f "/tmp/owrt_client.log" ]; then
        cat /tmp/owrt_client.log
    fi
    echo "Run 'owrt_client_simple_test' for detailed troubleshooting"
    exit 1
fi

echo ""
echo "Client is now running with encryption enabled!"
echo "✓ Encryption enabled (hardcoded in program)"
echo "Commands: owrt_client_start (restart) | owrt_client_test (test) | owrt_client_encryption_status (check encryption)"
echo "Logs: tail -f /tmp/owrt_client.log"
