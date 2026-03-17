/**
 * OpenWrt 管理系统前端 JavaScript - 现代化版本
 */

// 全局变量
let currentTab = 'devices';
let devices = [];
let commands = [];
let templates = [];
let selectedDevices = new Set();
let isBatchMode = false;
let currentPage = 0;
let devicesPerPage = 24;
let filteredDevices = [];
let searchTimeout = null;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

// 初始化应用
function initializeApp() {
    loadDevices();
    loadTemplates();
    loadCommands();
    
    // 每30秒自动刷新设备状态
    setInterval(loadDevices, 30000);
    
    // 每10秒检查冲突
    setInterval(checkConflicts, 10000);
    
    // 初始化图表
    initializeCharts();
    
    // 设置键盘快捷键
    setupKeyboardShortcuts();
    
    // 设置监控自动刷新开关事件
    setupMonitoringAutoRefresh();
}

// 设置监控自动刷新
function setupMonitoringAutoRefresh() {
    const autoRefreshSwitch = document.getElementById('auto-refresh');
    if (autoRefreshSwitch) {
        autoRefreshSwitch.addEventListener('change', function() {
            if (currentTab === 'monitoring') {
                if (this.checked) {
                    startMonitoringAutoRefresh();
                } else {
                    stopMonitoringAutoRefresh();
                }
            }
        });
    }
}

// 标签页切换 - 增强版
function showTab(tabName) {
    // 隐藏所有标签页
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
    });
    
    // 移除所有活动状态
    document.querySelectorAll('.list-group-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // 显示目标标签页
    document.getElementById(tabName + '-tab').style.display = 'block';
    
    // 设置活动状态
    event.target.classList.add('active');
    
    currentTab = tabName;
    
    // 根据标签页加载相应数据
    switch(tabName) {
        case 'devices':
            stopMonitoringAutoRefresh();
            loadDevices();
            break;
        case 'commands':
            stopMonitoringAutoRefresh();
            loadCommands();
            break;
        case 'templates':
            stopMonitoringAutoRefresh();
            loadTemplates();
            break;
        case 'monitoring':
            loadMonitoring();
            startMonitoringAutoRefresh();
            break;
        case 'logs':
            stopMonitoringAutoRefresh();
            loadLogs();
            break;
        case 'alerts':
            stopMonitoringAutoRefresh();
            checkConflicts();
            break;
        case 'maintenance':
            stopMonitoringAutoRefresh();
            loadSystemStats();
            break;
        default:
            stopMonitoringAutoRefresh();
    }
    
    // 更新URL（不刷新页面）
    history.pushState({tab: tabName}, '', `#${tabName}`);
}

// 加载设备列表 - 增强版
async function loadDevices() {
    try {
        showLoadingSkeletons();
        
        const response = await fetch('api/device_status.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            devices = data.devices;
            filteredDevices = [...devices];
            updateDeviceStats();
            renderDevices();
            updatePagination();
            
            // 加载筛选选项
            await loadFilterOptions();
        } else {
            showError('Failed to load devices: ' + data.message);
        }
    } catch (error) {
        console.error('Error loading devices:', error);
        showError('网络连接错误，无法加载设备列表');
    }
}

// 更新设备统计
function updateDeviceStats() {
    const totalDevices = devices.length;
    const onlineDevices = devices.filter(d => d.status === 'online').length;
    const offlineDevices = totalDevices - onlineDevices;
    
    // 更新导航栏
    document.getElementById('online-count').textContent = onlineDevices;
    document.getElementById('total-count').textContent = totalDevices;
    document.getElementById('sidebar-device-count').textContent = totalDevices;
    
    // 更新统计卡片
    document.getElementById('stats-total-devices').textContent = totalDevices;
    document.getElementById('stats-online-devices').textContent = onlineDevices;
    document.getElementById('stats-offline-devices').textContent = offlineDevices;
    document.getElementById('stats-last-update').textContent = new Date().toLocaleTimeString('zh-CN');
}

