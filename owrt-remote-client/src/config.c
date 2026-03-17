#include "../include/owrt_client.h"
#include <stdlib.h>  // 确保 system() 函数可用
#include <string.h>  // 确保字符串函数可用

int load_config(owrt_config_t *config) {
    FILE *fp;
    char command[2048];  // 进一步增加缓冲区大小
    char result[1024];   // 进一步增加缓冲区大小
    
    // 设置默认值 - 写死配置，不依赖UCI
    strcpy(config->server_url, "http://azurebt.mswifi.online");  
    config->heartbeat_interval = 60;  
    config->max_retries = 3;
    config->use_encryption = 1;  // 写死启用加密
    strcpy(config->auth_token, "");
    strcpy(config->aes_key, "");
    
    owrt_log(LOG_INFO, "Configuration loaded (hardcoded): server_url=%s, heartbeat_interval=%d, max_retries=%d, use_encryption=%d", 
             config->server_url, config->heartbeat_interval, config->max_retries, config->use_encryption);
    return OWRT_SUCCESS;
}

int save_config(const owrt_config_t *config) {
    // 配置已写死在代码中，无需保存
    owrt_log(LOG_INFO, "Configuration is hardcoded, no save needed");
    return OWRT_SUCCESS;
}

int generate_device_id(char *device_id, size_t size) {
    FILE *fp;
    char mac_addr[32] = "";
    char cpu_info[64] = "";
    char serial[64] = "";
    unsigned char hash_input[256];
    int hash_len;
    
    // 尝试获取MAC地址
    fp = fopen("/sys/class/net/eth0/address", "r");
    if (fp) {
        fgets(mac_addr, sizeof(mac_addr), fp);
        fclose(fp);
        // 移除换行符
        mac_addr[strcspn(mac_addr, "\n")] = 0;
    }
    
    // 如果eth0不存在，尝试其他网络接口
    if (strlen(mac_addr) == 0) {
        fp = fopen("/sys/class/net/br-lan/address", "r");
        if (fp) {
            fgets(mac_addr, sizeof(mac_addr), fp);
            fclose(fp);
            mac_addr[strcspn(mac_addr, "\n")] = 0;
        }
    }
    
    // 获取CPU信息
    fp = fopen("/proc/cpuinfo", "r");
    if (fp) {
        char line[128];
        while (fgets(line, sizeof(line), fp)) {
            if (strncmp(line, "Hardware", 8) == 0 || 
                strncmp(line, "model name", 10) == 0) {
                char *colon = strchr(line, ':');
                if (colon) {
                    strncpy(cpu_info, colon + 2, sizeof(cpu_info) - 1);
                    cpu_info[strcspn(cpu_info, "\n")] = 0;
                    break;
                }
            }
        }
        fclose(fp);
    }
    
    // 尝试获取设备序列号
    fp = fopen("/proc/version", "r");
    if (fp) {
        fgets(serial, sizeof(serial), fp);
        fclose(fp);
        serial[strcspn(serial, "\n")] = 0;
    }
    
    // 创建哈希输入
    hash_len = snprintf((char*)hash_input, sizeof(hash_input), 
                       "%s|%s|%s", mac_addr, cpu_info, serial);
    
    // 生成简单的设备ID（实际项目中应使用更强的哈希算法）
    unsigned int hash = 0;
    for (int i = 0; i < hash_len; i++) {
        hash = hash * 31 + hash_input[i];
    }
    
    snprintf(device_id, size, "OWRT_%08X_%s", hash, mac_addr);
    
    owrt_log(LOG_INFO, "Generated device ID: %s", device_id);
    return OWRT_SUCCESS;
}

int load_device_id(char *device_id, size_t size) {
    FILE *fp;
    
    fp = fopen(DEVICE_ID_FILE, "r");
    if (!fp) {
        owrt_log(LOG_INFO, "Device ID file not found");
        return OWRT_ERROR_CONFIG;
    }
    
    if (fgets(device_id, size, fp) == NULL) {
        fclose(fp);
        owrt_log(LOG_ERR, "Failed to read device ID");
        return OWRT_ERROR_CONFIG;
    }
    
    // 移除换行符
    device_id[strcspn(device_id, "\n")] = 0;
    
    fclose(fp);
    owrt_log(LOG_INFO, "Loaded device ID: %s", device_id);
    return OWRT_SUCCESS;
}

int save_device_id(const char *device_id) {
    FILE *fp;
    
    fp = fopen(DEVICE_ID_FILE, "w");
    if (!fp) {
        owrt_log(LOG_ERR, "Failed to save device ID file");
        return OWRT_ERROR_CONFIG;
    }
    
    fprintf(fp, "%s\n", device_id);
    fclose(fp);
    
    owrt_log(LOG_INFO, "Device ID saved: %s", device_id);
    return OWRT_SUCCESS;
}

