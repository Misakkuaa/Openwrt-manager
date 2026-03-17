<?php
/**
 * Commands Management API
 */

header('Content-Type: application/json');
require_once '../classes/CommandManager.php';

$commandManager = new CommandManager();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // 获取特定指令详情
                $commandId = (int)$_GET['id'];
                $command = $commandManager->getCommandById($commandId);
                
                if (!$command) {
                    throw new Exception('Command not found');
                }
                
                $response = [
                    'status' => 'success',
                    'command' => $command
                ];
            } else {
                // 获取指令列表
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : null;
                
                if ($deviceId) {
                    $commands = $commandManager->getCommandHistory($deviceId, $limit);
                } else {
                    $commands = $commandManager->getAllCommands($limit, $offset);
                }
                
                $response = [
                    'status' => 'success',
                    'commands' => $commands
                ];
            }
            break;
            
        case 'DELETE':
            // 取消指令
            $input = json_decode(file_get_contents('php://input'), true);
            $commandId = $input['command_id'] ?? 0;
            
            if (!$commandId) {
                throw new Exception('Command ID is required');
            }
            
            $commandManager->cancelCommand($commandId);
            
            $response = [
                'status' => 'success',
                'message' => 'Command cancelled successfully'
            ];
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    http_response_code(400);
}

echo json_encode($response);
