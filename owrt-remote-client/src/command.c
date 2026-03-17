#include "../include/owrt_client.h"
#include <stdarg.h>

int is_command_allowed(const char *command);

int execute_command(const char *command, char *output, size_t output_size) {
    FILE *fp;
    char buffer[256];
    size_t total_len = 0;
    int exit_code = -1;
    
    // 清空输出缓冲区
    output[0] = '\0';
    
    // 根据用户要求：没有指令安全限制，直接执行命令
    owrt_log(LOG_INFO, "Executing command: %.100s", command);
    
    // 执行命令
    fp = popen(command, "r");
    if (fp == NULL) {
        snprintf(output, output_size, "Error: Failed to execute command");
        owrt_log(LOG_ERR, "Failed to execute command: %s", command);
        return -1;
    }
    
    // 读取命令输出
    while (fgets(buffer, sizeof(buffer), fp) != NULL && 
           total_len < output_size - 1) {
        size_t len = strlen(buffer);
        if (total_len + len < output_size - 1) {
            strcat(output, buffer);
            total_len += len;
        } else {
            // 截断过长的输出
            strncat(output, buffer, output_size - total_len - 1);
            break;
        }
    }
    
    // 获取退出码
    exit_code = pclose(fp);
    exit_code = WEXITSTATUS(exit_code);
    
    owrt_log(LOG_INFO, "Command executed, exit code: %d, output length: %zu", 
             exit_code, strlen(output));
    
    return exit_code;
}

int is_command_allowed(const char *command) {
    // 允许的命令白名单
    static const char *allowed_commands[] = {
        "uci",
        "ifconfig",
        "iwconfig",
        "iwlist",
        "ps",
        "top",
        "free",
        "df",
        "uptime",
        "cat /proc/",
        "cat /sys/",
        "ls",
        "netstat",
        "route",
        "iptables -L",
        "logread",
        "/etc/init.d/",
        "opkg list-installed",
        "opkg info",
        NULL
    };
    
    // 危险命令黑名单
    static const char *blocked_commands[] = {
        "rm ",
        "rm\t",
        "rmdir",
        "dd ",
        "dd\t",
        "mkfs",
        "format",
        "fdisk",
        "passwd",
        "su ",
        "su\t",
        "sudo",
        "chmod +x",
        "wget",
        "curl",
        "nc ",
        "nc\t",
        "netcat",
        "tftp",
        "scp",
        "ssh",
        "telnet",
        ">",
        ">>",
        "&",
        ";",
        "|",
        "`",
        "$(",
        "$()",
        NULL
    };
    
    // 检查黑名单
    for (int i = 0; blocked_commands[i] != NULL; i++) {
        if (strstr(command, blocked_commands[i]) != NULL) {
            return 0;
        }
    }
    
    // 检查白名单
    for (int i = 0; allowed_commands[i] != NULL; i++) {
        if (strncmp(command, allowed_commands[i], strlen(allowed_commands[i])) == 0) {
            return 1;
        }
    }
    
    return 0;
}

int verify_server_signature(const char *data, const char *signature) {
    // 这是一个简化的签名验证实现
    // 实际项目中应该使用更强的加密算法如RSA或ECDSA
    
    char expected_sig[128];
    unsigned int hash = 0;
    
    // 计算数据的简单哈希
    for (const char *p = data; *p; p++) {
        hash = hash * 31 + *p;
    }
    
    // 生成期望的签名（实际应该使用服务器的私钥签名）
    snprintf(expected_sig, sizeof(expected_sig), "SIG_%08X", hash);
    
    if (strcmp(signature, expected_sig) == 0) {
        owrt_log(LOG_DEBUG, "Signature verification successful");
        return OWRT_SUCCESS;
    }
    
    owrt_log(LOG_WARNING, "Signature verification failed");
    return OWRT_ERROR_AUTH;
}

int get_system_info(char *info, size_t size) {
    FILE *fp;
    char version[64] = "";
    char model[64] = "";
    char arch[32] = "";
    
    // 获取OpenWrt版本
    fp = fopen("/etc/openwrt_release", "r");
    if (fp) {
        char line[128];
        while (fgets(line, sizeof(line), fp)) {
            if (strncmp(line, "DISTRIB_RELEASE=", 16) == 0) {
                strncpy(version, line + 16, sizeof(version) - 1);
                version[strcspn(version, "\n'")] = 0;
                break;
            }
        }
        fclose(fp);
    }
    
    // 获取设备型号
    fp = fopen("/proc/device-tree/model", "r");
    if (fp) {
        fgets(model, sizeof(model), fp);
        fclose(fp);
        model[strcspn(model, "\n")] = 0;
    }
    
    // 获取架构信息
    fp = fopen("/proc/cpuinfo", "r");
    if (fp) {
        char line[128];
        while (fgets(line, sizeof(line), fp)) {
            if (strncmp(line, "machine", 7) == 0 || 
                strncmp(line, "Hardware", 8) == 0) {
                char *colon = strchr(line, ':');
                if (colon) {
                    strncpy(arch, colon + 2, sizeof(arch) - 1);
                    arch[strcspn(arch, "\n")] = 0;
                    break;
                }
            }
        }
        fclose(fp);
    }
    
    snprintf(info, size, "OpenWrt %s|%s|%s", version, model, arch);
    return OWRT_SUCCESS;
}

// 处理服务器命令 - 匹配Shell脚本格式
int process_server_commands(json_object *json_obj, const owrt_config_t *config) {
    json_object *commands_obj;
    
    if (!json_object_object_get_ex(json_obj, "commands", &commands_obj)) {
        owrt_log(LOG_DEBUG, "No commands in server response");
        return OWRT_SUCCESS;
    }
    
    if (!json_object_is_type(commands_obj, json_type_array)) {
        owrt_log(LOG_WARNING, "Commands field is not an array");
        return OWRT_ERROR_SYSTEM;
    }
    
    int array_len = json_object_array_length(commands_obj);
    owrt_log(LOG_INFO, "Processing %d commands from server", array_len);
    
    for (int i = 0; i < array_len; i++) {
        json_object *cmd_obj = json_object_array_get_idx(commands_obj, i);
        if (cmd_obj) {
            const char *command = json_object_get_string(cmd_obj);
            if (command && strlen(command) > 0) {
                char output[MAX_BUFFER_SIZE];
                int exit_code = execute_command(command, output, sizeof(output));
                owrt_log(LOG_INFO, "Command '%s' executed with exit code: %d", command, exit_code);
                
                // 在实际实现中，可能需要将命令结果发送回服务器
                // 这里只是记录日志
            }
        }
    }
    
    return OWRT_SUCCESS;
}
