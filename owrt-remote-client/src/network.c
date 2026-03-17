#include "../include/owrt_client.h"

// 获取MAC地址并生成设备ID
int get_device_id_from_mac(char *device_id, size_t max_len) {
    FILE *fp;
    char mac_addr[32] = {0};
    char formatted_mac[16] = {0};
    char salt_id[] = "ID";  // 盐值修改为ID
    char command[256];
    char *pos;
    
    owrt_log(LOG_DEBUG, "Starting device ID generation...");
    
    // 尝试多种方式获取MAC地址
    // 方式1: br-lan
    fp = popen("ifconfig br-lan 2>/dev/null | grep -oE '([[:xdigit:]]{1,2}:){5}[[:xdigit:]]{1,2}' | head -1", "r");
    if (fp != NULL && fgets(mac_addr, sizeof(mac_addr), fp) != NULL) {
        pclose(fp);
        owrt_log(LOG_DEBUG, "Got MAC from br-lan: %s", mac_addr);
    } else {
        if (fp) pclose(fp);
        
        // 方式2: eth0
        fp = popen("ifconfig eth0 2>/dev/null | grep -oE '([[:xdigit:]]{1,2}:){5}[[:xdigit:]]{1,2}' | head -1", "r");
        if (fp != NULL && fgets(mac_addr, sizeof(mac_addr), fp) != NULL) {
            pclose(fp);
            owrt_log(LOG_DEBUG, "Got MAC from eth0: %s", mac_addr);
        } else {
            if (fp) pclose(fp);
            
            // 方式3: ip link
            fp = popen("ip link show 2>/dev/null | awk '/link\\/ether/ {print $2}' | head -1", "r");
            if (fp != NULL && fgets(mac_addr, sizeof(mac_addr), fp) != NULL) {
                pclose(fp);
                owrt_log(LOG_DEBUG, "Got MAC from ip link: %s", mac_addr);
            } else {
                if (fp) pclose(fp);
                owrt_log(LOG_ERR, "Failed to get MAC address from any interface");
                
                // 备用方案：使用系统UUID或随机生成
                fp = popen("cat /proc/sys/kernel/random/uuid 2>/dev/null | tr -d '-' | head -c12", "r");
                if (fp != NULL && fgets(mac_addr, sizeof(mac_addr), fp) != NULL) {
                    pclose(fp);
                    owrt_log(LOG_WARNING, "Using fallback UUID-based ID: %s", mac_addr);
                } else {
                    if (fp) pclose(fp);
                    // 最后的备用方案
                    snprintf(mac_addr, sizeof(mac_addr), "FALLBACK%lld", (long long)(time(NULL) % 1000000));
                    owrt_log(LOG_WARNING, "Using timestamp-based fallback ID: %s", mac_addr);
                }
            }
        }
    }
    
    // 移除换行符
    if ((pos = strchr(mac_addr, '\n')) != NULL) {
        *pos = '\0';
    }
    
    // 如果是MAC地址格式，去掉冒号
    if (strchr(mac_addr, ':') != NULL) {
        int j = 0;
        for (int i = 0; mac_addr[i] && j < sizeof(formatted_mac) - 1; i++) {
            if (mac_addr[i] != ':') {
                formatted_mac[j++] = mac_addr[i];
            }
        }
        formatted_mac[j] = '\0';
    } else {
        // 直接使用
        strncpy(formatted_mac, mac_addr, sizeof(formatted_mac) - 1);
        formatted_mac[sizeof(formatted_mac) - 1] = '\0';
    }
    
    owrt_log(LOG_DEBUG, "Formatted identifier: %s", formatted_mac);
    
    // 生成MD5哈希的前8位作为设备ID
    snprintf(command, sizeof(command), "echo -n '%s%s' | md5sum | cut -c1-8", formatted_mac, salt_id);
    
    fp = popen(command, "r");
    if (fp == NULL) {
        owrt_log(LOG_ERR, "Failed to generate device ID hash");
        return OWRT_ERROR_SYSTEM;
    }
    
    if (fgets(device_id, max_len, fp) == NULL) {
        pclose(fp);
        owrt_log(LOG_ERR, "Failed to read device ID hash");
        return OWRT_ERROR_SYSTEM;
    }
    pclose(fp);
    
    // 移除换行符
    if ((pos = strchr(device_id, '\n')) != NULL) {
        *pos = '\0';
    }
    
    owrt_log(LOG_INFO, "Generated device ID: %s (from identifier: %s)", device_id, formatted_mac);
    return OWRT_SUCCESS;
}

