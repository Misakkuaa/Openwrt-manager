#include "../include/owrt_client.h"

// Base64编码表
static const char base64_table[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

/**
 * Base64编码函数
 */
char *base64_encode(const unsigned char *data, size_t input_length) {
    size_t output_length = 4 * ((input_length + 2) / 3);
    char *encoded_data = malloc(output_length + 1);
    if (encoded_data == NULL) return NULL;

    for (size_t i = 0, j = 0; i < input_length;) {
        uint32_t octet_a = i < input_length ? data[i++] : 0;
        uint32_t octet_b = i < input_length ? data[i++] : 0;
        uint32_t octet_c = i < input_length ? data[i++] : 0;

        uint32_t triple = (octet_a << 0x10) + (octet_b << 0x08) + octet_c;

        encoded_data[j++] = base64_table[(triple >> 3 * 6) & 0x3F];
        encoded_data[j++] = base64_table[(triple >> 2 * 6) & 0x3F];
        encoded_data[j++] = base64_table[(triple >> 1 * 6) & 0x3F];
        encoded_data[j++] = base64_table[(triple >> 0 * 6) & 0x3F];
    }

    // 添加填充
    size_t mod = input_length % 3;
    if (mod != 0) {
        for (size_t i = mod; i < 3; i++) {
            encoded_data[output_length - 1 - (3 - 1 - i)] = '=';
        }
    }

    encoded_data[output_length] = '\0';
    return encoded_data;
}

/**
 * Base64解码函数
 */
unsigned char *base64_decode(const char *data, size_t input_length, size_t *output_length) {
    static int decoding_table[128] = {0};
    static int table_built = 0;
    
    // 构建解码表（只构建一次）
    if (!table_built) {
        for (int i = 0; i < 128; i++) decoding_table[i] = -1;
        for (int i = 0; i < 64; i++) decoding_table[(int)base64_table[i]] = i;
        table_built = 1;
    }

    if (input_length % 4 != 0) return NULL;

    *output_length = input_length / 4 * 3;
    if (data[input_length - 1] == '=') (*output_length)--;
    if (data[input_length - 2] == '=') (*output_length)--;

    unsigned char *decoded_data = malloc(*output_length);
    if (decoded_data == NULL) return NULL;

    for (size_t i = 0, j = 0; i < input_length;) {
        uint32_t sextet_a = data[i] == '=' ? 0 & i++ : decoding_table[(int)data[i++]];
        uint32_t sextet_b = data[i] == '=' ? 0 & i++ : decoding_table[(int)data[i++]];
        uint32_t sextet_c = data[i] == '=' ? 0 & i++ : decoding_table[(int)data[i++]];
        uint32_t sextet_d = data[i] == '=' ? 0 & i++ : decoding_table[(int)data[i++]];

        uint32_t triple = (sextet_a << 3 * 6) + (sextet_b << 2 * 6) + (sextet_c << 1 * 6) + (sextet_d << 0 * 6);

        if (j < *output_length) decoded_data[j++] = (triple >> 2 * 8) & 0xFF;
        if (j < *output_length) decoded_data[j++] = (triple >> 1 * 8) & 0xFF;
        if (j < *output_length) decoded_data[j++] = (triple >> 0 * 8) & 0xFF;
    }

    return decoded_data;
}

/**
 * AES-256-CBC加密函数
 */
int aes_encrypt(const unsigned char *plaintext, int plaintext_len, 
                const unsigned char *key, const unsigned char *iv,
                unsigned char *ciphertext) {
    EVP_CIPHER_CTX *ctx;
    int len;
    int ciphertext_len;

    // 创建加密上下文
    if (!(ctx = EVP_CIPHER_CTX_new())) {
        owrt_log(LOG_ERR, "Failed to create cipher context");
        return -1;
    }

    // 初始化加密
    if (1 != EVP_EncryptInit_ex(ctx, EVP_aes_256_cbc(), NULL, key, iv)) {
        owrt_log(LOG_ERR, "Failed to initialize encryption");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }

    // 加密数据
    if (1 != EVP_EncryptUpdate(ctx, ciphertext, &len, plaintext, plaintext_len)) {
        owrt_log(LOG_ERR, "Failed to encrypt data");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }
    ciphertext_len = len;

    // 完成加密
    if (1 != EVP_EncryptFinal_ex(ctx, ciphertext + len, &len)) {
        owrt_log(LOG_ERR, "Failed to finalize encryption");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }
    ciphertext_len += len;

    // 清理
    EVP_CIPHER_CTX_free(ctx);
    
    return ciphertext_len;
}

/**
 * AES-256-CBC解密函数
 */
int aes_decrypt(const unsigned char *ciphertext, int ciphertext_len,
                const unsigned char *key, const unsigned char *iv,
                unsigned char *plaintext) {
    EVP_CIPHER_CTX *ctx;
    int len;
    int plaintext_len;

    // 创建解密上下文
    if (!(ctx = EVP_CIPHER_CTX_new())) {
        owrt_log(LOG_ERR, "Failed to create cipher context");
        return -1;
    }

    // 初始化解密
    if (1 != EVP_DecryptInit_ex(ctx, EVP_aes_256_cbc(), NULL, key, iv)) {
        owrt_log(LOG_ERR, "Failed to initialize decryption");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }

    // 解密数据
    if (1 != EVP_DecryptUpdate(ctx, plaintext, &len, ciphertext, ciphertext_len)) {
        owrt_log(LOG_ERR, "Failed to decrypt data");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }
    plaintext_len = len;

    // 完成解密
    if (1 != EVP_DecryptFinal_ex(ctx, plaintext + len, &len)) {
        owrt_log(LOG_ERR, "Failed to finalize decryption");
        EVP_CIPHER_CTX_free(ctx);
        return -1;
    }
    plaintext_len += len;

    // 清理
    EVP_CIPHER_CTX_free(ctx);
    
    return plaintext_len;
}

/**
 * 生成随机IV
 */
int generate_random_iv(unsigned char *iv, size_t size) {
    if (RAND_bytes(iv, size) != 1) {
        owrt_log(LOG_ERR, "Failed to generate random IV");
        return -1;
    }
    return 0;
}

/**
 * 计算HMAC-SHA256
 */
int compute_hmac_sha256(const unsigned char *data, size_t data_len,
                       const unsigned char *key, size_t key_len,
                       unsigned char *hmac) {
    unsigned int hmac_len;
    
    if (!HMAC(EVP_sha256(), key, key_len, data, data_len, hmac, &hmac_len)) {
        owrt_log(LOG_ERR, "Failed to compute HMAC");
        return -1;
    }
    
    if (hmac_len != HMAC_SIZE) {
        owrt_log(LOG_ERR, "HMAC size mismatch: expected %d, got %u", HMAC_SIZE, hmac_len);
        return -1;
    }
    
    return 0;
}

/**
 * 验证HMAC-SHA256
 */
int verify_hmac_sha256(const unsigned char *data, size_t data_len,
                      const unsigned char *key, size_t key_len,
                      const unsigned char *hmac) {
    unsigned char computed_hmac[HMAC_SIZE];
    
    if (compute_hmac_sha256(data, data_len, key, key_len, computed_hmac) != 0) {
        return -1;
    }
    
    // 使用常时比较防止时序攻击
    if (CRYPTO_memcmp(hmac, computed_hmac, HMAC_SIZE) != 0) {
        owrt_log(LOG_ERR, "HMAC verification failed");
        return -1;
    }
    
    return 0;
}

/**
 * 将二进制数据转换为十六进制字符串
 */
static void bin_to_hex(const unsigned char *bin, size_t bin_len, char *hex) {
    for (size_t i = 0; i < bin_len; i++) {
        sprintf(hex + i * 2, "%02x", bin[i]);
    }
}

/**
 * 将十六进制字符串转换为二进制数据
 */
static int hex_to_bin(const char *hex, unsigned char *bin, size_t bin_len) {
    if (strlen(hex) != bin_len * 2) {
        return -1;
    }
    
    for (size_t i = 0; i < bin_len; i++) {
        sscanf(hex + i * 2, "%2hhx", &bin[i]);
    }
    
    return 0;
}

/**
 * 加密JSON数据
 */
char *encrypt_json_data(const char *json_data, const owrt_config_t *config) {
    if (!config->use_encryption || strlen(config->aes_key) == 0) {
        owrt_log(LOG_DEBUG, "Encryption disabled or no key available");
        return strdup(json_data);
    }
    
    size_t json_len = strlen(json_data);
    unsigned char iv[AES_IV_SIZE];
    unsigned char *ciphertext = NULL;
    unsigned char *payload = NULL;
    char *result = NULL;
    char *encoded_result = NULL;
    
    // 生成随机IV
    if (generate_random_iv(iv, AES_IV_SIZE) != 0) {
        goto cleanup;
    }
    
    // 为密文分配空间（加上padding）
    size_t max_ciphertext_len = json_len + AES_BLOCK_SIZE;
    ciphertext = malloc(max_ciphertext_len);
    if (!ciphertext) {
        owrt_log(LOG_ERR, "Failed to allocate memory for ciphertext");
        goto cleanup;
    }
    
    // 解码AES密钥
    unsigned char aes_key[AES_KEY_SIZE];
    size_t key_len;
    owrt_log(LOG_DEBUG, "Decoding AES key: '%s' (length: %lu)", config->aes_key, (unsigned long)strlen(config->aes_key));
    unsigned char *decoded_key = base64_decode(config->aes_key, strlen(config->aes_key), &key_len);
    if (!decoded_key || key_len != AES_KEY_SIZE) {
        owrt_log(LOG_ERR, "Invalid AES key: decoded_key=%p, key_len=%lu, expected=%d", 
                 decoded_key, (unsigned long)key_len, AES_KEY_SIZE);
        free(decoded_key);
        goto cleanup;
    }
    memcpy(aes_key, decoded_key, AES_KEY_SIZE);
    free(decoded_key);
    
    // 加密数据
    int ciphertext_len = aes_encrypt((unsigned char*)json_data, json_len, aes_key, iv, ciphertext);
    if (ciphertext_len < 0) {
        goto cleanup;
    }
    
    // 组装payload: IV + 密文
    size_t payload_len = AES_IV_SIZE + ciphertext_len;
    payload = malloc(payload_len);
    if (!payload) {
        owrt_log(LOG_ERR, "Failed to allocate memory for payload");
        goto cleanup;
    }
    
    memcpy(payload, iv, AES_IV_SIZE);
    memcpy(payload + AES_IV_SIZE, ciphertext, ciphertext_len);
    
    // 计算HMAC - 匹配服务器格式：对 IV+密文 计算HMAC
    unsigned char hmac[HMAC_SIZE];
    owrt_log(LOG_DEBUG, "Computing HMAC on payload (IV+ciphertext, length: %zu)", payload_len);
    if (compute_hmac_sha256(payload, payload_len, aes_key, AES_KEY_SIZE, hmac) != 0) {
        owrt_log(LOG_ERR, "HMAC computation failed");
        goto cleanup;
    }
    
    // 组装最终数据: HMAC + IV + 密文 (匹配服务器格式)
    size_t final_len = HMAC_SIZE + AES_IV_SIZE + ciphertext_len;
    unsigned char *final_data = malloc(final_len);
    if (!final_data) {
        owrt_log(LOG_ERR, "Failed to allocate memory for final data");
        goto cleanup;
    }
    
    memcpy(final_data, hmac, HMAC_SIZE);
    memcpy(final_data + HMAC_SIZE, iv, AES_IV_SIZE);
    memcpy(final_data + HMAC_SIZE + AES_IV_SIZE, ciphertext, ciphertext_len);
    
    // Base64编码
    encoded_result = base64_encode(final_data, final_len);
    free(final_data);
    
    if (!encoded_result) {
        owrt_log(LOG_ERR, "Failed to base64 encode encrypted data");
        goto cleanup;
    }
    
    // 创建加密数据的JSON包装
    json_object *wrapper = json_object_new_object();
    json_object *encrypted_flag = json_object_new_boolean(1);
    json_object *data_obj = json_object_new_string(encoded_result);
    
    json_object_object_add(wrapper, "encrypted", encrypted_flag);
    json_object_object_add(wrapper, "data", data_obj);
    
    result = strdup(json_object_to_json_string(wrapper));
    json_object_put(wrapper);
    
    owrt_log(LOG_DEBUG, "Data encrypted successfully");
    
cleanup:
    free(ciphertext);
    free(payload);
    free(encoded_result);
    
    return result;
}

/**
 * 解密JSON数据
 */
char *decrypt_json_data(const char *encrypted_data, const owrt_config_t *config) {
    if (!config->use_encryption || strlen(config->aes_key) == 0) {
        owrt_log(LOG_DEBUG, "Encryption disabled or no key available");
        return strdup(encrypted_data);
    }
    
    // 首先尝试解析为JSON格式的加密数据
    json_object *root = json_tokener_parse(encrypted_data);
    if (root) {
        // 检查是否为加密数据
        json_object *encrypted_flag;
        if (json_object_object_get_ex(root, "encrypted", &encrypted_flag) &&
            json_object_get_boolean(encrypted_flag)) {
            
            // 获取加密数据
            json_object *data_obj;
            if (json_object_object_get_ex(root, "data", &data_obj)) {
                const char *encoded_data = json_object_get_string(data_obj);
                if (encoded_data) {
                    // 处理JSON包装的加密数据
                    char *result = decrypt_base64_data(encoded_data, config);
                    json_object_put(root);
                    return result;
                }
            }
            json_object_put(root);
            return NULL;
        } else {
            // 未加密数据，直接返回
            json_object_put(root);
            return strdup(encrypted_data);
        }
    }
    
    // 如果不是JSON格式，尝试直接作为base64加密数据处理
    owrt_log(LOG_DEBUG, "Treating as direct base64 encrypted data");
    return decrypt_base64_data(encrypted_data, config);
}

/**
 * 解密base64编码的加密数据
 */
/**
 * 解密base64编码的加密数据
 */
char *decrypt_base64_data(const char *encoded_data, const owrt_config_t *config) {
    // Base64解码
    size_t decoded_len;
    unsigned char *decoded_data = base64_decode(encoded_data, strlen(encoded_data), &decoded_len);
    
    if (!decoded_data) {
        owrt_log(LOG_ERR, "Failed to base64 decode encrypted data");
        return NULL;
    }
    
    if (decoded_len < AES_IV_SIZE + HMAC_SIZE) {
        owrt_log(LOG_ERR, "Encrypted data too short");
        free(decoded_data);
        return NULL;
    }
    
    // 解码AES密钥
    unsigned char aes_key[AES_KEY_SIZE];
    size_t key_len;
    unsigned char *decoded_key = base64_decode(config->aes_key, strlen(config->aes_key), &key_len);
    if (!decoded_key || key_len != AES_KEY_SIZE) {
        owrt_log(LOG_ERR, "Invalid AES key");
        free(decoded_key);
        free(decoded_data);
        return NULL;
    }
    memcpy(aes_key, decoded_key, AES_KEY_SIZE);
    free(decoded_key);
    
    // 新格式分离: HMAC + IV + 密文 (匹配服务器格式)
    if (decoded_len < HMAC_SIZE + AES_IV_SIZE) {
        owrt_log(LOG_ERR, "Encrypted data too short for HMAC + IV");
        free(decoded_data);
        return NULL;
    }
    
    unsigned char *hmac = decoded_data;
    unsigned char *iv = decoded_data + HMAC_SIZE;
    size_t ciphertext_len = decoded_len - HMAC_SIZE - AES_IV_SIZE;
    unsigned char *ciphertext = decoded_data + HMAC_SIZE + AES_IV_SIZE;
    
    owrt_log(LOG_DEBUG, "Decryption format: HMAC(%d) + IV(%d) + Ciphertext(%zu)", 
             HMAC_SIZE, AES_IV_SIZE, ciphertext_len);
    
    // 验证HMAC - 对 IV+密文 计算 (匹配服务器格式)
    size_t payload_len = AES_IV_SIZE + ciphertext_len;
    if (verify_hmac_sha256(iv, payload_len, aes_key, AES_KEY_SIZE, hmac) != 0) {
        owrt_log(LOG_ERR, "HMAC verification failed");
        free(decoded_data);
        return NULL;
    }
    
    // 解密数据
    unsigned char *plaintext = malloc(ciphertext_len + AES_BLOCK_SIZE);
    if (!plaintext) {
        owrt_log(LOG_ERR, "Failed to allocate memory for plaintext");
        free(decoded_data);
        return NULL;
    }
    
    int plaintext_len = aes_decrypt(ciphertext, ciphertext_len, aes_key, iv, plaintext);
    free(decoded_data);
    
    if (plaintext_len < 0) {
        free(plaintext);
        return NULL;
    }
    
    // 确保字符串结尾
    plaintext[plaintext_len] = '\0';
    
    char *result = strdup((char*)plaintext);
    free(plaintext);
    
    owrt_log(LOG_DEBUG, "Data decrypted successfully");
    return result;
}

/**
 * 与服务器交换AES密钥
 */
int exchange_aes_key(owrt_config_t *config) {
    char url[MAX_URL_SIZE];
    char request_data[512];
    http_response_t response = {0};
    
    // 首先测试基本连接
    owrt_log(LOG_INFO, "Testing server connectivity before key exchange...");
    char test_url[MAX_URL_SIZE];
    snprintf(test_url, sizeof(test_url), "%s/api/test.php", config->server_url);
    
    http_response_t test_response = {0};
    if (send_http_request(test_url, "{\"test\":\"ping\"}", NULL, &test_response) != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Server connectivity test failed");
        owrt_log(LOG_ERR, "Cannot reach server at: %s", config->server_url);
        return OWRT_ERROR_NETWORK;
    }
    
    if (test_response.data) {
        owrt_log(LOG_INFO, "Server connectivity test successful");
        free(test_response.data);
    }
    
    snprintf(url, sizeof(url), "%s/api/crypto/exchange.php", config->server_url);
    snprintf(request_data, sizeof(request_data), 
             "{\"device_id\":\"%s\"}", config->device_id);
    
    owrt_log(LOG_INFO, "Exchanging AES key with server...");
    owrt_log(LOG_DEBUG, "Key exchange URL: %s", url);
    owrt_log(LOG_DEBUG, "Request data: %s", request_data);
    
    // 发送密钥交换请求（不加密）
    if (send_http_request(url, request_data, NULL, &response) != OWRT_SUCCESS) {
        owrt_log(LOG_ERR, "Failed to send key exchange request");
        return OWRT_ERROR_NETWORK;
    }
    
    if (!response.data) {
        owrt_log(LOG_ERR, "Empty response from key exchange");
        return OWRT_ERROR_NETWORK;
    }
    
    // 解析响应
    json_object *root = json_tokener_parse(response.data);
    if (!root) {
        owrt_log(LOG_ERR, "Failed to parse key exchange response");
        free(response.data);
        return OWRT_ERROR_NETWORK;
    }
    
    json_object *success_obj;
    if (!json_object_object_get_ex(root, "success", &success_obj) ||
        !json_object_get_boolean(success_obj)) {
        owrt_log(LOG_ERR, "Key exchange failed");
        json_object_put(root);
        free(response.data);
        return OWRT_ERROR_AUTH;
    }
    
    json_object *key_obj;
    if (!json_object_object_get_ex(root, "key", &key_obj)) {
        owrt_log(LOG_ERR, "No key in exchange response");
        json_object_put(root);
        free(response.data);
        return OWRT_ERROR_AUTH;
    }
    
    const char *key_string = json_object_get_string(key_obj);
    if (!key_string) {
        owrt_log(LOG_ERR, "Invalid key in exchange response");
        json_object_put(root);
        free(response.data);
        return OWRT_ERROR_AUTH;
    }
    
    // 保存AES密钥
    strncpy(config->aes_key, key_string, sizeof(config->aes_key) - 1);
    config->aes_key[sizeof(config->aes_key) - 1] = '\0';
    config->use_encryption = 1;
    
    owrt_log(LOG_INFO, "AES key exchange successful");
    owrt_log(LOG_DEBUG, "New AES key saved: %.20s... (length: %lu)", 
             config->aes_key, (unsigned long)strlen(config->aes_key));
    
    json_object_put(root);
    free(response.data);
    
    return OWRT_SUCCESS;
}