// 渲染设备 - 新版本支持分页和批量选择
function renderDevices() {
    const container = document.getElementById('devices-container');
    const startIndex = currentPage * devicesPerPage;
    const endIndex = Math.min(startIndex + devicesPerPage, filteredDevices.length);
    const pageDevices = filteredDevices.slice(startIndex, endIndex);
    
    container.innerHTML = '';
    
    if (pageDevices.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <h4 class="text-muted mt-3">暂无设备</h4>
                <p class="text-muted">请检查搜索条件或等待设备上线</p>
            </div>
        `;
        return;
    }
    
    pageDevices.forEach(device => {
        const isSelected = selectedDevices.has(device.device_id);
        const deviceCard = createDeviceCard(device, isSelected);
        container.appendChild(deviceCard);
    });
    
    updateDevicesInfo();
}

// 创建设备卡片
function createDeviceCard(device, isSelected = false) {
    const cardDiv = document.createElement('div');
    cardDiv.className = `device-card ${isSelected ? 'selected' : ''}`;
    cardDiv.setAttribute('data-device-id', device.device_id);
    
    const statusClass = device.status === 'online' ? 'device-status-online' : 'device-status-offline';
    const statusIcon = device.status === 'online' ? 'bi-check-circle' : 'bi-x-circle';
    const lastSeen = device.status === 'online' ? '现在在线' : formatRelativeTime(device.last_seen);
    
    cardDiv.innerHTML = `
        <div class="card-header ${statusClass} d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                ${isBatchMode ? `
                    <input type="checkbox" class="form-check-input me-2" ${isSelected ? 'checked' : ''} 
                           onchange="toggleDeviceSelection('${device.device_id}', this.checked)">
                ` : ''}
                <i class="bi ${statusIcon} me-2"></i>
                <span class="fw-bold">${device.status.toUpperCase()}</span>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" onclick="showDeviceDetail('${device.device_id}')">
                        <i class="bi bi-info-circle me-2"></i>查看详情
                    </a></li>
                    <li><a class="dropdown-item" onclick="quickCommand('${device.device_id}')" ${device.status !== 'online' ? 'class="disabled"' : ''}>
                        <i class="bi bi-terminal me-2"></i>执行指令
                    </a></li>
                    <li><a class="dropdown-item" onclick="editDeviceNotes('${device.device_id}')">
                        <i class="bi bi-pencil me-2"></i>编辑备注
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" onclick="removeDevice('${device.device_id}')">
                        <i class="bi bi-trash me-2"></i>移除设备
                    </a></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <h6 class="card-title text-truncate" title="${device.device_id}">
                <i class="bi bi-router me-2"></i>${device.device_id}
            </h6>
            <div class="mb-3">
                <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>外网IP:</span>
                    <span class="fw-semibold">${device.external_ip || device.ip_address || 'N/A'}</span>
                </div>
                <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>内网IP:</span>
                    <span class="fw-semibold">${device.internal_ip || 'N/A'}</span>
                </div>
                <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>系统:</span>
                    <span class="fw-semibold">${parseSystemInfo(device.system_info) || 'N/A'}</span>
                </div>
                <div class="d-flex justify-content-between text-muted small mb-1">
                    <span>加密:</span>
                    <span class="fw-semibold">${getEncryptionStatusBadge(device)}</span>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span>最后活动:</span>
                    <span class="fw-semibold">${lastSeen}</span>
                </div>
            </div>
            ${device.notes ? `
                <div class="alert alert-info p-2 small mb-3">
                    <i class="bi bi-sticky me-1"></i> ${device.notes}
                </div>
            ` : ''}
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary flex-fill" onclick="showDeviceDetail('${device.device_id}')">
                    <i class="bi bi-info-circle me-1"></i>详情
                </button>
                <button class="btn btn-sm btn-outline-success flex-fill" onclick="quickCommand('${device.device_id}')" ${device.status !== 'online' ? 'disabled' : ''}>
                    <i class="bi bi-terminal me-1"></i>指令
                </button>
            </div>
        </div>
        <div class="card-footer bg-transparent small text-muted">
            <div class="d-flex justify-content-between">
                <span><i class="bi bi-clock me-1"></i>首次连接: ${formatDateTime(device.first_seen)}</span>
                <span class="status-indicator ${device.status}"></span>
            </div>
        </div>
    `;
    
    return cardDiv;
}

// 搜索和过滤功能
function handleSearch(event) {
    if (event.key === 'Enter') {
        performSearch();
    } else {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(performSearch, 300);
    }
}

function performSearch() {
    const query = document.getElementById('device-search').value.trim().toLowerCase();
    const deviceIdFilter = document.getElementById('device-id-filter').value;
    const statusFilter = document.getElementById('status-filter').value;
    const modelFilter = document.getElementById('model-filter').value;
    const versionFilter = document.getElementById('version-filter').value;
    
    filteredDevices = devices.filter(device => {
        const systemInfo = parseSystemInfo(device.system_info);
        
        // 设备ID精确筛选
        const matchesDeviceId = !deviceIdFilter || device.device_id === deviceIdFilter;
        
        // 通用搜索（排除设备ID，因为有专门的筛选器）
        const matchesSearch = !query || 
            (device.ip_address && device.ip_address.includes(query)) ||
            (device.external_ip && device.external_ip.includes(query)) ||
            (device.internal_ip && device.internal_ip.includes(query)) ||
            (device.notes && device.notes.toLowerCase().includes(query)) ||
            (systemInfo && systemInfo.toLowerCase().includes(query));
        
        const matchesStatus = !statusFilter || device.status === statusFilter;
        
        // 型号筛选
        const deviceModel = getDeviceModel(device);
        const matchesModel = !modelFilter || deviceModel === modelFilter;
        
        // 版本筛选
        const deviceVersion = getDeviceVersion(device);
        const matchesVersion = !versionFilter || deviceVersion === versionFilter;
        
        return matchesDeviceId && matchesSearch && matchesStatus && matchesModel && matchesVersion;
    });
    
    updateFilterSummary();
    currentPage = 0;
    renderDevices();
    updatePagination();
}

// 快速过滤
function setQuickFilter(filter) {
    // 更新过滤器按钮状态
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    document.querySelector(`[data-filter="${filter}"]`).classList.add('active');
    
    // 应用过滤器
    const now = new Date();
    const oneHourAgo = new Date(now.getTime() - 60 * 60 * 1000);
    
    switch(filter) {
        case 'all':
            filteredDevices = [...devices];
            document.getElementById('status-filter').value = '';
            break;
        case 'online':
            filteredDevices = devices.filter(d => d.status === 'online');
            document.getElementById('status-filter').value = 'online';
            break;
        case 'offline':
            filteredDevices = devices.filter(d => d.status === 'offline');
            document.getElementById('status-filter').value = 'offline';
            break;
        case 'recent':
            filteredDevices = devices.filter(d => new Date(d.last_seen) > oneHourAgo);
            document.getElementById('status-filter').value = '';
            break;
    }
    
    currentPage = 0;
    renderDevices();
    updatePagination();
}

// 应用过滤器
function applyFilters() {
    performSearch();
}

// 清除搜索
function clearSearch() {
    document.getElementById('device-search').value = '';
    performSearch();
}

// 清除所有筛选器
function clearAllFilters() {
    document.getElementById('device-id-filter').value = '';
    document.getElementById('device-search').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('model-filter').value = '';
    document.getElementById('version-filter').value = '';
    setQuickFilter('all');
    performSearch();
}

// 批量选择功能
function toggleBatchMode() {
    isBatchMode = !isBatchMode;
    selectedDevices.clear();
    
    const batchActions = document.getElementById('batch-actions');
    if (isBatchMode) {
        batchActions.classList.add('visible');
    } else {
        batchActions.classList.remove('visible');
    }
    
    renderDevices();
    updateBatchActionsVisibility();
}

function toggleDeviceSelection(deviceId, isSelected) {
    if (isSelected) {
        selectedDevices.add(deviceId);
    } else {
        selectedDevices.delete(deviceId);
    }
    
    updateBatchActionsVisibility();
    updateDeviceCardSelection(deviceId, isSelected);
}

function updateDeviceCardSelection(deviceId, isSelected) {
    const card = document.querySelector(`[data-device-id="${deviceId}"]`);
    if (card) {
        if (isSelected) {
            card.classList.add('selected');
        } else {
            card.classList.remove('selected');
        }
    }
}

function selectAllDevices() {
    const pageDevices = filteredDevices.slice(currentPage * devicesPerPage, (currentPage + 1) * devicesPerPage);
    pageDevices.forEach(device => {
        selectedDevices.add(device.device_id);
    });
    renderDevices();
    updateBatchActionsVisibility();
}

function clearSelection() {
    selectedDevices.clear();
    renderDevices();
    updateBatchActionsVisibility();
}

function updateBatchActionsVisibility() {
    const count = selectedDevices.size;
    const batchActions = document.getElementById('batch-actions');
    const selectedCountElement = document.getElementById('selected-count');
    
    if (selectedCountElement) {
        selectedCountElement.textContent = count;
    }
    
    if (count > 0 && isBatchMode) {
        batchActions.classList.add('visible');
    } else if (!isBatchMode) {
        batchActions.classList.remove('visible');
    }
}

// 分页功能
function updatePagination() {
    const totalPages = Math.ceil(filteredDevices.length / devicesPerPage);
    const pagination = document.getElementById('devices-pagination');
    
    let paginationHtml = '<ul class="pagination mb-0">';
    
    // 上一页
    if (currentPage > 0) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage - 1})">上一页</a></li>`;
    } else {
        paginationHtml += `<li class="page-item disabled"><span class="page-link">上一页</span></li>`;
    }
    
    // 页码
    const startPage = Math.max(0, currentPage - 2);
    const endPage = Math.min(totalPages - 1, currentPage + 2);
    
    if (startPage > 0) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(0)">1</a></li>`;
        if (startPage > 1) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            paginationHtml += `<li class="page-item active"><span class="page-link">${i + 1}</span></li>`;
        } else {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${i})">${i + 1}</a></li>`;
        }
    }
    
    if (endPage < totalPages - 1) {
        if (endPage < totalPages - 2) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${totalPages - 1})">${totalPages}</a></li>`;
    }
    
    // 下一页
    if (currentPage < totalPages - 1) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changePage(${currentPage + 1})">下一页</a></li>`;
    } else {
        paginationHtml += `<li class="page-item disabled"><span class="page-link">下一页</span></li>`;
    }
    
    paginationHtml += '</ul>';
    pagination.innerHTML = paginationHtml;
}

function changePage(page) {
    currentPage = page;
    renderDevices();
    updatePagination();
}

function changeDevicesPerPage() {
    devicesPerPage = parseInt(document.getElementById('devices-per-page').value);
    currentPage = 0;
    renderDevices();
    updatePagination();
}

