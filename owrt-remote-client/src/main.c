#include "../include/owrt_client.h"

static owrt_config_t g_config;
static int g_running = 1;

int main(int argc, char *argv[]) {
    int ret;
    
    // 初始化日志系统
    init_logging();
    owrt_log(LOG_INFO, "OpenWrt Remote Client starting...");
    
    // 初始化客户端
    ret = owrt_init();
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to initialize client: %d", ret);
        cleanup_logging();
        return ret;
    }
    
    // 进入主循环
    ret = owrt_main_loop();
    
    // 清理资源
    owrt_cleanup();
    cleanup_logging();
    
    owrt_log(LOG_INFO, "OpenWrt Remote Client stopped");
    
    return ret;
}

int owrt_init(void) {
    int ret;
    
    // 加载配置
    ret = load_config(&g_config);
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to load configuration");
        return ret;
    }
    
    // 加载或生成设备ID（使用MAC地址）
    ret = get_device_id_from_mac(g_config.device_id, sizeof(g_config.device_id));
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to generate device ID from MAC address");
        return ret;
    }
    
    // 保存设备ID
    save_device_id(g_config.device_id);
    
    // 初始化CURL
    curl_global_init(CURL_GLOBAL_DEFAULT);
    
    // 与服务器进行认证 (如果失败，将在主循环中重试)
    ret = authenticate_with_server(&g_config);
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_WARNING, "Initial authentication failed, will retry in main loop");
        // 不立即退出，在主循环中重试
    } else {
        owrt_log(LOG_INFO, "Initial authentication successful");
    }
    
    owrt_log(LOG_INFO, "Client initialized successfully, Device ID: %s", g_config.device_id);
    return OWRT_SUCCESS;
}

int owrt_cleanup(void) {
    curl_global_cleanup();
    return OWRT_SUCCESS;
}

int owrt_main_loop(void) {
    time_t last_heartbeat = 0;
    time_t last_auth_attempt = 0;
    time_t current_time;
    int retry_count = 0;
    int auth_success = 0;
    
    owrt_log(LOG_INFO, "Entering main loop...");
    
    // 检查初始认证状态
    if (strlen(g_config.auth_token) > 0) {
        auth_success = 1;
        owrt_log(LOG_INFO, "Using existing authentication token");
    }
    
    while (g_running) {
        current_time = time(NULL);
        
        // 如果还未认证成功，立即尝试认证（第一次）或者等待30秒后重试
        if (!auth_success && (last_auth_attempt == 0 || current_time - last_auth_attempt >= 30)) {
            owrt_log(LOG_INFO, "Attempting authentication...");
            if (authenticate_with_server(&g_config) == OWRT_SUCCESS) {
                auth_success = 1;
                owrt_log(LOG_INFO, "Authentication successful");
            } else {
                owrt_log(LOG_WARNING, "Authentication failed, will retry in 30 seconds");
            }
            last_auth_attempt = current_time;
        }
        
        // 只有认证成功后才发送心跳
        if (auth_success && (current_time - last_heartbeat >= g_config.heartbeat_interval)) {
            int ret = send_heartbeat(&g_config);
            if (ret == OWRT_SUCCESS) {
                last_heartbeat = current_time;
                retry_count = 0;
                owrt_log(LOG_DEBUG, "Heartbeat sent successfully");
            } else {
                retry_count++;
                owrt_log(LOG_WARNING, "Heartbeat failed, retry count: %d", retry_count);
                
                if (retry_count >= g_config.max_retries) {
                    owrt_log(LOG_ERR, "Max retries reached, need re-authentication");
                    auth_success = 0;  // 标记需要重新认证
                    retry_count = 0;
                }
            }
        }
        
        // 系统维护（内存检查、临时文件清理等）
        system_maintenance();
        
        // 短暂休眠
        sleep(1);
    }
    
    return OWRT_SUCCESS;
}

// 系统维护函数
void system_maintenance(void) {
    static time_t last_maintenance = 0;
    time_t current_time = time(NULL);
    
    // 每5分钟执行一次维护
    if (current_time - last_maintenance >= 300) {
        owrt_log(LOG_DEBUG, "Performing system maintenance...");
        
        // 清理临时文件
        system("find /tmp -name 'owrt_*' -mtime +1 -delete 2>/dev/null");
        
        // 检查内存使用情况
        FILE *fp = fopen("/proc/meminfo", "r");
        if (fp) {
            char line[256];
            long mem_total = 0, mem_free = 0;
            
            while (fgets(line, sizeof(line), fp)) {
                if (sscanf(line, "MemTotal: %ld kB", &mem_total) == 1) continue;
                if (sscanf(line, "MemAvailable: %ld kB", &mem_free) == 1) break;
            }
            fclose(fp);
            
            if (mem_total > 0 && mem_free > 0) {
                int mem_usage = (int)((mem_total - mem_free) * 100 / mem_total);
                if (mem_usage > 90) {
                    owrt_log(LOG_WARNING, "High memory usage: %d%%", mem_usage);
                }
            }
        }
        
        last_maintenance = current_time;
        owrt_log(LOG_DEBUG, "System maintenance completed");
    }
}
