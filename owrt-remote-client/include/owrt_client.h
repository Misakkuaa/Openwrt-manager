#ifndef OWRT_CLIENT_H
#define OWRT_CLIENT_H

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <stdint.h>  // 为base64函数添加
#include <curl/curl.h>
#include <json-c/json.h>
#include <syslog.h>
#include <time.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <openssl/evp.h>
#include <openssl/aes.h>
#include <openssl/rand.h>
#include <openssl/hmac.h>
#include <openssl/sha.h>

// 配置常量
#define MAX_BUFFER_SIZE 4096
#define MAX_CMD_SIZE 512
#define MAX_URL_SIZE 2048     // 进一步增加URL缓冲区大小
#define MAX_SERVER_URL_SIZE 512  // 限制服务器URL的最大长度
#define DEVICE_ID_FILE "/tmp/owrt_device_id"
#define CONFIG_FILE "/etc/config/owrt_client"
#define LOG_FILE "/var/log/owrt_client.log"
#define AES_KEY_FILE "/tmp/owrt_aes_key"

// AES加密常量
#define AES_KEY_SIZE 32      // AES-256
#define AES_KEY_BASE64_SIZE 48  // Base64编码后的AES密钥大小(44字符 + 终止符 + 额外空间)
#define AES_IV_SIZE 16       // 128-bit IV
#define AES_BLOCK_SIZE 16    // AES block size
#define HMAC_SIZE 32         // SHA-256 HMAC size

// 错误码定义
#define OWRT_SUCCESS 0
#define OWRT_ERROR_NETWORK -1
#define OWRT_ERROR_AUTH -2
#define OWRT_ERROR_CONFIG -3
#define OWRT_ERROR_SYSTEM -4
#define OWRT_ERROR_INVALID_PARAMS -5
#define OWRT_ERROR_CRYPTO -6

// 结构体定义
typedef struct {
    char server_url[MAX_SERVER_URL_SIZE];  // 使用专门的服务器URL大小限制
    char device_id[64];
    char auth_token[256];  // 增加token缓冲区大小
    int heartbeat_interval;
    int max_retries;
    int use_encryption;    // 是否启用加密
    char aes_key[AES_KEY_BASE64_SIZE];  // AES密钥(base64编码)
} owrt_config_t;

typedef struct {
    char *data;
    size_t size;
} http_response_t;

// 核心功能函数声明
int owrt_init(void);
int owrt_cleanup(void);
int owrt_main_loop(void);

// 配置管理
int load_config(owrt_config_t *config);
int save_config(const owrt_config_t *config);

// 设备标识管理
int generate_device_id(char *device_id, size_t size);
int load_device_id(char *device_id, size_t size);
int save_device_id(const char *device_id);
int get_device_id_from_mac(char *device_id, size_t max_len);

// 网络通信
int http_request(const char *url, const char *data, http_response_t *response);
int http_request_encrypted(const char *url, const char *data, http_response_t *response, const owrt_config_t *config);
size_t write_callback(void *contents, size_t size, size_t nmemb, http_response_t *response);

// AES加密功能
int aes_encrypt(const unsigned char *plaintext, int plaintext_len, 
                const unsigned char *key, const unsigned char *iv,
                unsigned char *ciphertext);
int aes_decrypt(const unsigned char *ciphertext, int ciphertext_len,
                const unsigned char *key, const unsigned char *iv,
                unsigned char *plaintext);
int generate_random_iv(unsigned char *iv, size_t size);
int compute_hmac_sha256(const unsigned char *data, size_t data_len,
                       const unsigned char *key, size_t key_len,
                       unsigned char *hmac);
int verify_hmac_sha256(const unsigned char *data, size_t data_len,
                      const unsigned char *key, size_t key_len,
                      const unsigned char *hmac);

// 加密通信
char *encrypt_json_data(const char *json_data, const owrt_config_t *config);
char *decrypt_json_data(const char *encrypted_data, const owrt_config_t *config);
char *decrypt_base64_data(const char *encoded_data, const owrt_config_t *config);
int exchange_aes_key(owrt_config_t *config);

// 认证管理
int authenticate_with_server(owrt_config_t *config);
int verify_server_signature(const char *data, const char *signature);

// 指令执行
int execute_command(const char *command, char *output, size_t output_size);
int send_heartbeat(const owrt_config_t *config);
int process_server_commands(json_object *json_obj, const owrt_config_t *config);
int process_server_command(const char *json_data, const owrt_config_t *config);
int send_command_result(const owrt_config_t *config, int command_id, const char *command, 
                       int exit_code, const char *output);

// 日志管理
void owrt_log(int level, const char *format, ...);
int init_logging(void);
void cleanup_logging(void);
void check_memory_usage(void);
void system_maintenance(void);
void cleanup_temp_files(void);

// 工具函数
char *base64_encode(const unsigned char *data, size_t input_length);
unsigned char *base64_decode(const char *data, size_t input_length, size_t *output_length);
int get_system_info(char *info, size_t size);
int get_system_info_json(char *json_buf, size_t buf_size);

#endif // OWRT_CLIENT_H
