-- 添加SN字段到devices表
ALTER TABLE devices ADD COLUMN sn VARCHAR(64) DEFAULT 'none';

-- 创建索引以便快速查找
CREATE INDEX idx_devices_sn ON devices(sn);
