ALTER TABLE `srd_dev`.`transaction_log` 
ADD COLUMN `reconciled_state_time` DATETIME NULL DEFAULT NULL AFTER `user_agent`,
ADD COLUMN `reconciliation_batch_id` BIGINT(20) NULL DEFAULT NULL AFTER `reconciled_state_time`;