function updateDevicesInfo() {
    const start = currentPage * devicesPerPage + 1;
    const end = Math.min((currentPage + 1) * devicesPerPage, filteredDevices.length);
    const total = filteredDevices.length;
    
    document.getElementById('devices-info').textContent = `显示 ${start}-${end}，共 ${total} 个设备`;
}

// 工具函数
function formatRelativeTime(dateStr) {
    if (!dateStr) return 'N/A';
    
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffMins < 1) return '刚刚';
    if (diffMins < 60) return `${diffMins}分钟前`;
    if (diffHours < 24) return `${diffHours}小时前`;
    return `${diffDays}天前`;
}

function showLoadingSkeletons() {
    const container = document.getElementById('devices-container');
    container.innerHTML = '';
    
    for (let i = 0; i < 6; i++) {
        const skeletonCard = document.createElement('div');
        skeletonCard.className = 'device-card';
        skeletonCard.innerHTML = '<div class="loading-skeleton" style="height: 200px;"></div>';
        container.appendChild(skeletonCard);
    }
}

function showError(message) {
    // 可以实现更友好的错误提示
    console.error(message);
}

// 键盘快捷键
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F: 聚焦搜索框
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            document.getElementById('device-search').focus();
        }
        
        // Escape: 清除搜索和选择
        if (e.key === 'Escape') {
            clearSearch();
            clearSelection();
        }
        
        // Ctrl/Cmd + A: 全选设备（在批量模式下）
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && isBatchMode) {
            e.preventDefault();
            selectAllDevices();
        }
    });
}

// 图表初始化
function initializeCharts() {
    // 初始化图表变量
    window.modelChart = null;
    window.versionChart = null;
    window.statusChart = null;
    console.log('Charts initialized');
}

