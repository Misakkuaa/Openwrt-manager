-- 添加加密状态字段到设备表
USE management;

-- 添加加密相关字段
ALTER TABLE devices 
ADD COLUMN encryption_enabled BOOLEAN DEFAULT FALSE COMMENT '是否启用加密通信',
ADD COLUMN last_key_exchange DATETIME NULL COMMENT '最后一次密钥交换时间',
ADD COLUMN encryption_cipher VARCHAR(32) DEFAULT NULL COMMENT '加密算法',
ADD COLUMN key_id VARCHAR(64) DEFAULT NULL COMMENT '当前密钥ID';

-- 创建索引以提高查询性能
CREATE INDEX idx_encryption_enabled ON devices(encryption_enabled);
CREATE INDEX idx_last_key_exchange ON devices(last_key_exchange);

-- 显示表结构
DESCRIBE devices;
