<?php
/**
 * Device Management Class
 */

require_once 'Database.php';

class DeviceManager {
    private $db;
    
    public function __construct() {
        // 设置时区为北京时间
        date_default_timezone_set('Asia/Shanghai');
        $this->db = new Database();
    }
    
    /**
     * 从系统信息中提取内网IP
     */
    private function extractInternalIpFromSystemInfo($systemInfo) {
        return $this->extractInternalIpFromSystemInfoHelper($systemInfo);
    }

    /**
     * 智能检查并标记离线设备
     * 只在有心跳活动时触发，避免过于频繁的检查
     */
    private function checkAndMarkStaleDevices() {
        // 使用120秒超时，符合60秒心跳间隔（丢失2次心跳判定离线）
        $timeout = 120; // 120秒（2倍心跳间隔）
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$timeout} seconds"));
        
        $sql = "UPDATE devices SET status = 'offline' 
                WHERE status = 'online' 
                AND last_seen < :cutoff_time";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cutoff_time' => $cutoffTime]);
    }

    /**
     * 从系统信息中提取内网IP的辅助方法
     */
    private function extractInternalIpFromSystemInfoHelper($systemInfo) {
        $internalIp = null;
        
        if (is_string($systemInfo)) {
            $systemData = json_decode($systemInfo, true);
            if ($systemData && isset($systemData['internal_ip'])) {
                $internalIp = $systemData['internal_ip'];
            }
        } elseif (is_array($systemInfo) && isset($systemInfo['internal_ip'])) {
            $internalIp = $systemInfo['internal_ip'];
        }
        
        return $internalIp;
    }
    
    /**
     * Register or update a device
     * 根据设备ID、内网IP、外网IP的组合判断是否为同一设备
     */
    public function registerDevice($deviceId, $systemInfo, $ipAddress) {
        // 解析系统信息以获取SN和内网IP
        $sn = 'none';
        $internalIp = $this->extractInternalIpFromSystemInfo($systemInfo);
        
        if ($systemInfo) {
            $sysInfo = json_decode($systemInfo, true);
            if ($sysInfo && isset($sysInfo['sn'])) {
                $sn = $sysInfo['sn'] ?: 'none';
            }
        }
        
        // 查找是否存在相同设备ID、内网IP、外网IP的设备
        $existing = $this->findExistingDevice($deviceId, $ipAddress, $internalIp);
        
        if ($existing) {
            // Update existing device
            $data = [
                'system_info' => $systemInfo,
                'last_seen' => date('Y-m-d H:i:s'),
                'status' => 'online'
            ];
            
            // 向后兼容：同时更新新旧字段
            if ($ipAddress) {
                $data['ip_address'] = $ipAddress;
                $data['external_ip'] = $ipAddress;
            }
            
            if ($internalIp) {
                $data['internal_ip'] = $internalIp;
            }
            
            // 更新SN字段（如果表中存在该字段）
            try {
                $columnExists = $this->db->selectOne("SHOW COLUMNS FROM devices LIKE 'sn'");
                if ($columnExists) {
                    $data['sn'] = $sn;
                }
            } catch (Exception $e) {
                // 忽略字段不存在的错误
            }
            
            $this->db->update('devices', $data, 'id = :id', ['id' => $existing['id']]);
            return $existing['id'];
        } else {
            // Create new device
            $currentTime = date('Y-m-d H:i:s');
            $data = [
                'device_id' => $deviceId,
                'system_info' => $systemInfo,
                'first_seen' => $currentTime,
                'last_seen' => $currentTime,
                'status' => 'online',
                'notes' => ''
            ];
            
            // 调试日志：记录设备注册时间
            error_log("New device registered: $deviceId at $currentTime (timezone: " . date_default_timezone_get() . ")");
            
            // 向后兼容：同时设置新旧字段
            if ($ipAddress) {
                $data['ip_address'] = $ipAddress;
                $data['external_ip'] = $ipAddress;
            }
            
            if ($internalIp) {
                $data['internal_ip'] = $internalIp;
            }
            
            // 添加SN字段（如果表中存在该字段）
            try {
                $columnExists = $this->db->selectOne("SHOW COLUMNS FROM devices LIKE 'sn'");
                if ($columnExists) {
                    $data['sn'] = $sn;
                }
            } catch (Exception $e) {
                // 忽略字段不存在的错误
            }
            
            return $this->db->insert('devices', $data);
        }
    }
    
    /**
     * 查找是否存在相同设备ID、内网IP、外网IP的设备
     * 先尝试精确匹配，如果找不到再尝试仅根据设备ID匹配（向后兼容）
     */
    private function findExistingDevice($deviceId, $externalIp, $internalIp) {
        try {
            // 1. 优先查找完全匹配的设备（设备ID + 内网IP + 公网IP）
            $sql = "SELECT * FROM devices WHERE device_id = :device_id AND internal_ip = :internal_ip AND external_ip = :external_ip LIMIT 1";
            $params = [
                'device_id' => $deviceId,
                'internal_ip' => $internalIp,
                'external_ip' => $externalIp
            ];
            
            $exactMatch = $this->db->selectOne($sql, $params);
            if ($exactMatch) {
                return $exactMatch;
            }
            
            // 2. 降级查找：仅匹配设备ID和内网IP（向后兼容）
            $sql = "SELECT * FROM devices WHERE device_id = :device_id AND internal_ip = :internal_ip LIMIT 1";
            $params = [
                'device_id' => $deviceId,
                'internal_ip' => $internalIp
            ];
            
            $fallbackDevice = $this->db->selectOne($sql, $params);
            if ($fallbackDevice) {
                // 检查是否需要更新公网IP
                if ($fallbackDevice['external_ip'] !== $externalIp) {
                    $updateData = [
                        'external_ip' => $externalIp,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $this->db->update('devices', $updateData, 'id = :id', ['id' => $fallbackDevice['id']]);
                    $fallbackDevice = array_merge($fallbackDevice, $updateData);
                    error_log("Updated external IP for device {$deviceId}: -> {$externalIp}");
                }
                return $fallbackDevice;
            }
            
            // 3. 最后降级：仅匹配设备ID（向后兼容旧设备）
            $sql = "SELECT * FROM devices WHERE device_id = :device_id LIMIT 1";
            $params = ['device_id' => $deviceId];
            
            $oldDevice = $this->db->selectOne($sql, $params);
            if ($oldDevice) {
                // 更新IP信息
                $updateData = [];
                if ($oldDevice['internal_ip'] !== $internalIp) {
                    $updateData['internal_ip'] = $internalIp;
                    error_log("Updated internal IP for device {$deviceId}: {$oldDevice['internal_ip']} -> {$internalIp}");
                }
                if ($oldDevice['external_ip'] !== $externalIp) {
                    $updateData['external_ip'] = $externalIp;
                    error_log("Updated external IP for device {$deviceId}: {$oldDevice['external_ip']} -> {$externalIp}");
                }
                
                if (!empty($updateData)) {
                    $updateData['updated_at'] = date('Y-m-d H:i:s');
                    $this->db->update('devices', $updateData, 'id = :id', ['id' => $oldDevice['id']]);
                    $oldDevice = array_merge($oldDevice, $updateData);
                }
                return $oldDevice;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Database query failed in findExistingDevice: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get device by device ID
     */
    public function getDevice($deviceId) {
        return $this->db->selectOne(
            "SELECT * FROM devices WHERE device_id = :device_id",
            ['device_id' => $deviceId]
        );
    }
    
    /**
     * 获取设备详情，包括在线时长和详细状态信息
     */
    public function getDeviceDetail($deviceId) {
        $device = $this->db->selectOne(
            "SELECT *, 
                    CASE 
                        WHEN last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 1 
                        ELSE 0 
                    END as is_online,
                    TIMESTAMPDIFF(SECOND, first_seen, NOW()) as total_lifetime_seconds,
                    TIMESTAMPDIFF(SECOND, first_seen, last_seen) as last_active_duration_seconds
             FROM devices 
             WHERE device_id = :device_id",
            ['device_id' => $deviceId]
        );
        
        if (!$device) {
            return null;
        }
        
        // 计算在线时长相关信息
        $currentTime = time();
        $firstSeenTime = strtotime($device['first_seen']);
        $lastSeenTime = strtotime($device['last_seen']);
        
        // 设备总存在时长（从首次连接到现在）
        $totalLifetimeSeconds = $currentTime - $firstSeenTime;
        
        // 当前在线状态
        $secondsSinceHeartbeat = $currentTime - $lastSeenTime;
        $isCurrentlyOnline = $secondsSinceHeartbeat <= 120;
        
        // 计算当前在线会话时长
        $currentSessionDuration = 0;
        if ($isCurrentlyOnline) {
            // 如果当前在线，计算从什么时候开始在线的
            // 这里我们需要查找最后一次离线的时间
            $lastOfflineTime = $this->getLastOfflineTime($deviceId);
            if ($lastOfflineTime) {
                $currentSessionDuration = $currentTime - strtotime($lastOfflineTime);
            } else {
                // 如果从未离线过，则从首次连接算起
                $currentSessionDuration = $currentTime - $firstSeenTime;
            }
        }
        
        // 添加计算后的时长信息
        $device['uptime_info'] = [
            'is_online' => $isCurrentlyOnline,
            'seconds_since_heartbeat' => max(0, $secondsSinceHeartbeat),
            'total_lifetime_seconds' => $totalLifetimeSeconds,
            'total_lifetime_formatted' => $this->formatDuration($totalLifetimeSeconds),
            'current_session_duration_seconds' => $currentSessionDuration,
            'current_session_duration_formatted' => $this->formatDuration($currentSessionDuration),
            'first_seen_formatted' => date('Y-m-d H:i:s', $firstSeenTime),
            'last_seen_formatted' => date('Y-m-d H:i:s', $lastSeenTime),
            'last_heartbeat_ago' => $this->formatDuration($secondsSinceHeartbeat) . ' ago'
        ];
        
        return $device;
    }
    
    /**
     * 获取设备最后一次离线的时间
     * 这里可以通过分析心跳记录或状态变化来判断
     * 简化实现：如果设备当前在线，假设从上次心跳开始就一直在线
     */
    private function getLastOfflineTime($deviceId) {
        // 简化实现：查找设备状态变化历史
        // 在实际应用中，你可能需要一个专门的状态变化记录表
        
        // 这里我们使用一个简化的方法：
        // 如果设备当前在线，我们假设它从最后一次心跳时间开始就在线
        // 更精确的实现需要记录设备的状态变化历史
        
        return null; // 简化实现，返回null表示无法确定确切的离线时间
    }
    
    /**
     * 格式化时长显示
     */
    private function formatDuration($seconds) {
        if ($seconds < 0) {
            return '0秒';
        }
        
        $units = [
            '年' => 365 * 24 * 3600,
            '天' => 24 * 3600,
            '小时' => 3600,
            '分钟' => 60,
            '秒' => 1
        ];
        
        $result = [];
        
        foreach ($units as $name => $divisor) {
            $quot = intval($seconds / $divisor);
            if ($quot > 0) {
                $result[] = $quot . $name;
                $seconds = $seconds % $divisor;
            }
        }
        
        if (empty($result)) {
            return '0秒';
        }
        
        // 只显示最多两个单位，避免过于冗长
        return implode(' ', array_slice($result, 0, 2));
    }
    
    /**
     * Get all devices
     * 排序规则：1. 在线设备优先 2. 首次连接时间最新的在前
     */
    public function getAllDevices($limit = 1000, $offset = 0) {
        return $this->db->select(
            "SELECT *, encryption_enabled, last_key_exchange, encryption_cipher, key_id,
                    CASE 
                        WHEN last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 1 
                        ELSE 0 
                    END as is_online
             FROM devices 
             ORDER BY is_online DESC, first_seen DESC 
             LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
    }
    
    /**
     * 获取设备及其实时状态
     * 基于心跳时间实时计算设备状态，而不依赖数据库中的status字段
     * 排序规则：1. 在线设备优先 2. 首次连接时间最新的在前
     */
    public function getDevicesWithRealtimeStatus($limit = 1000, $offset = 0) {
        $devices = $this->db->select(
            "SELECT *, last_seen, NOW() as `current_time`,
                    CASE 
                        WHEN last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 1 
                        ELSE 0 
                    END as is_online
             FROM devices 
             ORDER BY is_online DESC, first_seen DESC 
             LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
        
        // 实时计算设备状态
        foreach ($devices as &$device) {
            // 使用PHP来计算时间差，避免MySQL时区问题
            $lastSeenTime = strtotime($device['last_seen']);
            $currentTime = time();
            $secondsSinceHeartbeat = $currentTime - $lastSeenTime;
            
            // 确保秒数不为负值
            if ($secondsSinceHeartbeat < 0) {
                $secondsSinceHeartbeat = 0;
            }
            
            // 120秒内有心跳视为在线（2倍心跳间隔：60秒*2），超过120秒视为离线
            if ($secondsSinceHeartbeat <= 120) {
                $device['realtime_status'] = 'online';
                $device['status_reason'] = 'Recent heartbeat';
            } else {
                $device['realtime_status'] = 'offline';
                $device['status_reason'] = "No heartbeat for {$secondsSinceHeartbeat} seconds";
            }
            
            // 保留数据库中的原始状态作为参考
            $device['db_status'] = $device['status'];
            // 使用实时计算的状态
            $device['status'] = $device['realtime_status'];
            
            // 更新计算后的秒数
            $device['seconds_since_heartbeat'] = $secondsSinceHeartbeat;
        }
        
        return $devices;
    }
    
    /**
     * Update device heartbeat
     * 根据设备ID、内网IP、外网IP的组合更新对应设备
     */
    public function updateHeartbeat($deviceId, $ipAddress = null, $systemInfo = null) {
        // 解析系统信息中的内网IP
        $internalIp = null;
        if ($systemInfo) {
            $sysInfo = json_decode($systemInfo, true);
            if ($sysInfo && isset($sysInfo['internal_ip'])) {
                $internalIp = $sysInfo['internal_ip'];
            }
        }
        
        // 查找匹配的设备
        $device = $this->findExistingDevice($deviceId, $ipAddress, $internalIp);
        
        if (!$device) {
            // 如果找不到完全匹配的设备，创建新设备记录
            return $this->registerDevice($deviceId, $systemInfo, $ipAddress);
        }
        
        $data = [
            'last_seen' => date('Y-m-d H:i:s'),
            'status' => 'online'
        ];
        
        if ($ipAddress) {
            $data['external_ip'] = $ipAddress;
            $data['ip_address'] = $ipAddress; // 向后兼容
        }
        
        if ($internalIp) {
            $data['internal_ip'] = $internalIp;
        }
        
        if ($systemInfo) {
            $data['system_info'] = $systemInfo;
        }
        
        // 检查设备冲突
        $this->checkDeviceConflicts($deviceId, $ipAddress, $internalIp);
        
        // 在更新当前设备状态的同时，检查其他设备是否应该标记为离线
        $this->checkAndMarkStaleDevices();
        
        return $this->db->update('devices', $data, 'id = :id', ['id' => $device['id']]);
    }
    
    /**
     * Update device system info
     */
    public function updateSystemInfo($deviceId, $systemInfo) {
        $data = [
            'system_info' => $systemInfo,
            'last_seen' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('devices', $data, 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    
    /**
     * Set device offline
     */
    public function setDeviceOffline($deviceId) {
        $data = ['status' => 'offline'];
        return $this->db->update('devices', $data, 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    
    /**
     * Update device notes
     */
    public function updateDeviceNotes($deviceId, $notes) {
        $data = ['notes' => $notes];
        return $this->db->update('devices', $data, 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    

    
    /**
     * Get online devices count
     */
    public function getOnlineDevicesCount() {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM devices WHERE status = 'online'"
        );
        return $result['count'];
    }
    
    /**
     * Get devices by status
     */
    public function getDevicesByStatus($status) {
        return $this->db->select(
            "SELECT *, encryption_enabled, last_key_exchange, encryption_cipher, key_id,
                    CASE 
                        WHEN last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 1 
                        ELSE 0 
                    END as is_online
             FROM devices 
             WHERE status = :status 
             ORDER BY is_online DESC, first_seen DESC",
            ['status' => $status]
        );
    }
    
    /**
     * Search devices
     */
    public function searchDevices($query) {
        $searchPattern = "%{$query}%";
        return $this->db->select(
            "SELECT *, encryption_enabled, last_key_exchange, encryption_cipher, key_id FROM devices WHERE 
             device_id LIKE :search OR 
             system_info LIKE :search OR 
             ip_address LIKE :search OR 
             notes LIKE :search 
             ORDER BY last_seen DESC",
            ['search' => $searchPattern]
        );
    }
    
    /**
     * Delete device
     */
    public function deleteDevice($deviceId) {
        return $this->db->delete('devices', 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    
    /**
     * Mark devices as offline if not seen for specified minutes
     */
    public function markStaleDevicesOffline($minutes = 2) { // 增加到2分钟，给更多缓冲时间
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));
        
        $data = ['status' => 'offline'];
        return $this->db->update(
            'devices', 
            $data, 
            'last_seen < :cutoff_time AND status = :online_status',
            ['cutoff_time' => $cutoffTime, 'online_status' => 'online']
        );
    }
    
    /**
     * 检查设备冲突 (向后兼容版本)
     */
    public function checkDeviceConflicts($deviceId = null, $externalIp = null, $internalIp = null) {
        // 如果没有传参数，使用旧方法检查冲突
        if ($deviceId === null) {
            $sql = "SELECT device_id, COUNT(*) as count, GROUP_CONCAT(COALESCE(external_ip, ip_address)) as ip_addresses 
                    FROM devices 
                    WHERE status = 'online' 
                    GROUP BY device_id 
                    HAVING count > 1";
            
            return $this->db->select($sql);
        }
        
        // 新的冲突检测逻辑
        $currentTime = date('Y-m-d H:i:s');
        $conflicts = [];
        
        try {
            // 检查数据库是否有新字段
            $hasNewFields = $this->checkIfNewFieldsExist();
            
            if ($hasNewFields) {
                // 使用新字段进行检测
                $conflicts = $this->performAdvancedConflictCheck($deviceId, $externalIp, $internalIp);
            } else {
                // 使用旧字段进行基本检测
                $conflicts = $this->performBasicConflictCheck($deviceId, $externalIp);
            }
        } catch (Exception $e) {
            error_log("Conflict detection error: " . $e->getMessage());
            // 如果出错，返回空数组避免阻塞心跳
            return [];
        }
        
        return $conflicts;
    }
    
    /**
     * 检查数据库是否有新字段
     */
    private function checkIfNewFieldsExist() {
        try {
            $result = $this->db->selectOne("SHOW COLUMNS FROM devices LIKE 'external_ip'");
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 基本冲突检测（使用旧字段）
     */
    private function performBasicConflictCheck($deviceId, $externalIp) {
        $conflicts = [];
        
        // 检查相同设备ID但不同IP的情况
        $duplicateIds = $this->db->select(
            "SELECT * FROM devices WHERE device_id = :device_id AND 
             ip_address != :ip_address AND status = 'online' AND 
             last_seen > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [
                'device_id' => $deviceId,
                'ip_address' => $externalIp ?: ''
            ]
        );
        
        foreach ($duplicateIds as $conflictDevice) {
            $conflicts[] = [
                'type' => 'duplicate_id',
                'device_id' => $deviceId,
                'conflicting_device_id' => $deviceId,
                'device_ip' => $externalIp,
                'conflicting_ip' => $conflictDevice['ip_address'],
                'details' => "Same device ID '{$deviceId}' detected from different IPs: {$externalIp} vs {$conflictDevice['ip_address']}"
            ];
        }
        
        return $conflicts;
    }
    
    /**
     * 高级冲突检测（使用新字段）
     */
    private function performAdvancedConflictCheck($deviceId, $externalIp, $internalIp) {
        $currentTime = date('Y-m-d H:i:s');
        $conflicts = [];
        
        // 1. 检查相同设备ID但不同IP的冲突
        $duplicateIds = $this->db->select(
            "SELECT * FROM devices WHERE device_id = :device_id AND 
             (external_ip != :external_ip OR internal_ip != :internal_ip) AND 
             status = 'online' AND last_seen > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [
                'device_id' => $deviceId,
                'external_ip' => $externalIp ?: '',
                'internal_ip' => $internalIp ?: ''
            ]
        );
        
        if (!empty($duplicateIds)) {
            foreach ($duplicateIds as $conflictDevice) {
                $conflicts[] = [
                    'type' => 'duplicate_id',
                    'device_id' => $deviceId,
                    'conflicting_device_id' => $deviceId,
                    'device_ip' => $externalIp,
                    'conflicting_ip' => $conflictDevice['external_ip'],
                    'internal_ip' => $internalIp,
                    'conflicting_internal_ip' => $conflictDevice['internal_ip'],
                    'details' => "Same device ID '{$deviceId}' detected from different IPs: {$externalIp} vs {$conflictDevice['external_ip']}, Internal: {$internalIp} vs {$conflictDevice['internal_ip']}"
                ];
            }
        }
        
        // 2. 检查相同内网IP但不同设备ID的冲突
        if ($internalIp) {
            $duplicateInternalIps = $this->db->select(
                "SELECT * FROM devices WHERE internal_ip = :internal_ip AND 
                 device_id != :device_id AND status = 'online' AND 
                 last_seen > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
                [
                    'internal_ip' => $internalIp,
                    'device_id' => $deviceId
                ]
            );
            
            if (!empty($duplicateInternalIps)) {
                foreach ($duplicateInternalIps as $conflictDevice) {
                    $conflicts[] = [
                        'type' => 'ip_mismatch',
                        'device_id' => $deviceId,
                        'conflicting_device_id' => $conflictDevice['device_id'],
                        'device_ip' => $externalIp,
                        'conflicting_ip' => $conflictDevice['external_ip'],
                        'internal_ip' => $internalIp,
                        'conflicting_internal_ip' => $internalIp,
                        'details' => "Same internal IP '{$internalIp}' used by different devices: {$deviceId} vs {$conflictDevice['device_id']}"
                    ];
                }
            }
        }
        
        // 记录冲突并更新设备状态
        if (!empty($conflicts)) {
            foreach ($conflicts as $conflict) {
                $this->recordDeviceConflict($conflict);
            }
            
            // 更新设备冲突状态
            $this->updateConflictStatus($deviceId, 'confirmed', $currentTime, count($conflicts) . ' conflicts detected');
            
            // 也更新冲突设备的状态
            foreach ($conflicts as $conflict) {
                if ($conflict['conflicting_device_id'] !== $deviceId) {
                    $this->updateConflictStatus($conflict['conflicting_device_id'], 'confirmed', $currentTime, 'Conflict with ' . $deviceId);
                }
            }
        } else {
            // 没有冲突，清除冲突状态
            $this->updateConflictStatus($deviceId, 'none', null, null);
        }
        
        return $conflicts;
    }
    
    /**
     * 记录设备冲突到数据库
     */
    private function recordDeviceConflict($conflict) {
        // 检查冲突表是否存在
        try {
            $tableExists = $this->db->selectOne("SHOW TABLES LIKE 'device_conflicts'");
            if (!$tableExists) {
                return false; // 表不存在，跳过记录
            }
            
            $data = [
                'device_id' => $conflict['device_id'],
                'conflict_type' => $conflict['type'],
                'conflicting_device_id' => $conflict['conflicting_device_id'],
                'device_ip' => $conflict['device_ip'],
                'conflicting_ip' => $conflict['conflicting_ip'],
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                'details' => $conflict['details'],
                'resolved' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return $this->db->insert('device_conflicts', $data);
        } catch (Exception $e) {
            error_log("Failed to record device conflict: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新设备冲突状态
     */
    private function updateConflictStatus($deviceId, $status, $detectedAt, $details) {
        try {
            // 检查字段是否存在
            $hasConflictFields = $this->db->selectOne("SHOW COLUMNS FROM devices LIKE 'conflict_status'");
            if (!$hasConflictFields) {
                return false; // 字段不存在，跳过更新
            }
            
            $data = [
                'conflict_status' => $status,
                'conflict_detected_at' => $detectedAt,
                'conflict_details' => $details
            ];
            
            return $this->db->update('devices', $data, 'device_id = :device_id', ['device_id' => $deviceId]);
        } catch (Exception $e) {
            error_log("Failed to update conflict status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取所有设备冲突
     */
    public function getAllConflicts($includeResolved = false) {
        try {
            // 检查冲突表是否存在
            $tableExists = $this->db->selectOne("SHOW TABLES LIKE 'device_conflicts'");
            if (!$tableExists) {
                // 如果冲突表不存在，使用旧方法
                return $this->checkDeviceConflicts(null);
            }
            
            $whereClause = $includeResolved ? '' : 'WHERE resolved = 0';
            return $this->db->select("SELECT * FROM device_conflicts {$whereClause} ORDER BY created_at DESC");
        } catch (Exception $e) {
            error_log("Failed to get conflicts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 解决冲突
     */
    public function resolveConflict($conflictId) {
        $data = [
            'resolved' => true,
            'resolved_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('device_conflicts', $data, 'id = :id', ['id' => $conflictId]);
    }
    
    /**
     * 获取设备统计信息
     */
    public function getDeviceStats() {
        $stats = [];
        
        // 总设备数
        $stats['total_devices'] = $this->db->selectOne("SELECT COUNT(*) as count FROM devices")['count'];
        
        // 在线设备数
        $stats['online_devices'] = $this->db->selectOne("SELECT COUNT(*) as count FROM devices WHERE status = 'online'")['count'];
        
        // 离线设备数
        $stats['offline_devices'] = $this->db->selectOne("SELECT COUNT(*) as count FROM devices WHERE status = 'offline'")['count'];
        
        // 按型号统计
        $modelStats = $this->db->select("
            SELECT 
                CASE 
                    WHEN system_info IS NOT NULL AND system_info != '' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.model')), 'Unknown')
                    ELSE 'Unknown'
                END as model,
                COUNT(*) as count 
            FROM devices 
            GROUP BY model 
            ORDER BY count DESC
        ");
        $stats['models'] = $modelStats;
        
        // 按版本统计
        $versionStats = $this->db->select("
            SELECT 
                CASE 
                    WHEN system_info IS NOT NULL AND system_info != '' THEN 
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.version')), 'Unknown')
                    ELSE 'Unknown'
                END as version,
                COUNT(*) as count 
            FROM devices 
            GROUP BY version 
            ORDER BY count DESC
        ");
        $stats['versions'] = $versionStats;
        
        // 按状态统计
        $statusStats = $this->db->select("
            SELECT status, COUNT(*) as count 
            FROM devices 
            GROUP BY status 
            ORDER BY count DESC
        ");
        $stats['statuses'] = $statusStats;
        
        return $stats;
    }
    
    /**
     * 根据筛选条件获取设备列表
     */
    public function getFilteredDevices($filters) {
        $where = ['1=1'];
        $params = [];
        
        // 设备ID筛选
        if (!empty($filters['device_id'])) {
            $where[] = "device_id LIKE :device_id";
            $params['device_id'] = '%' . $filters['device_id'] . '%';
        }
        
        // 型号筛选
        if (!empty($filters['model'])) {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.model')) LIKE :model";
            $params['model'] = '%' . $filters['model'] . '%';
        }
        
        // 版本筛选
        if (!empty($filters['version'])) {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.version')) LIKE :version";
            $params['version'] = '%' . $filters['version'] . '%';
        }
        
        // 状态筛选
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        $sql = "
            SELECT 
                device_id,
                system_info,
                ip_address,
                external_ip,
                status,
                last_seen,
                created_at,
                notes,
                sn,
                CASE 
                    WHEN system_info IS NOT NULL AND system_info != '' THEN 
                        JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.model'))
                    ELSE 'Unknown'
                END as model,
                CASE 
                    WHEN system_info IS NOT NULL AND system_info != '' THEN 
                        JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.version'))
                    ELSE 'Unknown'
                END as version
            FROM devices 
            WHERE " . implode(' AND ', $where) . "
            ORDER BY last_seen DESC
        ";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * 获取筛选后的设备数量
     */
    public function getFilteredDevicesCount($filters) {
        $where = ['1=1'];
        $params = [];
        
        // 设备ID筛选
        if (!empty($filters['device_id'])) {
            $where[] = "device_id LIKE :device_id";
            $params['device_id'] = '%' . $filters['device_id'] . '%';
        }
        
        // 型号筛选
        if (!empty($filters['model'])) {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.model')) LIKE :model";
            $params['model'] = '%' . $filters['model'] . '%';
        }
        
        // 版本筛选
        if (!empty($filters['version'])) {
            $where[] = "JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.version')) LIKE :version";
            $params['version'] = '%' . $filters['version'] . '%';
        }
        
        // 状态筛选
        if (!empty($filters['status'])) {
            $where[] = "status = :status";
            $params['status'] = $filters['status'];
        }
        
        $sql = "SELECT COUNT(*) as count FROM devices WHERE " . implode(' AND ', $where);
        $result = $this->db->selectOne($sql, $params);
        
        return $result['count'];
    }
    
    /**
     * 获取指定字段的所有可能值及其数量
     */
    public function getFieldValues($field) {
        switch ($field) {
            case 'model':
                return $this->db->select("
                    SELECT 
                        CASE 
                            WHEN system_info IS NOT NULL AND system_info != '' THEN 
                                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.model')), 'Unknown')
                            ELSE 'Unknown'
                        END as value,
                        COUNT(*) as count 
                    FROM devices 
                    GROUP BY value 
                    ORDER BY count DESC
                ");
                
            case 'version':
                return $this->db->select("
                    SELECT 
                        CASE 
                            WHEN system_info IS NOT NULL AND system_info != '' THEN 
                                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(system_info, '$.version')), 'Unknown')
                            ELSE 'Unknown'
                        END as value,
                        COUNT(*) as count 
                    FROM devices 
                    GROUP BY value 
                    ORDER BY count DESC
                ");
                
            case 'status':
                return $this->db->select("
                    SELECT status as value, COUNT(*) as count 
                    FROM devices 
                    GROUP BY status 
                    ORDER BY count DESC
                ");
                
            case 'device_id':
                return $this->db->select("
                    SELECT device_id as value, COUNT(*) as count 
                    FROM devices 
                    GROUP BY device_id
                    ORDER BY device_id
                ");
                
            default:
                throw new Exception("Invalid field: $field");
        }
    }
    
    /**
     * 更新设备加密状态
     */
    public function updateDeviceEncryptionStatus($deviceId, $enabled = true, $cipher = null, $keyId = null) {
        $data = [
            'encryption_enabled' => $enabled,
            'last_key_exchange' => $enabled ? date('Y-m-d H:i:s') : null
        ];
        
        if ($enabled && $cipher) {
            $data['encryption_cipher'] = $cipher;
        }
        
        if ($enabled && $keyId) {
            $data['key_id'] = $keyId;
        }
        
        return $this->db->update('devices', $data, 'device_id = :device_id', ['device_id' => $deviceId]);
    }
    
    /**
     * 获取设备加密状态
     */
    public function getDeviceEncryptionStatus($deviceId) {
        return $this->db->selectOne(
            "SELECT encryption_enabled, last_key_exchange, encryption_cipher, key_id 
             FROM devices WHERE device_id = :device_id",
            ['device_id' => $deviceId]
        );
    }
    
    /**
     * 获取所有设备列表（包含加密状态）
     * 排序规则：1. 在线设备优先 2. 首次连接时间最新的在前
     */
    public function getAllDevicesWithEncryption() {
        return $this->db->select(
            "SELECT *, 
                    encryption_enabled,
                    last_key_exchange,
                    encryption_cipher,
                    key_id,
                    TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_last_seen,
                    CASE 
                        WHEN last_seen > DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 1 
                        ELSE 0 
                    END as is_online
             FROM devices 
             ORDER BY is_online DESC, first_seen DESC"
        );
    }
    
    /**
     * 获取加密设备统计
     */
    public function getEncryptionStats() {
        $stats = [];
        
        // 总设备数
        $stats['total_devices'] = $this->db->selectOne("SELECT COUNT(*) as count FROM devices")['count'];
        
        // 启用加密的设备数
        $stats['encrypted_devices'] = $this->db->selectOne("SELECT COUNT(*) as count FROM devices WHERE encryption_enabled = TRUE")['count'];
        
        // 未启用加密的设备数
        $stats['unencrypted_devices'] = $stats['total_devices'] - $stats['encrypted_devices'];
        
        // 按加密算法统计
        $cipherStats = $this->db->select("
            SELECT encryption_cipher, COUNT(*) as count 
            FROM devices 
            WHERE encryption_enabled = TRUE AND encryption_cipher IS NOT NULL
            GROUP BY encryption_cipher 
            ORDER BY count DESC
        ");
        $stats['cipher_stats'] = $cipherStats;
        
        return $stats;
    }
    
    /**
     * 获取最新设备连接时间戳
     * 用于检测是否有新设备连接，避免不必要的界面刷新
     */
    public function getLatestDeviceTimestamp() {
        $result = $this->db->selectOne(
            "SELECT UNIX_TIMESTAMP(MAX(first_seen)) as latest_timestamp FROM devices"
        );
        return $result['latest_timestamp'] ?? 0;
    }
    
    /**
     * 检查是否有新设备连接
     * @param int $lastKnownTimestamp 客户端已知的最新时间戳
     * @return bool 是否有新设备
     */
    public function hasNewDevicesSince($lastKnownTimestamp) {
        $latestTimestamp = $this->getLatestDeviceTimestamp();
        return $latestTimestamp > $lastKnownTimestamp;
    }
    
    /**
     * 获取设备状态变化信息
     * 用于检测设备上线/离线状态变化
     */
    public function getDeviceStatusChangeInfo() {
        return [
            'latest_device_timestamp' => $this->getLatestDeviceTimestamp(),
            'online_count' => $this->getOnlineDevicesCount(),
            'total_count' => $this->getTotalDevicesCount()
        ];
    }
    
    /**
     * 获取总设备数量
     */
    public function getTotalDevicesCount() {
        $result = $this->db->selectOne("SELECT COUNT(*) as count FROM devices");
        return $result['count'];
    }
    
    /**
     * 获取总设备数量（重命名以保持一致性）
     */
    public function getTotalDeviceCount() {
        return $this->getTotalDevicesCount();
    }
    
    /**
     * 获取最近指定小时内活跃的设备数量
     */
    public function getRecentActiveDevicesCount($hours = 24) {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM devices 
             WHERE last_seen >= DATE_SUB(NOW(), INTERVAL :hours HOUR)",
            ['hours' => $hours]
        );
        return $result['count'];
    }
    
    /**
     * 获取最近指定天数内首次连接的新设备数量
     */
    public function getNewDevicesCount($days = 7) {
        $result = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM devices 
             WHERE first_seen >= DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $days]
        );
        return $result['count'];
    }
}