// HTTP响应回调函数
size_t write_callback(void *contents, size_t size, size_t nmemb, http_response_t *response) {
    size_t total_size = size * nmemb;
    
    char *new_data = realloc(response->data, response->size + total_size + 1);
    if (!new_data) {
        owrt_log(LOG_ERR, "Failed to allocate memory for HTTP response");
        return 0;
    }
    
    response->data = new_data;
    memcpy(&(response->data[response->size]), contents, total_size);
    response->size += total_size;
    response->data[response->size] = '\0';
    
    return total_size;
}

// 发送HTTP请求
int send_http_request(const char *url, const char *post_data, const char *token, http_response_t *response) {
    CURL *curl;
    CURLcode res;
    struct curl_slist *headers = NULL;
    char auth_header[512];
    
    // 初始化响应
    response->data = malloc(1);
    response->size = 0;
    response->data[0] = '\0';
    
    curl = curl_easy_init();
    if (!curl) {
        owrt_log(LOG_ERR, "Failed to initialize CURL");
        free(response->data);
        return OWRT_ERROR_NETWORK;
    }
    
    // 设置URL
    curl_easy_setopt(curl, CURLOPT_URL, url);
    
    // 设置HTTP头
    headers = curl_slist_append(headers, "Content-Type: application/json");
    
    // 如果有token，添加认证头
    if (token && strlen(token) > 0) {
        snprintf(auth_header, sizeof(auth_header), "Authorization: Bearer %s", token);
        headers = curl_slist_append(headers, auth_header);
    }
    
    curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);
    
    // 如果有POST数据
    if (post_data) {
        curl_easy_setopt(curl, CURLOPT_POSTFIELDS, post_data);
    }
    
    // 设置响应回调
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, write_callback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, response);
    
    // 设置超时
    curl_easy_setopt(curl, CURLOPT_TIMEOUT, 30L);
    curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 10L);
    
    // 执行请求
    res = curl_easy_perform(curl);
    
    // 清理
    curl_slist_free_all(headers);
    curl_easy_cleanup(curl);
    
    if (res != CURLE_OK) {
        owrt_log(LOG_ERR, "HTTP request failed: %s", curl_easy_strerror(res));
        free(response->data);
        response->data = NULL;
        return OWRT_ERROR_NETWORK;
    }
    
    owrt_log(LOG_DEBUG, "HTTP request successful, response size: %zu", response->size);
    return OWRT_SUCCESS;
}

// 发送加密的HTTP请求
int send_encrypted_http_request(const char *url, const char *post_data, 
                               const char *token, const char *aes_key, 
                               http_response_t *response, const owrt_config_t *config) {
    char *encrypted_data = NULL;
    char *decrypted_response = NULL;
    http_response_t raw_response = {0};
    int result = OWRT_ERROR_NETWORK;
    
    // 加密POST数据
    encrypted_data = encrypt_json_data(post_data, config);
    if (!encrypted_data) {
        owrt_log(LOG_ERR, "Failed to encrypt request data");
        return OWRT_ERROR_CRYPTO;
    }
    
    // 发送加密数据
    if (send_http_request(url, encrypted_data, token, &raw_response) == OWRT_SUCCESS) {
        // 解密响应
        decrypted_response = decrypt_json_data(raw_response.data, config);
        if (decrypted_response) {
            // 设置解密后的响应
            response->data = decrypted_response;
            response->size = strlen(decrypted_response);
            result = OWRT_SUCCESS;
        } else {
            owrt_log(LOG_ERR, "Failed to decrypt response data");
            result = OWRT_ERROR_CRYPTO;
        }
    }
    
    free(encrypted_data);
    if (result != OWRT_SUCCESS) {
        free(decrypted_response);
    }
    free(raw_response.data);
    
    return result;
}

