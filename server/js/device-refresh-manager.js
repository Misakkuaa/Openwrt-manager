/**
 * 设备列表智能刷新管理器
 * 只在有新设备连接或状态变化时才刷新界面
 */

class DeviceRefreshManager {
    constructor(options = {}) {
        this.checkInterval = options.checkInterval || 10000; // 10秒检查一次
        this.lastTimestamp = 0;
        this.lastOnlineCount = 0;
        this.lastTotalCount = 0;
        this.isChecking = false;
        this.intervalId = null;
        this.onRefreshCallback = options.onRefresh || null;
        this.onStatusChangeCallback = options.onStatusChange || null;
    }
    
    /**
     * 开始监控设备变化
     */
    start() {
        console.log('设备刷新管理器已启动');
        this.checkDeviceChanges(); // 立即检查一次
        this.intervalId = setInterval(() => {
            this.checkDeviceChanges();
        }, this.checkInterval);
    }
    
    /**
     * 停止监控
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            console.log('设备刷新管理器已停止');
        }
    }
    
    /**
     * 检查设备变化
     */
    async checkDeviceChanges() {
        if (this.isChecking) {
            return; // 防止重复检查
        }
        
        this.isChecking = true;
        
        try {
            const params = new URLSearchParams({
                last_timestamp: this.lastTimestamp,
                last_online_count: this.lastOnlineCount,
                last_total_count: this.lastTotalCount
            });
            
            const response = await fetch(`/api/check_device_changes.php?${params}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                // 更新已知状态
                this.lastTimestamp = data.current_status.latest_device_timestamp;
                this.lastOnlineCount = data.current_status.online_count;
                this.lastTotalCount = data.current_status.total_count;
                
                // 如果有变化需要刷新
                if (data.needs_refresh) {
                    console.log('检测到设备变化:', data.changes);
                    
                    if (this.onRefreshCallback) {
                        this.onRefreshCallback(data.devices || null, data.changes);
                    }
                }
                
                // 状态变化回调
                if (this.onStatusChangeCallback) {
                    this.onStatusChangeCallback(data.current_status);
                }
                
            } else {
                console.error('检查设备变化失败:', data.message);
            }
            
        } catch (error) {
            console.error('检查设备变化时发生错误:', error);
        } finally {
            this.isChecking = false;
        }
    }
    
    /**
     * 手动触发检查
     */
    async forceCheck() {
        await this.checkDeviceChanges();
    }
    
    /**
     * 重置状态（强制下次检查时刷新）
     */
    reset() {
        this.lastTimestamp = 0;
        this.lastOnlineCount = 0;
        this.lastTotalCount = 0;
    }
}

/**
 * 设备列表渲染器
 * 处理设备列表的显示和排序
 */
class DeviceListRenderer {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.devices = [];
    }
    
    /**
     * 更新设备列表
     */
    updateDevices(devices) {
        this.devices = devices;
        this.render();
    }
    
    /**
     * 渲染设备列表
     */
    render() {
        if (!this.container) {
            console.error('设备列表容器未找到');
            return;
        }
        
        // 按照新的排序规则排序：在线设备优先，然后按首次连接时间排序
        const sortedDevices = this.devices.sort((a, b) => {
            // 首先按在线状态排序
            const aOnline = this.isDeviceOnline(a);
            const bOnline = this.isDeviceOnline(b);
            
            if (aOnline !== bOnline) {
                return bOnline - aOnline; // 在线设备排前面
            }
            
            // 如果在线状态相同，按首次连接时间排序（最新的在前）
            const aFirstSeen = new Date(a.first_seen).getTime();
            const bFirstSeen = new Date(b.first_seen).getTime();
            
            return bFirstSeen - aFirstSeen;
        });
        
        // 渲染设备列表
        this.container.innerHTML = sortedDevices.map(device => this.renderDevice(device)).join('');
    }
    
    /**
     * 判断设备是否在线
     */
    isDeviceOnline(device) {
        if (device.is_online !== undefined) {
            return device.is_online === 1;
        }
        
        // 备用检查：基于最后见时间
        const lastSeen = new Date(device.last_seen).getTime();
        const now = Date.now();
        const timeDiff = (now - lastSeen) / 1000; // 秒
        
        return timeDiff <= 120; // 2分钟内算在线
    }
    
    /**
     * 渲染单个设备
     */
    renderDevice(device) {
        const isOnline = this.isDeviceOnline(device);
        const statusClass = isOnline ? 'online' : 'offline';
        const statusText = isOnline ? '在线' : '离线';
        const statusIcon = isOnline ? '🟢' : '🔴';
        
        const firstSeen = new Date(device.first_seen).toLocaleString('zh-CN');
        const lastSeen = new Date(device.last_seen).toLocaleString('zh-CN');
        
        // 计算简单的在线时长显示
        const lastSeenTime = new Date(device.last_seen).getTime();
        const firstSeenTime = new Date(device.first_seen).getTime();
        const now = Date.now();
        
        let uptimeDisplay = '';
        if (isOnline) {
            // 简化计算：从首次连接到现在的时长
            const totalSeconds = Math.floor((now - firstSeenTime) / 1000);
            uptimeDisplay = this.formatSimpleDuration(totalSeconds);
        } else {
            const offlineSeconds = Math.floor((now - lastSeenTime) / 1000);
            uptimeDisplay = `离线 ${this.formatSimpleDuration(offlineSeconds)}`;
        }
        
        return `
            <div class="device-card ${statusClass}" data-device-id="${device.device_id}" onclick="openDeviceDetail('${device.device_id}')" style="cursor: pointer;">
                <div class="device-header">
                    <span class="device-status">${statusIcon} ${statusText}</span>
                    <span class="device-id">${device.device_id}</span>
                </div>
                <div class="device-info">
                    <div class="device-time">
                        <small>首次连接: ${firstSeen}</small><br>
                        <small>最近活动: ${lastSeen}</small><br>
                        <small><strong>⏱️ ${uptimeDisplay}</strong></small>
                    </div>
                    ${device.system_info ? this.renderSystemInfo(device.system_info) : ''}
                    <div class="device-actions" onclick="event.stopPropagation();" style="margin-top: 10px;">
                        <button class="detail-button" onclick="openDeviceDetail('${device.device_id}')" style="background: #007bff; color: white; border: none; padding: 4px 8px; border-radius: 3px; font-size: 12px; cursor: pointer;">
                            查看详情
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    /**
     * 格式化简单时长显示
     */
    formatSimpleDuration(seconds) {
        if (seconds < 60) {
            return `${seconds}秒`;
        } else if (seconds < 3600) {
            return `${Math.floor(seconds / 60)}分钟`;
        } else if (seconds < 86400) {
            return `${Math.floor(seconds / 3600)}小时`;
        } else {
            return `${Math.floor(seconds / 86400)}天`;
        }
    }
    
    /**
     * 渲染系统信息
     */
    renderSystemInfo(systemInfo) {
        try {
            const info = typeof systemInfo === 'string' ? JSON.parse(systemInfo) : systemInfo;
            return `
                <div class="system-info">
                    ${info.model ? `<div>型号: ${info.model}</div>` : ''}
                    ${info.version ? `<div>版本: ${info.version}</div>` : ''}
                    ${info.internal_ip ? `<div>内网IP: ${info.internal_ip}</div>` : ''}
                </div>
            `;
        } catch (e) {
            return '';
        }
    }
}

// 使用示例和初始化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化设备列表渲染器
    const deviceRenderer = new DeviceListRenderer('device-list');
    
    // 初始化刷新管理器
    const refreshManager = new DeviceRefreshManager({
        checkInterval: 10000, // 10秒检查一次
        onRefresh: (devices, changes) => {
            console.log('设备列表需要刷新，原因:', changes);
            if (devices) {
                deviceRenderer.updateDevices(devices);
            } else {
                // 如果没有返回设备列表，手动加载
                loadDeviceList();
            }
            
            // 显示刷新通知
            showRefreshNotification(changes);
        },
        onStatusChange: (status) => {
            // 更新页面上的统计信息
            updateDeviceStats(status);
        }
    });
    
    // 启动监控
    refreshManager.start();
    
    // 将管理器暴露到全局作用域
    window.refreshManager = refreshManager;
    window.deviceRenderer = deviceRenderer;
    
    // 页面卸载时停止监控
    window.addEventListener('beforeunload', () => {
        refreshManager.stop();
    });
    
    // 初始加载设备列表
    loadDeviceList();
    
    /**
     * 加载设备列表
     */
    async function loadDeviceList() {
        try {
            const response = await fetch('/api/devices.php');
            const data = await response.json();
            if (data.status === 'success' && data.devices) {
                deviceRenderer.updateDevices(data.devices);
                // 同时更新统计信息
                if (data.stats) {
                    updateDeviceStats(data.stats);
                }
            } else {
                console.error('加载设备列表失败:', data.message || '未知错误');
            }
        } catch (error) {
            console.error('加载设备列表失败:', error);
        }
    }
    
    /**
     * 显示刷新通知
     */
    function showRefreshNotification(changes) {
        let message = '';
        if (changes.includes('new_device')) {
            message = '🎉 发现新设备连接';
        } else if (changes.includes('online_count_changed')) {
            message = '📶 设备在线状态发生变化';
        } else if (changes.includes('total_count_changed')) {
            message = '📊 设备总数发生变化';
        }
        
        if (message && window.showRefreshNotification) {
            window.showRefreshNotification(changes);
        } else if (message) {
            console.log(message);
        }
    }
    
    /**
     * 更新设备统计信息
     */
    function updateDeviceStats(status) {
        // 更新页面上的统计数字
        const onlineCountEl = document.getElementById('online-count');
        const totalCountEl = document.getElementById('total-count');
        
        if (onlineCountEl) {
            onlineCountEl.textContent = status.online_count || 0;
        }
        if (totalCountEl) {
            totalCountEl.textContent = status.total_count || 0;
        }
        
        // 如果有全局的更新函数，也调用它
        if (window.updateDeviceStats) {
            window.updateDeviceStats(status);
        }
    }
});

/**
 * 打开设备详情页面
 */
function openDeviceDetail(deviceId) {
    window.open(`/device_detail.html?device_id=${encodeURIComponent(deviceId)}`, '_blank');
}
