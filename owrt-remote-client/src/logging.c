/**
 * OpenWrt Client日志管理改进
 * 添加日志轮转和内存优化功能
 */

#include "../include/owrt_client.h"
#include <stdarg.h>
#include <sys/stat.h>

// 日志配置
#define MAX_LOG_SIZE (1024 * 1024)  // 1MB
#define MAX_LOG_FILES 3             // 保留3个日志文件
#define LOG_ROTATION_SIZE (512 * 1024)  // 512KB后开始轮转

static int log_initialized = 0;
static FILE *log_file = NULL;

// 初始化日志系统
int init_logging() {
    if (log_initialized) {
        return OWRT_SUCCESS;
    }
    
    // 使用syslog作为主要日志输出（OpenWrt系统会自动管理）
    openlog("owrt_client", LOG_PID | LOG_CONS, LOG_DAEMON);
    
    log_initialized = 1;
    return OWRT_SUCCESS;
}

// 清理日志系统
void cleanup_logging() {
    if (log_initialized) {
        closelog();
        if (log_file) {
            fclose(log_file);
            log_file = NULL;
        }
        log_initialized = 0;
    }
}

// 日志轮转函数
void rotate_log_files() {
    char old_log[256], new_log[256];
    
    // 删除最老的日志文件
    snprintf(old_log, sizeof(old_log), "%s.%d", LOG_FILE, MAX_LOG_FILES);
    unlink(old_log);
    
    // 移动现有的日志文件
    for (int i = MAX_LOG_FILES - 1; i > 0; i--) {
        snprintf(old_log, sizeof(old_log), "%s.%d", LOG_FILE, i);
        snprintf(new_log, sizeof(new_log), "%s.%d", LOG_FILE, i + 1);
        rename(old_log, new_log);
    }
    
    // 移动当前日志文件
    snprintf(new_log, sizeof(new_log), "%s.1", LOG_FILE);
    rename(LOG_FILE, new_log);
}

// 检查并执行日志轮转
void check_log_rotation() {
    struct stat st;
    if (stat(LOG_FILE, &st) == 0) {
        if (st.st_size > LOG_ROTATION_SIZE) {
            if (log_file) {
                fclose(log_file);
                log_file = NULL;
            }
            rotate_log_files();
        }
    }
}

// 改进的日志函数
void owrt_log(int level, const char *format, ...) {
    va_list args;
    char buffer[1024];
    time_t now;
    struct tm *timeinfo;
    
    if (!log_initialized) {
        init_logging();
    }
    
    va_start(args, format);
    vsnprintf(buffer, sizeof(buffer), format, args);
    va_end(args);
    
    // 主要使用syslog（OpenWrt系统自动管理）
    syslog(level, "%s", buffer);
    
    // 根据日志级别决定是否写入文件（减少I/O）
    if (level <= LOG_WARNING) {  // 只记录重要的日志到文件
        check_log_rotation();
        
        if (!log_file) {
            log_file = fopen(LOG_FILE, "a");
        }
        
        if (log_file) {
            time(&now);
            timeinfo = localtime(&now);
            
            fprintf(log_file, "[%04d-%02d-%02d %02d:%02d:%02d] [%d] %s\n",
                    timeinfo->tm_year + 1900, timeinfo->tm_mon + 1, timeinfo->tm_mday,
                    timeinfo->tm_hour, timeinfo->tm_min, timeinfo->tm_sec,
                    level, buffer);
            fflush(log_file);
        }
    }
}

// 清理临时文件和缓存
void cleanup_temp_files() {
    // 清理可能的临时文件
    unlink("/tmp/owrt_client.tmp");
    unlink("/tmp/owrt_auth.tmp");
    
    // 清理过期的设备ID备份
    unlink("/tmp/owrt_device_id.bak");
}

// 内存使用监控
void check_memory_usage() {
    FILE *fp;
    char line[256];
    long mem_available = 0;
    
    fp = fopen("/proc/meminfo", "r");
    if (fp) {
        while (fgets(line, sizeof(line), fp)) {
            if (strncmp(line, "MemAvailable:", 13) == 0) {
                sscanf(line, "MemAvailable: %ld kB", &mem_available);
                break;
            }
        }
        fclose(fp);
        
        // 如果可用内存低于10MB，记录警告
        if (mem_available < 10240) {
            owrt_log(LOG_WARNING, "Low memory warning: %ld KB available", mem_available);
            cleanup_temp_files();
        }
    }
}

// 系统资源监控（定期调用）
// 注：system_maintenance函数已移至main.c，避免重复定义
