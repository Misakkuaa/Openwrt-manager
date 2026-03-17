<?php
require_once '../config/config.php';
require_once '../classes/Database.php';

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = new Database();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetTemplates($db);
            break;
            
        case 'POST':
            handleCreateTemplate($db);
            break;
            
        case 'PUT':
            handleUpdateTemplate($db);
            break;
            
        case 'DELETE':
            handleDeleteTemplate($db);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Templates API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}

// 获取模板列表
function handleGetTemplates($db) {
    try {
        $stmt = $db->prepare("
            SELECT id, name, description, command, category, created_at, updated_at
            FROM command_templates 
            ORDER BY category, name
        ");
        
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'templates' => $templates
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to load templates: " . $e->getMessage());
    }
}

// 创建模板
function handleCreateTemplate($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }
        
        // 验证必需字段
        $required_fields = ['name', 'command'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || trim($input[$field]) === '') {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $name = trim($input['name']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        $command = trim($input['command']);
        $category = isset($input['category']) ? trim($input['category']) : 'custom';
        
        // 检查模板名称是否已存在
        $stmt = $db->prepare("SELECT COUNT(*) FROM command_templates WHERE name = ?");
        $stmt->execute([$name]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Template name already exists');
        }
        
        // 创建模板
        $stmt = $db->prepare("
            INSERT INTO command_templates (name, description, command, category, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        
        $success = $stmt->execute([$name, $description, $command, $category]);
        
        if (!$success) {
            throw new Exception('Failed to create template');
        }
        
        $template_id = $db->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Template created successfully',
            'template_id' => $template_id
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to create template: " . $e->getMessage());
    }
}

// 更新模板
function handleUpdateTemplate($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }
        
        // 验证必需字段
        $required_fields = ['id', 'name', 'command'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $id = (int)$input['id'];
        $name = trim($input['name']);
        $description = isset($input['description']) ? trim($input['description']) : '';
        $command = trim($input['command']);
        $category = isset($input['category']) ? trim($input['category']) : 'custom';
        
        if (empty($name) || empty($command)) {
            throw new Exception('Name and command cannot be empty');
        }
        
        // 检查模板是否存在
        $stmt = $db->prepare("SELECT COUNT(*) FROM command_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Template not found');
        }
        
        // 检查名称是否与其他模板冲突
        $stmt = $db->prepare("SELECT COUNT(*) FROM command_templates WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Template name already exists');
        }
        
        // 更新模板
        $stmt = $db->prepare("
            UPDATE command_templates 
            SET name = ?, description = ?, command = ?, category = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $success = $stmt->execute([$name, $description, $command, $category, $id]);
        
        if (!$success) {
            throw new Exception('Failed to update template');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Template updated successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to update template: " . $e->getMessage());
    }
}

// 删除模板
function handleDeleteTemplate($db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON data');
        }
        
        if (!isset($input['id'])) {
            throw new Exception('Missing template ID');
        }
        
        $id = (int)$input['id'];
        
        // 检查模板是否存在
        $stmt = $db->prepare("SELECT COUNT(*) FROM command_templates WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Template not found');
        }
        
        // 删除模板
        $stmt = $db->prepare("DELETE FROM command_templates WHERE id = ?");
        $success = $stmt->execute([$id]);
        
        if (!$success) {
            throw new Exception('Failed to delete template');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Template deleted successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to delete template: " . $e->getMessage());
    }
}
?>