// 认证函数
int authenticate_with_server(owrt_config_t *config) {
    char url[MAX_URL_SIZE];
    char post_data[2048];
    http_response_t response;
    json_object *json_obj, *token_obj, *status_obj;
    const char *token_str, *status_str;
    int ret = OWRT_ERROR_AUTH;
    
    // 如果启用加密，先验证现有密钥，如果无效则重新交换
    owrt_log(LOG_DEBUG, "Encryption check: use_encryption=%d, aes_key_len=%lu", 
             config->use_encryption, (unsigned long)strlen(config->aes_key));
    
    if (config->use_encryption) {
        if (strlen(config->aes_key) == 0) {
            owrt_log(LOG_INFO, "No AES key found, initiating key exchange");
            if (exchange_aes_key(config) != OWRT_SUCCESS) {
                owrt_log(LOG_ERR, "Failed to exchange AES key");
                return OWRT_ERROR_CRYPTO;
            }
        }
    }
    
    // 获取系统信息
    char system_info[1024];
    if (get_system_info_json(system_info, sizeof(system_info)) != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to get system info");
        strcpy(system_info, "{\"model\":\"Unknown\"}");
    }
    
    // 构建认证请求
    snprintf(post_data, sizeof(post_data),
             "{\"action\":\"authenticate\",\"device_id\":\"%s\",\"system_info\":%s}",
             config->device_id, system_info);
    
    owrt_log(LOG_DEBUG, "Authentication request: %s", post_data);
    
    // 构建URL
    snprintf(url, sizeof(url), "%s/api/auth.php", config->server_url);
    
    // 发送请求（根据加密设置选择加密或明文）
    if (config->use_encryption && strlen(config->aes_key) > 0) {
        ret = send_encrypted_http_request(url, post_data, NULL, config->aes_key, &response, config);
    } else {
        ret = send_http_request(url, post_data, NULL, &response);
    }
    
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Authentication request failed");
        return ret;
    }
    
    owrt_log(LOG_DEBUG, "Authentication response: %s", response.data);
    
    // 解析JSON响应
    json_obj = json_tokener_parse(response.data);
    if (!json_obj) {
        owrt_log(LOG_ERR, "Failed to parse authentication response JSON");
        free(response.data);
        return OWRT_ERROR_AUTH;
    }
    
    // 检查状态
    if (json_object_object_get_ex(json_obj, "status", &status_obj)) {
        status_str = json_object_get_string(status_obj);
        if (strcmp(status_str, "success") != 0) {
            owrt_log(LOG_ERR, "Authentication failed: %s", status_str);
            json_object_put(json_obj);
            free(response.data);
            return OWRT_ERROR_AUTH;
        }
    }
    
    // 获取token
    if (json_object_object_get_ex(json_obj, "token", &token_obj)) {
        token_str = json_object_get_string(token_obj);
        strncpy(config->auth_token, token_str, sizeof(config->auth_token) - 1);
        config->auth_token[sizeof(config->auth_token) - 1] = '\0';
        
        owrt_log(LOG_INFO, "Authentication successful, token: %.20s...", config->auth_token);
        ret = OWRT_SUCCESS;
    } else {
        owrt_log(LOG_ERR, "No token in authentication response");
        ret = OWRT_ERROR_AUTH;
    }
    
    json_object_put(json_obj);
    free(response.data);
    return ret;
}

