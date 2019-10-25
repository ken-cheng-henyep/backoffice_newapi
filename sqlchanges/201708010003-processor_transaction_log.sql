
SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `srd_dev`.`processor_transaction_log` 
ADD COLUMN `transaction_type` DATE NULL DEFAULT NULL AFTER `id`
 ;


ALTER TABLE `srd_dev`.`processor_transaction_log` 
ADD COLUMN `pc_log_id` BIGINT(20) NULL DEFAULT NULL AFTER `transaction_type`;

ALTER TABLE `srd_dev`.`processor_transaction_log` 
ADD INDEX `idx_log_id` (`pc_log_id` ASC);


UPDATE processor_transaction_log ptx, transaction_log tx SET ptx.pc_log_id = tx.id 
WHERE 
    ((ptx.pc_transaction_id = tx.TRANSACTION_ID) OR (ptx.pc_internal_id = tx.internal_id AND tx.internal_id IS NOT NULL) ) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL);


SET SQL_SAFE_UPDATES = 1;