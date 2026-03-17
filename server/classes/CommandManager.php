<?php
/**
 * Command Management Class
 */

require_once 'Database.php';

class CommandManager {
    private $db;
    private $allowedCommands;
    
    public function __construct() {
        $this->db = new Database();
        $this->allowedCommands = $this->loadAllowedCommands();
    }
    
    /**
     * Send command to device
     */
    public function sendCommand($deviceId, $command, $userId = null) {
        // Validate command
        if (!$this->isCommandAllowed($command)) {
            throw new Exception('Command not allowed for security reasons');
        }
        
        // Create command record
        $commandId = $this->createCommandRecord($deviceId, $command, $userId);
        
        // Store command for device to pick up
        $this->storeCommandForDevice($deviceId, $commandId, $command);
        
        return $commandId;
    }
    
    /**
     * Get pending command for device
     */
    public function getPendingCommand($deviceId) {
        $command = $this->db->selectOne(
            "SELECT * FROM device_commands 
             WHERE device_id = :device_id AND status = 'pending' 
             ORDER BY created_at ASC LIMIT 1",
            ['device_id' => $deviceId]
        );
        
        if ($command) {
            // Mark as sent
            $this->updateCommandStatus($command['id'], 'sent');
        }
        
        return $command;
    }
    
    /**
     * Store command result
     */
    public function storeCommandResult($deviceId, $commandId, $exitCode, $output) {
        error_log("Storing command result - device_id: $deviceId, command_id: $commandId, exit_code: $exitCode");
        
        // Update command record
        $data = [
            'status' => ($exitCode == 0) ? 'completed' : 'failed',
            'exit_code' => $exitCode,
            'output' => $output,
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        $affected_rows = $this->db->update('device_commands', $data, 'id = :id AND device_id = :device_id', [
            'id' => $commandId,
            'device_id' => $deviceId
        ]);
        
        error_log("Command result update affected rows: " . ($affected_rows ? $affected_rows->rowCount() : 'NULL'));
        
        return true;
    }
    
    /**
     * Get command history for device
     */
    public function getCommandHistory($deviceId, $limit = 50) {
        return $this->db->select(
            "SELECT * FROM device_commands 
             WHERE device_id = :device_id 
             ORDER BY created_at DESC LIMIT :limit",
            ['device_id' => $deviceId, 'limit' => $limit]
        );
    }
    
    /**
     * Get all commands with pagination
     */
    public function getAllCommands($limit = 100, $offset = 0) {
        return $this->db->select(
            "SELECT dc.*, d.device_id, d.system_info 
             FROM device_commands dc 
             JOIN devices d ON dc.device_id = d.device_id 
             ORDER BY dc.created_at DESC LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset]
        );
    }
    
    /**
     * Create quick command templates
     */
    public function createCommandTemplate($name, $command, $description = '') {
        $data = [
            'name' => $name,
            'command' => $command,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('command_templates', $data);
    }
    
    /**
     * Get command templates
     */
    public function getCommandTemplates() {
        return $this->db->select("SELECT * FROM command_templates ORDER BY name");
    }
    
    /**
     * Execute template command on device
     */
    public function executeTemplate($templateId, $deviceId, $userId = null) {
        $template = $this->db->selectOne(
            "SELECT * FROM command_templates WHERE id = :id",
            ['id' => $templateId]
        );
        
        if (!$template) {
            throw new Exception('Command template not found');
        }
        
        return $this->sendCommand($deviceId, $template['command'], $userId);
    }
    
    /**
     * Get command statistics
     */
    public function getCommandStats($deviceId = null, $days = 7) {
        $whereClause = '';
        $params = ['since' => date('Y-m-d H:i:s', strtotime("-{$days} days"))];
        
        if ($deviceId) {
            $whereClause = ' AND device_id = :device_id';
            $params['device_id'] = $deviceId;
        }
        
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_duration
                FROM device_commands 
                WHERE created_at > :since {$whereClause}
                GROUP BY status";
        
        return $this->db->select($sql, $params);
    }
    
    /**
     * Cancel pending command
     */
    public function cancelCommand($commandId) {
        $data = [
            'status' => 'cancelled',
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->update('device_commands', $data, 'id = :id AND status IN (:pending, :sent)', [
            'id' => $commandId,
            'pending' => 'pending',
            'sent' => 'sent'
        ]);
    }
    
    /**
     * Load allowed commands from configuration
     */
    private function loadAllowedCommands() {
        return [
            // System information
            'uci show',
            'uci get',
            'ifconfig',
            'iwconfig',
            'iwlist scan',
            'ps aux',
            'top -n 1',
            'free -m',
            'df -h',
            'uptime',
            'cat /proc/cpuinfo',
            'cat /proc/meminfo',
            'cat /proc/version',
            'cat /sys/class/net/*/address',
            
            // Network commands
            'netstat -tuln',
            'route -n',
            'iptables -L -n',
            'ip route show',
            'ip addr show',
            
            // OpenWrt specific
            'logread',
            'logread -f',
            'opkg list-installed',
            'opkg info',
            '/etc/init.d/network restart',
            '/etc/init.d/wireless restart',
            '/etc/init.d/dnsmasq restart',
            '/etc/init.d/firewall restart',
            
            // File operations (read-only)
            'ls -la',
            'cat /etc/config/*',
            'cat /etc/passwd',
            'cat /etc/hosts',
            
            // Hardware info
            'lscpu',
            'lsusb',
            'lspci',
            'dmesg | tail -50'
        ];
    }
    
    /**
     * Check if command is allowed
     * 根据需求：没有指令安全限制，允许所有命令
     */
    private function isCommandAllowed($command) {
        // 移除所有安全限制，允许任何命令
        return true;
    }
    
    /**
     * Create command record in database
     */
    private function createCommandRecord($deviceId, $command, $userId) {
        $data = [
            'device_id' => $deviceId,
            'command' => $command,
            'status' => 'pending',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->db->insert('device_commands', $data);
    }
    
    /**
     * Store command for device to pick up
     */
    private function storeCommandForDevice($deviceId, $commandId, $command) {
        // This could be implemented using a separate queue table
        // or by updating the device_commands table with pending status
        return true;
    }
    
    /**
     * Update command status
     */
    private function updateCommandStatus($commandId, $status) {
        $data = ['status' => $status];
        if ($status === 'sent') {
            $data['sent_at'] = date('Y-m-d H:i:s');
        }
        
        return $this->db->update('device_commands', $data, 'id = :id', ['id' => $commandId]);
    }
    
    /**
     * Get command by ID
     */
    public function getCommandById($commandId) {
        return $this->db->selectOne(
            "SELECT dc.*, d.device_id, d.system_info 
             FROM device_commands dc 
             JOIN devices d ON dc.device_id = d.device_id 
             WHERE dc.id = :id",
            ['id' => $commandId]
        );
    }
}