// 心跳函数
int send_heartbeat(const owrt_config_t *config) {
    char url[MAX_URL_SIZE];
    char post_data[2048];
    char timestamp[32];
    http_response_t response;
    json_object *json_obj, *status_obj, *command_obj, *command_id_obj;
    const char *status_str, *command_str;
    int command_id;
    int ret = OWRT_ERROR_NETWORK;
    
    // 获取当前时间戳
    snprintf(timestamp, sizeof(timestamp), "%ld", time(NULL));
    
    // 获取系统信息
    char system_info[1024];
    if (get_system_info_json(system_info, sizeof(system_info)) != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to get system info");
        strcpy(system_info, "{\"model\":\"Unknown\"}");
    }
    
    // 构建心跳请求
    snprintf(post_data, sizeof(post_data),
             "{\"device_id\":\"%s\",\"token\":\"%s\",\"timestamp\":\"%s\",\"system_info\":%s}",
             config->device_id, config->auth_token, timestamp, system_info);
    
    owrt_log(LOG_DEBUG, "Heartbeat request: %s", post_data);
    
    // 构建URL
    snprintf(url, sizeof(url), "%s/api/heartbeat.php", config->server_url);
    
    // 发送请求（根据加密设置选择加密或明文）
    if (config->use_encryption && strlen(config->aes_key) > 0) {
        ret = send_encrypted_http_request(url, post_data, config->auth_token, config->aes_key, &response, config);
    } else {
        ret = send_http_request(url, post_data, config->auth_token, &response);
    }
    
    if (ret != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Heartbeat request failed");
        return ret;
    }
    
    owrt_log(LOG_DEBUG, "Heartbeat response: %s", response.data);
    
    // 解析JSON响应
    json_obj = json_tokener_parse(response.data);
    if (!json_obj) {
        owrt_log(LOG_ERR, "Failed to parse heartbeat response JSON");
        free(response.data);
        return OWRT_ERROR_NETWORK;
    }
    
    // 检查状态
    if (json_object_object_get_ex(json_obj, "status", &status_obj)) {
        status_str = json_object_get_string(status_obj);
        if (strcmp(status_str, "success") == 0) {
            owrt_log(LOG_DEBUG, "Heartbeat successful");
            ret = OWRT_SUCCESS;
            
            // 检查是否有待执行的命令
            if (json_object_object_get_ex(json_obj, "command", &command_obj) && 
                json_object_object_get_ex(json_obj, "command_id", &command_id_obj)) {
                
                command_str = json_object_get_string(command_obj);
                command_id = json_object_get_int(command_id_obj);
                
                if (command_str && strlen(command_str) > 0) {
                    owrt_log(LOG_INFO, "Received command (ID: %d): %s", command_id, command_str);
                    
                    // 执行命令
                    char command_result[4096];
                    int exit_code = execute_command(command_str, command_result, sizeof(command_result));
                    
                    owrt_log(LOG_INFO, "Command executed, exit code: %d", exit_code);
                    
                    // 发送命令执行结果
                    if (send_command_result(config, command_id, command_str, exit_code, command_result) != OWRT_SUCCESS) {
                        owrt_log(LOG_WARNING, "Failed to send command result (non-critical)");
                        // 不影响心跳成功状态
                    } else {
                        owrt_log(LOG_INFO, "Command result sent successfully");
                    }
                }
            }
        } else {
            owrt_log(LOG_WARNING, "Heartbeat failed: %s", status_str);
            ret = OWRT_ERROR_NETWORK;
        }
    }
    
    json_object_put(json_obj);
    free(response.data);
    return ret;
}

// 处理命令函数
int process_command(const char *command, char *result, size_t result_size) {
    FILE *fp;
    char *line = NULL;
    size_t line_len = 0;
    ssize_t read;
    size_t total_len = 0;
    
    owrt_log(LOG_INFO, "Executing command: %s", command);
    
    fp = popen(command, "r");
    if (fp == NULL) {
        owrt_log(LOG_ERR, "Failed to execute command: %s", command);
        return OWRT_ERROR_SYSTEM;
    }
    
    result[0] = '\0';
    
    while ((read = getline(&line, &line_len, fp)) != -1) {
        if (total_len + read + 1 < result_size) {
            strncat(result, line, result_size - total_len - 1);
            total_len += read;
        } else {
            owrt_log(LOG_WARNING, "Command output truncated");
            break;
        }
    }
    
    free(line);
    pclose(fp);
    
    // 移除最后的换行符
    if (total_len > 0 && result[total_len - 1] == '\n') {
        result[total_len - 1] = '\0';
    }
    
    owrt_log(LOG_DEBUG, "Command executed successfully, output length: %zu", total_len);
    return OWRT_SUCCESS;
}

// 发送命令结果到服务器
int send_command_result(const owrt_config_t *config, int command_id, const char *command, 
                       int exit_code, const char *output) {
    char url[MAX_URL_SIZE];
    char post_data[4096];
    http_response_t response;
    int ret;
    
    // 构建命令结果数据
    snprintf(post_data, sizeof(post_data),
             "{\"device_id\":\"%s\",\"token\":\"%s\",\"command_id\":%d,\"command\":\"%s\",\"exit_code\":%d,\"output\":\"%s\"}",
             config->device_id, config->auth_token, command_id, command, exit_code, output);
    
    // 构建URL
    snprintf(url, sizeof(url), "%s/api/command_result.php", config->server_url);
    
    // 发送请求（根据加密设置选择加密或明文）
    if (config->use_encryption && strlen(config->aes_key) > 0) {
        ret = send_encrypted_http_request(url, post_data, config->auth_token, config->aes_key, &response, config);
    } else {
        ret = send_http_request(url, post_data, config->auth_token, &response);
    }

    if (ret == OWRT_SUCCESS) {
        owrt_log(LOG_INFO, "Command result sent to server successfully");
        if (response.data) free(response.data);
        return OWRT_SUCCESS;
    } else {
        // 对于命令结果提交，网络错误不是致命的，记录警告即可
        owrt_log(LOG_WARNING, "Failed to send command result to server (non-fatal)");
        if (response.data) free(response.data);
        return OWRT_SUCCESS; // 返回成功，避免影响心跳循环
    }
}
