-- 修改 ep_card_owner 表的状态字段
ALTER TABLE `ep_card_owner` 
MODIFY COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：0=禁用，1=正常，2=警告';

-- 修改 ep_card_bank 表的卡类型字段
ALTER TABLE `ep_card_bank` 
MODIFY COLUMN `card_type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '卡类型：1=储蓄卡，2=信用卡，3=借记卡';

-- 修改 ep_card_bank 表的卡状态字段
ALTER TABLE `ep_card_bank` 
MODIFY COLUMN `card_status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '卡状态：1=正常，2=冻结，3=挂失，4=注销';

-- 如果需要修改现有数据，可以使用以下语句（根据实际情况调整）
-- UPDATE `ep_card_owner` SET `status` = 1 WHERE `status` = 'normal';
-- UPDATE `ep_card_owner` SET `status` = 0 WHERE `status` = 'hidden';
-- UPDATE `ep_card_owner` SET `status` = 2 WHERE `status` = 'warning';

-- 查看修改后的表结构
DESCRIBE `ep_card_owner`;
DESCRIBE `ep_card_bank`;
