<?php
/**
 * Configuration File
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'misakku');
define('DB_PASS', 'misakkupassword');
define('DB_NAME', 'management');

// 安全配置
define('SECRET_KEY_FILE', __DIR__ . '/secret.key');
define('JWT_ALGORITHM', 'HS256');
define('TOKEN_LIFETIME', 24 * 60 * 60); // 24小时

// 时区配置
date_default_timezone_set('Asia/Shanghai');

// AES加密配置
define('AES_KEY_FILE', __DIR__ . '/aes.key');
define('AES_CIPHER', 'AES-256-CBC');
define('AES_KEY_LENGTH', 32); // 256位
define('AES_IV_LENGTH', 16); // 128位

// 系统配置
define('MAX_COMMAND_LENGTH', 1024);
define('MAX_OUTPUT_LENGTH', 1024 * 1024); // 1MB
define('HEARTBEAT_TIMEOUT', 300); // 5分钟
define('RATE_LIMIT_WINDOW', 300); // 5分钟
define('RATE_LIMIT_MAX_ATTEMPTS', 10);

// 日志配置
define('LOG_LEVEL', 'INFO');
define('LOG_FILE', '/var/log/owrt-server.log');

// 系统路径
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('TEMP_PATH', __DIR__ . '/../temp/');

// 创建必要的目录
if (!file_exists(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}

if (!file_exists(TEMP_PATH)) {
    @mkdir(TEMP_PATH, 0755, true);
}

// 错误报告设置
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    // 开发环境
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    define('DEBUG_MODE', true);
} else {
    // 生产环境或命令行环境
    error_reporting(0);
    ini_set('display_errors', 0);
    define('DEBUG_MODE', false);
}

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 自动加载函数
function owrt_log($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    if (defined('LOG_FILE') && is_writable(dirname(LOG_FILE))) {
        file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
    }
    
    if (DEBUG_MODE) {
        error_log($log_message);
    }
}

// 错误处理函数
function owrt_error_handler($errno, $errstr, $errfile, $errline) {
    owrt_log('ERROR', "PHP Error: {$errstr} in {$errfile} on line {$errline}");
    return false;
}

function owrt_exception_handler($exception) {
    owrt_log('ERROR', "Uncaught Exception: " . $exception->getMessage());
    
    if (!DEBUG_MODE) {
        // 生产环境不显示详细错误信息
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
        exit;
    }
}

// 注册错误处理函数
set_error_handler('owrt_error_handler');
set_exception_handler('owrt_exception_handler');

// 安全头设置
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    if (!DEBUG_MODE) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
