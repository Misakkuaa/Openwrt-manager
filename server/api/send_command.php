<?php
/**
 * Send Command API
 */

header('Content-Type: application/json');
require_once '../classes/DeviceManager.php';
require_once '../classes/CommandManager.php';
require_once '../classes/AuthManager.php';

$deviceManager = new DeviceManager();
$commandManager = new CommandManager();
$authManager = new AuthManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $deviceId = $input['device_id'] ?? '';
    $command = $input['command'] ?? '';
    $userId = $input['user_id'] ?? null;
    
    if (empty($deviceId) || empty($command)) {
        throw new Exception('Device ID and command are required');
    }
    
    // Check if device exists and is online
    $device = $deviceManager->getDevice($deviceId);
    if (!$device) {
        throw new Exception('Device not found');
    }
    
    if ($device['status'] !== 'online') {
        throw new Exception('Device is not online');
    }
    
    // Send command
    $commandId = $commandManager->sendCommand($deviceId, $command, $userId);
    
    // Log command sending
    $authManager->logSecurityEvent(
        $deviceId, 
        'command_sent', 
        "Command: {$command}", 
        $_SERVER['REMOTE_ADDR']
    );
    
    $response = [
        'status' => 'success',
        'command_id' => $commandId,
        'message' => 'Command sent successfully'
    ];
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