// 获取系统信息JSON格式 - 简化为设备型号字符串
int get_system_info_json(char *json_buf, size_t buf_size) {
    FILE *fp;
    char model[128] = "Unknown OpenWrt Device";
    char version[64] = "";
    char arch[64] = "";
    char internal_ip[64] = "";
    char sn[64] = "";
    char temp_buf[256];
    
    // 获取设备型号
    if ((fp = fopen("/proc/device-tree/model", "r")) != NULL) {
        if (fgets(model, sizeof(model), fp) != NULL) {
            model[strcspn(model, "\n")] = 0;
            // 清理可能的null字符
            for (int i = 0; model[i]; i++) {
                if (model[i] == '\0') {
                    model[i] = ' ';
                }
            }
        }
        fclose(fp);
    } else {
        // 尝试从其他位置获取型号信息
        if ((fp = popen("cat /tmp/sysinfo/model 2>/dev/null || echo 'Unknown'", "r")) != NULL) {
            if (fgets(model, sizeof(model), fp) != NULL) {
                model[strcspn(model, "\n")] = 0;
            }
            pclose(fp);
        }
    }
    
    // 获取DISTRIB_TARGET版本信息
    if ((fp = popen("grep '^DISTRIB_TARGET=' /etc/openwrt_release 2>/dev/null | tail -1 | cut -d'=' -f2 | tr -d \"'\\\"\" || echo ''", "r")) != NULL) {
        if (fgets(version, sizeof(version), fp) != NULL) {
            version[strcspn(version, "\n")] = 0;
        }
        pclose(fp);
    }
    
    // 如果没有获取到DISTRIB_TARGET，尝试其他方式
    if (strlen(version) == 0) {
        if ((fp = popen("cat /etc/openwrt_version 2>/dev/null || cat /etc/openwrt_release 2>/dev/null | head -1 | cut -d'=' -f2 | tr -d '\"' || echo ''", "r")) != NULL) {
            if (fgets(version, sizeof(version), fp) != NULL) {
                version[strcspn(version, "\n")] = 0;
            }
            pclose(fp);
        }
    }
    
    // 获取架构信息
    if ((fp = popen("uname -m 2>/dev/null || echo ''", "r")) != NULL) {
        if (fgets(arch, sizeof(arch), fp) != NULL) {
            arch[strcspn(arch, "\n")] = 0;
        }
        pclose(fp);
    }
    
    // 获取内网IP地址
    if ((fp = popen("ip route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}' || ip route show default 2>/dev/null | awk '{print $5; exit}' | xargs ip addr show 2>/dev/null | grep 'inet ' | head -1 | awk '{print $2}' | cut -d'/' -f1 || echo ''", "r")) != NULL) {
        if (fgets(internal_ip, sizeof(internal_ip), fp) != NULL) {
            internal_ip[strcspn(internal_ip, "\n")] = 0;
        }
        pclose(fp);
    }
    
    // 如果上面的方法失败，尝试更简单的方法
    if (strlen(internal_ip) == 0) {
        if ((fp = popen("ifconfig | grep 'inet addr' | grep -v '127.0.0.1' | head -1 | awk '{print $2}' | cut -d':' -f2 || ip addr show | grep 'inet ' | grep -v '127.0.0.1' | head -1 | awk '{print $2}' | cut -d'/' -f1 || echo ''", "r")) != NULL) {
            if (fgets(internal_ip, sizeof(internal_ip), fp) != NULL) {
                internal_ip[strcspn(internal_ip, "\n")] = 0;
            }
            pclose(fp);
        }
    }
    
    // 获取设备序列号 (SN)
    if ((fp = popen("strings /dev/mtd1ro 2>/dev/null | grep -o 'SN=[^ ]*' | head -1 | cut -d'=' -f2 || echo ''", "r")) != NULL) {
        if (fgets(sn, sizeof(sn), fp) != NULL) {
            sn[strcspn(sn, "\n")] = 0;
        }
        pclose(fp);
    }
    
    // 构建JSON格式的系统信息
    if (strlen(version) > 0 && strlen(arch) > 0) {
        snprintf(json_buf, buf_size, 
                 "{\"model\":\"%s\",\"version\":\"%s\",\"arch\":\"%s\",\"internal_ip\":\"%s\",\"sn\":\"%s\"}", 
                 model, version, arch, internal_ip, strlen(sn) > 0 ? sn : "none");
    } else if (strlen(version) > 0) {
        snprintf(json_buf, buf_size, 
                 "{\"model\":\"%s\",\"version\":\"%s\",\"arch\":\"\",\"internal_ip\":\"%s\",\"sn\":\"%s\"}", 
                 model, version, internal_ip, strlen(sn) > 0 ? sn : "none");
    } else {
        snprintf(json_buf, buf_size, 
                 "{\"model\":\"%s\",\"version\":\"\",\"arch\":\"%s\",\"internal_ip\":\"%s\",\"sn\":\"%s\"}", 
                 model, arch, internal_ip, strlen(sn) > 0 ? sn : "none");
    }
    
    return OWRT_SUCCESS;
}