// 创建统计图表
function createStatsCharts(stats) {
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            }
        }
    };
    
    // 销毁现有图表
    if (window.modelChart) {
        window.modelChart.destroy();
    }
    if (window.versionChart) {
        window.versionChart.destroy();
    }
    if (window.statusChart) {
        window.statusChart.destroy();
    }
    
    // 型号分布图表
    const modelCtx = document.getElementById('modelChart').getContext('2d');
    const modelColors = generateColors(stats.models.length);
    window.modelChart = new Chart(modelCtx, {
        type: 'doughnut',
        data: {
            labels: stats.models.map(item => item.model),
            datasets: [{
                data: stats.models.map(item => item.count),
                backgroundColor: modelColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: chartOptions
    });
    
    // 版本分布图表
    const versionCtx = document.getElementById('versionChart').getContext('2d');
    const versionColors = generateColors(stats.versions.length);
    window.versionChart = new Chart(versionCtx, {
        type: 'doughnut',
        data: {
            labels: stats.versions.map(item => item.version),
            datasets: [{
                data: stats.versions.map(item => item.count),
                backgroundColor: versionColors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: chartOptions
    });
    
    // 状态分布图表
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    window.statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: stats.statuses.map(item => item.status === 'online' ? '在线' : '离线'),
            datasets: [{
                data: stats.statuses.map(item => item.count),
                backgroundColor: stats.statuses.map(item => 
                    item.status === 'online' ? '#10b981' : '#f59e0b'
                ),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: chartOptions
    });
}

// 生成图表颜色
function generateColors(count) {
    const colors = [
        '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1',
        '#14b8a6', '#fbbf24', '#f87171', '#a78bfa', '#34d399'
    ];
    
    const result = [];
    for (let i = 0; i < count; i++) {
        result.push(colors[i % colors.length]);
    }
    return result;
}

// 实时监控功能
function loadMonitoring() {
    console.log('Loading monitoring data...');
    loadMonitoringStats();
    loadActivityStream();
}

async function loadMonitoringStats() {
    try {
        const response = await fetch('api/stats.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            // 更新实时监控统计卡片
            document.getElementById('monitor-heartbeats').textContent = data.today_heartbeats || 0;
            document.getElementById('monitor-commands').textContent = data.today_commands || 0;
            document.getElementById('monitor-connections').textContent = data.active_connections || 0;
            document.getElementById('monitor-avg-response').textContent = (data.avg_response_time || 0) + 'ms';
        } else {
            console.error('Failed to load monitoring stats:', data.message);
        }
    } catch (error) {
        console.error('Error loading monitoring stats:', error);
    }
}

async function loadActivityStream() {
    try {
        const response = await fetch('api/logs.php?limit=20&type=activity');
        const data = await response.json();
        
        if (data.status === 'success') {
            const activityStream = document.getElementById('activity-stream');
            
            if (data.logs && data.logs.length > 0) {
                const streamHTML = data.logs.map(log => `
                    <div class="activity-item p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-medium">${log.device_id || 'Unknown'}</div>
                                <div class="text-muted small">${log.action || 'Unknown Action'}</div>
                                <div class="text-muted small">${log.details || ''}</div>
                            </div>
                            <div class="text-muted small">${formatTime(log.created_at)}</div>
                        </div>
                    </div>
                `).join('');
                
                activityStream.innerHTML = streamHTML;
            } else {
                activityStream.innerHTML = '<div class="p-3 text-muted text-center">暂无活动记录</div>';
            }
        }
    } catch (error) {
        console.error('Error loading activity stream:', error);
        document.getElementById('activity-stream').innerHTML = '<div class="p-3 text-danger text-center">加载活动流失败</div>';
    }
}

function refreshMonitoring() {
    loadMonitoring();
}

// 自动刷新监控数据
let monitoringInterval = null;

function startMonitoringAutoRefresh() {
    // 清除现有的定时器
    if (monitoringInterval) {
        clearInterval(monitoringInterval);
    }
    
    // 检查自动刷新开关状态
    const autoRefreshEnabled = document.getElementById('auto-refresh').checked;
    
    if (autoRefreshEnabled && currentTab === 'monitoring') {
        // 每10秒刷新一次
        monitoringInterval = setInterval(loadMonitoring, 10000);
    }
}

function stopMonitoringAutoRefresh() {
    if (monitoringInterval) {
        clearInterval(monitoringInterval);
        monitoringInterval = null;
    }
}

// 批量操作
function batchSendCommand() {
    if (selectedDevices.size === 0) {
        alert('请先选择设备');
        return;
    }
    
    // 打开批量指令发送模态框
    showBatchCommandModal();
}

function batchUpdateNotes() {
    if (selectedDevices.size === 0) {
        alert('请先选择设备');
        return;
    }
    
    const notes = prompt('请输入备注内容：');
    if (notes !== null) {
        // 实现批量更新备注
        console.log('Batch updating notes:', notes);
    }
}

function batchExport() {
    if (selectedDevices.size === 0) {
        alert('请先选择设备');
        return;
    }
    
    // 实现批量导出
    console.log('Batch exporting devices');
}

// 设备操作
function removeDevice(deviceId) {
    if (confirm('确定要移除此设备吗？此操作不可恢复。')) {
        // 实现设备移除
        console.log('Removing device:', deviceId);
    }
}

function exportDevices() {
    // 实现设备导出
    console.log('Exporting all devices');
}

function showDeviceSettings() {
    // 显示设备设置
    console.log('Showing device settings');
}

// 显示设备详情
async function showDeviceDetail(deviceId) {
    try {
        const response = await fetch(`api/device_detail.php?device_id=${encodeURIComponent(deviceId)}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const device = data.device;
            const commandHistory = data.command_history || [];
            
            const detailContent = `
                <div class="row">
                    <div class="col-md-6">
                        <h5>基本信息</h5>
                        <table class="table table-sm">
                            <tr><td><strong>设备ID:</strong></td><td>${device.device_id}</td></tr>
                            <tr><td><strong>状态:</strong></td><td><span class="badge bg-${device.status === 'online' ? 'success' : 'secondary'}">${device.status}</span></td></tr>
                            <tr><td><strong>外网IP:</strong></td><td>${device.external_ip || device.ip_address || 'N/A'}</td></tr>
                            <tr><td><strong>内网IP:</strong></td><td>${device.internal_ip || 'N/A'}</td></tr>
                            <tr><td><strong>序列号:</strong></td><td>${device.sn || 'none'}</td></tr>
                            <tr><td><strong>系统信息:</strong></td><td>${parseDetailedSystemInfo(device.system_info)}</td></tr>
                            <tr><td><strong>首次连接:</strong></td><td>${formatDateTime(device.first_seen)}</td></tr>
                            <tr><td><strong>最后在线:</strong></td><td>${formatDateTime(device.last_seen)}</td></tr>
                            <tr><td><strong>备注:</strong></td><td>${device.notes || '无'}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h5>指令历史 <span class="badge bg-secondary">${commandHistory.length}</span></h5>
                        <div style="max-height: 300px; overflow-y: auto;">
                            ${commandHistory.map(cmd => `
                                <div class="card mb-2">
                                    <div class="card-body p-2">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">${formatDateTime(cmd.created_at)}</small>
                                            <span class="badge bg-${getCommandStatusColor(cmd.status)}">${cmd.status}</span>
                                        </div>
                                        <div class="mt-1"><code>${cmd.command}</code></div>
                                        ${cmd.output ? `<button class="btn btn-sm btn-outline-info mt-1" onclick="showCommandResult(${cmd.id})">查看结果</button>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('device-detail-content').innerHTML = detailContent;
            new bootstrap.Modal(document.getElementById('deviceDetailModal')).show();
        }
    } catch (error) {
        console.error('Error loading device detail:', error);
    }
}

// 快速指令
function quickCommand(deviceId) {
    document.getElementById('target-device').value = deviceId;
    new bootstrap.Modal(document.getElementById('sendCommandModal')).show();
}

// 发送指令
async function sendCommand() {
    const deviceId = document.getElementById('target-device').value;
    const command = document.getElementById('command-input').value.trim();
    
    if (!deviceId || !command) {
        alert('请选择设备并输入指令');
        return;
    }
    
    try {
        const response = await fetch('api/send_command.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_id: deviceId,
                command: command
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert('指令发送成功！');
            bootstrap.Modal.getInstance(document.getElementById('sendCommandModal')).hide();
            document.getElementById('send-command-form').reset();
            
            // 刷新指令列表
            if (currentTab === 'commands') {
                loadCommands();
            }
        } else {
            alert('指令发送失败: ' + data.message);
        }
    } catch (error) {
        console.error('Error sending command:', error);
        alert('指令发送失败');
    }
}

// 加载指令历史
async function loadCommands() {
    try {
        const response = await fetch('api/commands.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            commands = data.commands;
            renderCommands();
        }
    } catch (error) {
        console.error('Error loading commands:', error);
    }
}

// 渲染指令表格
function renderCommands() {
    const tbody = document.getElementById('commands-table');
    tbody.innerHTML = '';
    
    commands.forEach(cmd => {
        const row = `
            <tr>
                <td>${cmd.device_id}</td>
                <td><code>${cmd.command.length > 50 ? cmd.command.substring(0, 50) + '...' : cmd.command}</code></td>
                <td><span class="badge bg-${getCommandStatusColor(cmd.status)}">${cmd.status}</span></td>
                <td>${formatDateTime(cmd.created_at)}</td>
                <td>${cmd.completed_at ? formatDateTime(cmd.completed_at) : '-'}</td>
                <td>
                    ${(cmd.status === 'completed' || cmd.status === 'failed') ? `<button class="btn btn-sm btn-outline-info" onclick="showCommandResult(${cmd.id})">查看结果</button>` : ''}
                    ${cmd.status === 'pending' ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelCommand(${cmd.id})">取消</button>` : ''}
                </td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// 显示指令结果
async function showCommandResult(commandId) {
    try {
        const response = await fetch(`api/commands.php?id=${commandId}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            const cmd = data.command;
            const resultContent = `
                <div class="mb-3">
                    <h6>指令信息</h6>
                    <table class="table table-sm">
                        <tr><td><strong>设备:</strong></td><td>${cmd.device_id}</td></tr>
                        <tr><td><strong>指令:</strong></td><td><code>${cmd.command}</code></td></tr>
                        <tr><td><strong>状态:</strong></td><td><span class="badge bg-${getCommandStatusColor(cmd.status)}">${cmd.status}</span></td></tr>
                        <tr><td><strong>退出码:</strong></td><td>${cmd.exit_code !== null ? cmd.exit_code : 'N/A'}</td></tr>
                        <tr><td><strong>执行时间:</strong></td><td>${formatDateTime(cmd.created_at)}</td></tr>
                        <tr><td><strong>完成时间:</strong></td><td>${cmd.completed_at ? formatDateTime(cmd.completed_at) : 'N/A'}</td></tr>
                    </table>
                </div>
                <div>
                    <h6>执行结果</h6>
                    <div class="terminal-output p-3 rounded bg-dark text-light">
                        <pre style="color: #fff; margin: 0;">${cmd.output || '无输出'}</pre>
                    </div>
                </div>
            `;
            
            document.getElementById('command-result-content').innerHTML = resultContent;
            new bootstrap.Modal(document.getElementById('commandResultModal')).show();
        }
    } catch (error) {
        console.error('Error loading command result:', error);
    }
}

// 检查设备冲突
async function checkConflicts() {
    try {
        const response = await fetch('api/conflicts.php');
        const data = await response.json();
        
        if (data.status === 'success' && data.conflicts.length > 0) {
            renderConflicts(data.conflicts);
            
            // 如果当前不在冲突标签页，显示通知
            if (currentTab !== 'alerts') {
                showConflictNotification(data.conflicts.length);
            }
        } else {
            renderConflicts([]);
        }
    } catch (error) {
        console.error('Error checking conflicts:', error);
    }
}

// 渲染冲突信息
function renderConflicts(conflicts) {
    const container = document.getElementById('conflicts-container');
    
    if (conflicts.length === 0) {
        container.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> 当前没有设备ID冲突
            </div>
        `;
        return;
    }
    
    let conflictHtml = `
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> 
            发现 ${conflicts.length} 个设备ID冲突，请立即处理！
        </div>
    `;
    
    conflicts.forEach(conflict => {
        conflictHtml += `
            <div class="card mb-3 border-danger">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0">冲突设备ID: ${conflict.device_id}</h6>
                </div>
                <div class="card-body">
                    <p><strong>设备数量:</strong> ${conflict.count}</p>
                    <p><strong>IP地址:</strong> ${conflict.ip_addresses}</p>
                    <div class="alert alert-warning">
                        <strong>处理建议:</strong> 多个设备使用了相同的设备ID，这可能表示设备被克隆或存在安全风险。
                        请检查这些IP地址对应的设备，确认其合法性。
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = conflictHtml;
}

// 工具函数
function formatDateTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN');
}

function formatTime(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return '刚刚';
    if (diffMins < 60) return `${diffMins}分钟前`;
    if (diffHours < 24) return `${diffHours}小时前`;
    if (diffDays < 7) return `${diffDays}天前`;
    return date.toLocaleDateString('zh-CN');
}

function getCommandStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'sent': 'info',
        'completed': 'success',
        'failed': 'danger',
        'cancelled': 'secondary'
    };
    return colors[status] || 'secondary';
}

function showConflictNotification(count) {
    // 这里可以实现通知功能，比如浏览器通知或页面内通知
    console.log(`检测到 ${count} 个设备冲突`);
}

// 搜索设备
function searchDevices() {
    const query = document.getElementById('device-search').value.trim();
    if (!query) {
        renderDevices();
        return;
    }
    
    const filteredDevices = devices.filter(device => 
        device.device_id.toLowerCase().includes(query.toLowerCase()) ||
        (device.system_info && parseSystemInfo(device.system_info) && parseSystemInfo(device.system_info).toLowerCase().includes(query.toLowerCase())) ||
        (device.ip_address && device.ip_address.includes(query)) ||
        (device.external_ip && device.external_ip.includes(query)) ||
        (device.internal_ip && device.internal_ip.includes(query)) ||
        (device.notes && device.notes.toLowerCase().includes(query.toLowerCase()))
    );
    
    // 临时替换设备列表进行渲染
    const originalDevices = devices;
    devices = filteredDevices;
    renderDevices();
    devices = originalDevices;
}

// 刷新设备
function refreshDevices() {
    loadDevices();
}

// 加载设备选项到下拉菜单
function loadDeviceOptions() {
    const select = document.getElementById('target-device');
    select.innerHTML = '<option value="">选择设备...</option>';
    
    devices.filter(device => device.status === 'online').forEach(device => {
        const option = document.createElement('option');
        option.value = device.device_id;
        option.textContent = `${device.device_id} (外网: ${device.external_ip || device.ip_address || 'N/A'}, 内网: ${device.internal_ip || 'N/A'})`;
        select.appendChild(option);
    });
}

// 监听发送指令模态框显示事件
document.getElementById('sendCommandModal').addEventListener('show.bs.modal', function() {
    loadDeviceOptions();
});

// 编辑设备备注
async function editDeviceNotes(deviceId) {
    const device = devices.find(d => d.device_id === deviceId);
    if (!device) {
        alert('设备不存在');
        return;
    }
    
    const currentNotes = device.notes || '';
    const newNotes = prompt('编辑设备备注:', currentNotes);
    
    // 如果用户点击取消或输入为空，则不更新
    if (newNotes === null) {
        return;
    }
    
    try {
        const response = await fetch('api/devices.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                device_id: deviceId,
                notes: newNotes
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            // 更新本地设备数据
            device.notes = newNotes;
            renderDevices();
            alert('备注更新成功');
        } else {
            alert('备注更新失败: ' + (data.message || '未知错误'));
        }
    } catch (error) {
        console.error('Error updating device notes:', error);
        alert('备注更新失败: 网络错误');
    }
}

// 系统维护功能
async function loadSystemStats() {
    try {
        const response = await fetch('api/stats.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            document.getElementById('device-count').textContent = data.device_count || '-';
            document.getElementById('total-logs').textContent = data.total_logs || '-';
            document.getElementById('heartbeat-logs').textContent = data.heartbeat_logs || '-';
        } else {
            console.error('Failed to load system stats:', data.message);
        }
    } catch (error) {
        console.error('Error loading system stats:', error);
    }
}

// 执行清理
async function performCleanup() {
    const daysToKeep = parseInt(document.getElementById('cleanup-days').value);
    const forceCleanup = document.getElementById('force-cleanup').checked;
    
    if (!confirm(`确定要执行清理吗？${forceCleanup ? '这将删除所有心跳失败日志！' : `这将删除 ${daysToKeep} 天前的心跳失败日志。`}`)) {
        return;
    }
    
    const resultDiv = document.getElementById('cleanup-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-clock"></i> 正在执行清理...</div>';
    
    try {
        const response = await fetch('api/cleanup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                admin_key: 'your_admin_secret_key', // 这里应该从配置或用户输入获取
                days_to_keep: daysToKeep,
                force_cleanup: forceCleanup
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            resultDiv.innerHTML = `<div class="alert alert-success"><i class="bi bi-check-circle"></i> ${data.message}</div>`;
            // 刷新统计信息
            loadSystemStats();
        } else {
            resultDiv.innerHTML = `<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> 清理失败: ${data.message}</div>`;
        }
    } catch (error) {
        console.error('Error performing cleanup:', error);
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> 清理失败: 网络错误</div>';
    }
}

// 解析系统信息 - 处理不同格式的 system_info
function parseSystemInfo(systemInfo) {
    if (!systemInfo) {
        return null;
    }
    
    let parsed = null;
    
    // 如果是字符串，尝试解析
    if (typeof systemInfo === 'string') {
        try {
            parsed = JSON.parse(systemInfo);
        } catch (e) {
            // 不是 JSON，直接返回原字符串
            return systemInfo;
        }
    } else if (typeof systemInfo === 'object') {
        parsed = systemInfo;
    }
    
    // 如果解析成功且是对象，提取关键信息
    if (parsed && typeof parsed === 'object') {
        const parts = [];
        
        if (parsed.model) {
            parts.push(parsed.model);
        }
        
        if (parsed.version) {
            parts.push(parsed.version);
        }
        
        if (parsed.arch) {
            parts.push(`[${parsed.arch}]`);
        }
        
        return parts.length > 0 ? parts.join(' ') : 'OpenWrt Device';
    }
    
    return systemInfo.toString();
}

// 获取加密状态徽章
function getEncryptionStatusBadge(device) {
    console.log('getEncryptionStatusBadge called with device:', device); // 调试日志
    
    if (!device) {
        return '<span class="badge bg-secondary">未知</span>';
    }
    
    const isEncrypted = device.encryption_enabled === 1 || device.encryption_enabled === '1' || device.encryption_enabled === true;
    
    console.log('Device encryption check:', {
        device_id: device.device_id,
        encryption_enabled: device.encryption_enabled,
        isEncrypted: isEncrypted
    }); // 调试日志
    
    if (isEncrypted) {
        const lastExchange = device.last_key_exchange;
        const cipher = device.encryption_cipher || 'AES-256-CBC';
        
        let title = `已启用加密 (${cipher})`;
        if (lastExchange) {
            const exchangeDate = new Date(lastExchange);
            title += `\n最后密钥交换: ${exchangeDate.toLocaleString()}`;
        }
        
        return `<span class="badge bg-success" title="${title}">🔒 已加密</span>`;
    } else {
        return '<span class="badge bg-warning text-dark" title="设备未启用加密通信">🔓 未加密</span>';
    }
}

// 创建详细的系统信息显示函数
function parseDetailedSystemInfo(systemInfo) {
    if (!systemInfo) {
        return 'N/A';
    }
    
    let parsed = null;
    
    // 如果是字符串，尝试解析
    if (typeof systemInfo === 'string') {
        try {
            parsed = JSON.parse(systemInfo);
        } catch (e) {
            return systemInfo;
        }
    } else if (typeof systemInfo === 'object') {
        parsed = systemInfo;
    }
    
    // 如果解析成功且是对象，创建详细信息
    if (parsed && typeof parsed === 'object') {
        const details = [];
        
        if (parsed.model) {
            details.push(`<strong>型号:</strong> ${parsed.model}`);
        }
        
        if (parsed.version) {
            details.push(`<strong>版本:</strong> ${parsed.version}`);
        }
        
        if (parsed.arch) {
            details.push(`<strong>架构:</strong> ${parsed.arch}`);
        }
        
        if (parsed.sn) {
            details.push(`<strong>序列号:</strong> ${parsed.sn}`);
        }
        
        return details.length > 0 ? details.join('<br>') : 'OpenWrt Device';
    }
    
    return systemInfo.toString();
}

// 模板管理功能
async function loadTemplates() {
    try {
        const response = await fetch('api/templates.php');
        const data = await response.json();
        
        if (data.status === 'success') {
            templates = data.templates || [];
            renderTemplates();
        } else {
            console.error('Failed to load templates:', data.message);
        }
    } catch (error) {
        console.error('Error loading templates:', error);
    }
}

// 渲染模板列表
function renderTemplates() {
    const container = document.getElementById('templates-container');
    container.innerHTML = '';
    
    if (templates.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> 还没有创建任何指令模板。点击"创建模板"按钮开始。
                </div>
            </div>
        `;
        return;
    }
    
    // 按分类分组模板
    const categories = {
        'system': '系统管理',
        'network': '网络配置', 
        'monitoring': '监控诊断',
        'maintenance': '维护操作',
        'custom': '自定义'
    };
    
    const groupedTemplates = {};
    templates.forEach(template => {
        const category = template.category || 'custom';
        if (!groupedTemplates[category]) {
            groupedTemplates[category] = [];
        }
        groupedTemplates[category].push(template);
    });
    
    // 渲染每个分类
    Object.keys(groupedTemplates).forEach(category => {
        const categoryName = categories[category] || category;
        let categoryHtml = `
            <div class="col-12 mb-3">
                <h5 class="border-bottom pb-2"><i class="bi bi-folder"></i> ${categoryName}</h5>
                <div class="row">
        `;
        
        groupedTemplates[category].forEach(template => {
            categoryHtml += `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card template-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title">${template.name}</h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" onclick="useTemplate(${template.id})"><i class="bi bi-play"></i> 使用模板</a></li>
                                        <li><a class="dropdown-item" onclick="editTemplate(${template.id})"><i class="bi bi-pencil"></i> 编辑</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" onclick="deleteTemplate(${template.id})"><i class="bi bi-trash"></i> 删除</a></li>
                                    </ul>
                                </div>
                            </div>
                            <p class="card-text text-muted small">${template.description || '无描述'}</p>
                            <div class="template-command-preview">
                                <code class="small">${template.command.length > 50 ? template.command.substring(0, 50) + '...' : template.command}</code>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <small class="text-muted">
                                <i class="bi bi-clock"></i> ${formatDateTime(template.created_at)}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });
        
        categoryHtml += `
                </div>
            </div>
        `;
        
        container.innerHTML += categoryHtml;
    });
}

// 创建模板
async function createTemplate() {
    const name = document.getElementById('template-name').value.trim();
    const description = document.getElementById('template-description').value.trim();
    const command = document.getElementById('template-command').value.trim();
    const category = document.getElementById('template-category').value;
    
    if (!name || !command) {
        alert('请填写模板名称和指令');
        return;
    }
    
    try {
        const response = await fetch('api/templates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                name: name,
                description: description,
                command: command,
                category: category
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert('模板创建成功！');
            bootstrap.Modal.getInstance(document.getElementById('createTemplateModal')).hide();
            document.getElementById('create-template-form').reset();
            loadTemplates(); // 重新加载模板列表
        } else {
            alert('模板创建失败: ' + data.message);
        }
    } catch (error) {
        console.error('Error creating template:', error);
        alert('模板创建失败: 网络错误');
    }
}

// 使用模板
function useTemplate(templateId) {
    const template = templates.find(t => t.id === templateId);
    if (!template) {
        alert('模板不存在');
        return;
    }
    
    // 打开发送指令模态框并填入模板内容
    document.getElementById('command-input').value = template.command;
    new bootstrap.Modal(document.getElementById('sendCommandModal')).show();
}

// 编辑模板
async function editTemplate(templateId) {
    const template = templates.find(t => t.id === templateId);
    if (!template) {
        alert('模板不存在');
        return;
    }
    
    // 填入当前值
    document.getElementById('template-name').value = template.name;
    document.getElementById('template-description').value = template.description || '';
    document.getElementById('template-command').value = template.command;
    document.getElementById('template-category').value = template.category || 'custom';
    
    // 修改按钮文本和事件
    const modal = document.getElementById('createTemplateModal');
    const title = modal.querySelector('.modal-title');
    const button = modal.querySelector('.btn-primary');
    
    title.textContent = '编辑指令模板';
    button.textContent = '保存更改';
    button.onclick = () => updateTemplate(templateId);
    
    new bootstrap.Modal(modal).show();
}

// 更新模板
async function updateTemplate(templateId) {
    const name = document.getElementById('template-name').value.trim();
    const description = document.getElementById('template-description').value.trim();
    const command = document.getElementById('template-command').value.trim();
    const category = document.getElementById('template-category').value;
    
    if (!name || !command) {
        alert('请填写模板名称和指令');
        return;
    }
    
    try {
        const response = await fetch('api/templates.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: templateId,
                name: name,
                description: description,
                command: command,
                category: category
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert('模板更新成功！');
            bootstrap.Modal.getInstance(document.getElementById('createTemplateModal')).hide();
            resetTemplateModal();
            loadTemplates(); // 重新加载模板列表
        } else {
            alert('模板更新失败: ' + data.message);
        }
    } catch (error) {
        console.error('Error updating template:', error);
        alert('模板更新失败: 网络错误');
    }
}

// 删除模板
async function deleteTemplate(templateId) {
    const template = templates.find(t => t.id === templateId);
    if (!template) {
        alert('模板不存在');
        return;
    }
    
    if (!confirm(`确定要删除模板 "${template.name}" 吗？此操作不可恢复。`)) {
        return;
    }
    
    try {
        const response = await fetch('api/templates.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: templateId
            })
        });
        
        const data = await response.json();
        
        if (data.status === 'success') {
            alert('模板删除成功！');
            loadTemplates(); // 重新加载模板列表
        } else {
            alert('模板删除失败: ' + data.message);
        }
    } catch (error) {
        console.error('Error deleting template:', error);
        alert('模板删除失败: 网络错误');
    }
}

// 重置模板模态框
function resetTemplateModal() {
    const modal = document.getElementById('createTemplateModal');
    const title = modal.querySelector('.modal-title');
    const button = modal.querySelector('.btn-primary');
    
    title.textContent = '创建指令模板';
    button.textContent = '创建模板';
    button.onclick = createTemplate;
    
    document.getElementById('create-template-form').reset();
}

// 系统日志管理功能
let currentLogPage = 0;
const logsPerPage = 50;

async function loadLogs() {
    try {
        const response = await fetch(`api/logs.php?limit=${logsPerPage}&offset=${currentLogPage * logsPerPage}`);
        const data = await response.json();
        
        if (data.status === 'success') {
            renderLogs(data.logs);
            updateLogsPagination(data.total);
            renderLogFilters(data.available_actions, data.available_devices);
        } else {
            console.error('Failed to load logs:', data.message);
        }
    } catch (error) {
        console.error('Error loading logs:', error);
    }
}

// 渲染日志表格
function renderLogs(logs) {
    const tbody = document.getElementById('logs-table');
    tbody.innerHTML = '';
    
    if (logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-muted">
                    <i class="bi bi-inbox"></i> 暂无日志记录
                </td>
            </tr>
        `;
        return;
    }
    
    logs.forEach(log => {
        const actionBadge = getLogActionBadge(log.action);
        const row = `
            <tr>
                <td>${formatDateTime(log.created_at)}</td>
                <td>${log.device_id || '-'}</td>
                <td>${actionBadge}</td>
                <td>${log.details || '-'}</td>
                <td>${log.ip_address || '-'}</td>
            </tr>
        `;
        tbody.innerHTML += row;
    });
}

// 获取日志操作的徽章样式
function getLogActionBadge(action) {
    const badges = {
        'heartbeat': '<span class="badge bg-success">心跳</span>',
        'auth': '<span class="badge bg-info">认证</span>',
        'command': '<span class="badge bg-warning">指令</span>',
        'error': '<span class="badge bg-danger">错误</span>',
        'connect': '<span class="badge bg-primary">连接</span>',
        'disconnect': '<span class="badge bg-secondary">断开</span>'
    };
    
    return badges[action] || `<span class="badge bg-light text-dark">${action}</span>`;
}

// 更新日志分页
function updateLogsPagination(total) {
    const totalPages = Math.ceil(total / logsPerPage);
    const paginationContainer = document.getElementById('logs-pagination');
    
    if (!paginationContainer) {
        // 创建分页容器
        const logsCard = document.querySelector('#logs-tab .card-body');
        const paginationHtml = `
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <small class="text-muted">
                        显示第 ${currentLogPage * logsPerPage + 1} - ${Math.min((currentLogPage + 1) * logsPerPage, total)} 条，共 ${total} 条记录
                    </small>
                </div>
                <nav id="logs-pagination">
                    <!-- 分页按钮将在这里生成 -->
                </nav>
            </div>
        `;
        logsCard.innerHTML += paginationHtml;
    }
    
    const pagination = document.getElementById('logs-pagination');
    let paginationHtml = '<ul class="pagination pagination-sm mb-0">';
    
    // 上一页
    if (currentLogPage > 0) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogsPage(${currentLogPage - 1})">上一页</a></li>`;
    } else {
        paginationHtml += `<li class="page-item disabled"><span class="page-link">上一页</span></li>`;
    }
    
    // 页码
    const startPage = Math.max(0, currentLogPage - 2);
    const endPage = Math.min(totalPages - 1, currentLogPage + 2);
    
    if (startPage > 0) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogsPage(0)">1</a></li>`;
        if (startPage > 1) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentLogPage) {
            paginationHtml += `<li class="page-item active"><span class="page-link">${i + 1}</span></li>`;
        } else {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogsPage(${i})">${i + 1}</a></li>`;
        }
    }
    
    if (endPage < totalPages - 1) {
        if (endPage < totalPages - 2) {
            paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogsPage(${totalPages - 1})">${totalPages}</a></li>`;
    }
    
    // 下一页
    if (currentLogPage < totalPages - 1) {
        paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="loadLogsPage(${currentLogPage + 1})">下一页</a></li>`;
    } else {
        paginationHtml += `<li class="page-item disabled"><span class="page-link">下一页</span></li>`;
    }
    
    paginationHtml += '</ul>';
    pagination.innerHTML = paginationHtml;
}

// 加载指定页的日志
function loadLogsPage(page) {
    currentLogPage = page;
    loadLogs();
}

// 渲染日志过滤器
function renderLogFilters(actions, devices) {
    const filtersContainer = document.getElementById('logs-filters');
    
    if (!filtersContainer) {
        // 创建过滤器容器
        const logsTab = document.getElementById('logs-tab');
        const filtersHtml = `
            <div class="row mb-3" id="logs-filters">
                <div class="col-md-3">
                    <label for="log-device-filter" class="form-label">设备筛选</label>
                    <select class="form-select form-select-sm" id="log-device-filter" onchange="filterLogs()">
                        <option value="">所有设备</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="log-action-filter" class="form-label">操作筛选</label>
                    <select class="form-select form-select-sm" id="log-action-filter" onchange="filterLogs()">
                        <option value="">所有操作</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button class="btn btn-outline-primary btn-sm" onclick="refreshLogs()">
                            <i class="bi bi-arrow-clockwise"></i> 刷新
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="clearLogFilters()">
                            <i class="bi bi-x"></i> 清除筛选
                        </button>
                    </div>
                </div>
            </div>
        `;
        logsTab.insertAdjacentHTML('afterbegin', filtersHtml);
    }
    
    // 填充设备选项
    const deviceSelect = document.getElementById('log-device-filter');
    deviceSelect.innerHTML = '<option value="">所有设备</option>';
    devices.forEach(device => {
        deviceSelect.innerHTML += `<option value="${device}">${device}</option>`;
    });
    
    // 填充操作选项
    const actionSelect = document.getElementById('log-action-filter');
    actionSelect.innerHTML = '<option value="">所有操作</option>';
    actions.forEach(action => {
        actionSelect.innerHTML += `<option value="${action}">${action}</option>`;
    });
}

// 过滤日志
function filterLogs() {
    currentLogPage = 0;
    loadLogsWithFilters();
}

// 带过滤器加载日志
async function loadLogsWithFilters() {
    try {
        const deviceFilter = document.getElementById('log-device-filter')?.value || '';
        const actionFilter = document.getElementById('log-action-filter')?.value || '';
        
        let url = `api/logs.php?limit=${logsPerPage}&offset=${currentLogPage * logsPerPage}`;
        if (deviceFilter) url += `&device_id=${encodeURIComponent(deviceFilter)}`;
        if (actionFilter) url += `&action=${encodeURIComponent(actionFilter)}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.status === 'success') {
            renderLogs(data.logs);
            updateLogsPagination(data.total);
        } else {
            console.error('Failed to load filtered logs:', data.message);
        }
    } catch (error) {
        console.error('Error loading filtered logs:', error);
    }
}

// ==================== 设备筛选和统计功能 ====================

// 获取设备型号
function getDeviceModel(device) {
    try {
        if (device.system_info) {
            const sysInfo = JSON.parse(device.system_info);
            return sysInfo.model || 'Unknown';
        }
    } catch (e) {
        // 如果解析失败，尝试从字符串中提取
        if (device.system_info && device.system_info.includes('|')) {
            const parts = device.system_info.split('|');
            return parts[1] || 'Unknown';
        }
    }
    return 'Unknown';
}

// 获取设备版本
function getDeviceVersion(device) {
    try {
        if (device.system_info) {
            const sysInfo = JSON.parse(device.system_info);
            return sysInfo.version || 'Unknown';
        }
    } catch (e) {
        // 如果解析失败，尝试从字符串中提取
        if (device.system_info && device.system_info.includes('|')) {
            const parts = device.system_info.split('|');
            return parts[0] || 'Unknown';
        }
    }
    return 'Unknown';
}

// 更新筛选摘要
function updateFilterSummary() {
    const deviceIdFilter = document.getElementById('device-id-filter').value;
    const query = document.getElementById('device-search').value.trim();
    const statusFilter = document.getElementById('status-filter').value;
    const modelFilter = document.getElementById('model-filter').value;
    const versionFilter = document.getElementById('version-filter').value;
    
    const hasFilters = deviceIdFilter || query || statusFilter || modelFilter || versionFilter;
    const filterSummary = document.getElementById('filter-summary');
    const filterSummaryText = document.getElementById('filter-summary-text');
    
    if (hasFilters) {
        let summaryText = `找到 ${filteredDevices.length} 个设备`;
        let filtersParts = [];
        
        if (deviceIdFilter) filtersParts.push(`设备ID"${deviceIdFilter}"`);
        if (query) filtersParts.push(`搜索"${query}"`);
        if (statusFilter) filtersParts.push(`状态"${statusFilter === 'online' ? '在线' : '离线'}"`);
        if (modelFilter) filtersParts.push(`型号"${modelFilter}"`);
        if (versionFilter) filtersParts.push(`版本"${versionFilter}"`);
        
        if (filtersParts.length > 0) {
            summaryText += ` （筛选条件：${filtersParts.join('，')}）`;
        }
        
        filterSummaryText.textContent = summaryText;
        filterSummary.style.display = 'block';
    } else {
        filterSummary.style.display = 'none';
    }
}

// 清除所有筛选
function clearAllFilters() {
    document.getElementById('device-search').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('model-filter').value = '';
    document.getElementById('version-filter').value = '';
    
    // 重置快速筛选
    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.classList.remove('active');
    });
    document.querySelector('[data-filter="all"]').classList.add('active');
    
    filteredDevices = [...devices];
    updateFilterSummary();
    currentPage = 0;
    renderDevices();
    updatePagination();
}

// 加载筛选选项
async function loadFilterOptions() {
    try {
        // 加载设备ID选项
        const deviceIdResponse = await fetch('api/filters.php?action=values&field=device_id');
        const deviceIdData = await deviceIdResponse.json();
        
        if (deviceIdData.status === 'success') {
            const deviceIdSelect = document.getElementById('device-id-filter');
            const currentValue = deviceIdSelect.value;
            
            // 清空现有选项，保留默认选项
            deviceIdSelect.innerHTML = '<option value="">全部设备</option>';
            
            // 添加设备ID选项，显示数量
            deviceIdData.values.forEach(item => {
                if (item.value) {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = `${item.value} (${item.count})`;
                    deviceIdSelect.appendChild(option);
                }
            });
            
            // 恢复之前选择的值
            if (currentValue) deviceIdSelect.value = currentValue;
        }
        
        // 加载型号选项
        const modelResponse = await fetch('api/filters.php?action=values&field=model');
        const modelData = await modelResponse.json();
        
        if (modelData.status === 'success') {
            const modelSelect = document.getElementById('model-filter');
            const currentValue = modelSelect.value;
            
            // 清空现有选项，保留默认选项
            modelSelect.innerHTML = '<option value="">全部型号</option>';
            
            // 添加型号选项
            modelData.values.forEach(item => {
                if (item.value && item.value !== 'Unknown') {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = `${item.value} (${item.count})`;
                    modelSelect.appendChild(option);
                }
            });
            
            // 恢复之前选择的值
            if (currentValue) modelSelect.value = currentValue;
        }
        
        // 加载版本选项
        const versionResponse = await fetch('api/filters.php?action=values&field=version');
        const versionData = await versionResponse.json();
        
        if (versionData.status === 'success') {
            const versionSelect = document.getElementById('version-filter');
            const currentValue = versionSelect.value;
            
            // 清空现有选项，保留默认选项
            versionSelect.innerHTML = '<option value="">全部版本</option>';
            
            // 添加版本选项
            versionData.values.forEach(item => {
                if (item.value && item.value !== 'Unknown') {
                    const option = document.createElement('option');
                    option.value = item.value;
                    option.textContent = `${item.value} (${item.count})`;
                    versionSelect.appendChild(option);
                }
            });
            
            // 恢复之前选择的值
            if (currentValue) versionSelect.value = currentValue;
        }
        
    } catch (error) {
        console.error('Error loading filter options:', error);
    }
}

// 显示设备统计信息
async function showDeviceStats() {
    try {
        const response = await fetch('../api/filters.php?action=stats');
        const data = await response.json();
        
        if (data.status === 'success') {
            const stats = data.stats;
            
            // 更新模态框中的统计数据
            document.getElementById('modal-total-devices').textContent = stats.total_devices;
            document.getElementById('modal-online-devices').textContent = stats.online_devices;
            document.getElementById('modal-offline-devices').textContent = stats.offline_devices;
            
            // 更新型号统计列表
            const modelList = document.getElementById('model-stats-list');
            modelList.innerHTML = '';
            stats.models.forEach(item => {
                const div = document.createElement('div');
                div.className = 'stats-item';
                div.onclick = () => filterByField('model', item.model);
                div.innerHTML = `
                    <span class="stats-item-label">${item.model}</span>
                    <span class="stats-item-count">${item.count}</span>
                `;
                modelList.appendChild(div);
            });
            
            // 更新版本统计列表
            const versionList = document.getElementById('version-stats-list');
            versionList.innerHTML = '';
            stats.versions.forEach(item => {
                const div = document.createElement('div');
                div.className = 'stats-item';
                div.onclick = () => filterByField('version', item.version);
                div.innerHTML = `
                    <span class="stats-item-label">${item.version}</span>
                    <span class="stats-item-count">${item.count}</span>
                `;
                versionList.appendChild(div);
            });
            
            // 更新状态统计列表
            const statusList = document.getElementById('status-stats-list');
            statusList.innerHTML = '';
            stats.statuses.forEach(item => {
                const div = document.createElement('div');
                div.className = 'stats-item';
                div.onclick = () => filterByField('status', item.status);
                div.innerHTML = `
                    <span class="stats-item-label">${item.status === 'online' ? '在线' : '离线'}</span>
                    <span class="stats-item-count">${item.count}</span>
                `;
                statusList.appendChild(div);
            });
            
            // 显示统计模态框
            const modal = new bootstrap.Modal(document.getElementById('deviceStatsModal'));
            modal.show();
            
            // 在模态框显示后创建图表
            setTimeout(() => {
                createStatsCharts(stats);
            }, 300);
            
        } else {
            showNotification('获取统计信息失败: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error loading device stats:', error);
        showNotification('获取统计信息时发生错误', 'error');
    }
}

// 根据字段筛选
function filterByField(field, value) {
    // 关闭统计模态框
    const modal = bootstrap.Modal.getInstance(document.getElementById('deviceStatsModal'));
    if (modal) {
        modal.hide();
    }
    
    // 设置筛选值
    if (field === 'device_id') {
        document.getElementById('device-id-filter').value = value;
    } else if (field === 'model') {
        document.getElementById('model-filter').value = value;
    } else if (field === 'version') {
        document.getElementById('version-filter').value = value;
    } else if (field === 'status') {
        document.getElementById('status-filter').value = value;
    }
    
    // 应用筛选
    applyFilters();
}

// 导出统计信息
function exportStats() {
    // 这里可以实现导出功能
    showNotification('统计信息导出功能正在开发中...', 'info');
}

// 刷新日志
function refreshLogs() {
    currentLogPage = 0;
    loadLogs();
}

// 清除日志筛选
function clearLogFilters() {
    document.getElementById('log-device-filter').value = '';
    document.getElementById('log-action-filter').value = '';
    currentLogPage = 0;
    loadLogs();
}
