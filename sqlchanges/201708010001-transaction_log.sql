-- Adding column 'search_state_time' in transaction_log
-- Aim to speed up the transaction date query during searching with date time
-- 
-- search_state_time need to be update from Processor's payment_time (or PayConnector's STATE_TIME)
-- 
SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `srd_dev`.`transaction_log` 
ADD COLUMN `search_state_time` DATE NULL DEFAULT NULL AFTER `id_card_number`,
ADD INDEX `idx_search_state_time` (`search_state_time` ASC) ;



UPDATE transaction_log SET `search_state_time` = date_format(STATE_TIME,'%Y-%m-%d') WHERE (settlement_status = '' OR settlement_status IS NULL) AND STATE_TIME IS NOT NULL ;

-- For sale record, search_state_time may come from processor
UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME between '2017-07-01' AND '2017-08-30'
ORDER BY STATE_TIME ASC;

UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME between '2017-06-01' AND '2017-06-30'
ORDER BY STATE_TIME ASC;
UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME between '2017-05-01' AND '2017-06-31'
ORDER BY STATE_TIME ASC;
UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME between '2017-04-01' AND '2017-04-30'
ORDER BY STATE_TIME ASC;

UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME between '2017-03-01' AND '2017-03-31'
ORDER BY STATE_TIME ASC;

UPDATE transaction_log tx , processor_transaction_log ptx 
SET tx.search_state_time = DATE_FORMAT(ptx.payment_time,'%Y-%m-%d') 
WHERE ptx.processor IS NOT NULL 
AND (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL) 
AND tx.STATE = 'SALE'
AND (tx.settlement_status = '' OR tx.settlement_status IS NULL)
AND tx.STATE_TIME <= '2017-02-28' 
ORDER BY STATE_TIME ASC;


SET SQL_SAFE_UPDATES = 1;
