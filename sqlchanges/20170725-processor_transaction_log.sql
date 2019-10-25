

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
SET SQL_SAFE_UPDATES = 0;

DROP TABLE IF EXISTS `processor_transaction_log`;
CREATE TABLE `processor_transaction_log` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `transaction_type` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `pc_txn_id` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `pc_transaction_id` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `pc_internal_id` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `processor` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `processor_account_no` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `payment_no` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8_bin DEFAULT NULL,
  `status_text` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `settle_status_text` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `amount` double DEFAULT NULL,
  `fee` double DEFAULT NULL,
  `convert_rate` float DEFAULT NULL,
  `refund_amount` double DEFAULT NULL,
  `bank_name` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `bank_code` int(11) DEFAULT NULL,
  `bank_card_number` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `bank_payment_no` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `payment_code` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `payer_name` varchar(128) COLLATE utf8_bin DEFAULT NULL,
  `phone_no` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `id_type` int(11) DEFAULT NULL,
  `id_number` varchar(64) COLLATE utf8_bin DEFAULT NULL,
  `remark` text COLLATE utf8_bin,
  `transaction_time` timestamp NULL DEFAULT NULL,
  `payment_time` timestamp NULL DEFAULT NULL,
  `settle_time` timestamp NULL DEFAULT NULL,
  `create_time` timestamp NULL DEFAULT NULL,
  `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `pc_transaction_id` (`pc_transaction_id`),
  KEY `pc_internal_id` (`pc_internal_id`),
  KEY `idx_processor_transaction_log_processor` (`processor`)
) ENGINE=InnoDB AUTO_INCREMENT=148667 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;





TRUNCATE TABLE processor_transaction_log;

INSERT INTO processor_transaction_log (
`pc_transaction_id`,
`processor`,
`payment_no`,
`processor_account_no`
`status_text`,
`settle_status_text` ,
`currency`,
`amount`,
`fee`,
`convert_rate`,
`refund_amount`,
`bank_name`,
`bank_code`,
`bank_card_number`,
`payer_name` ,
`phone_no`,
`id_type`,
`id_number`,
`remark`,
`transaction_time`,
`payment_time`,
`settle_time`,
`create_time`,
`update_time`
)
SELECT 
`transaction_id`,
'GHT',
`payment_no`,
`merchant_no`
`status`,
`settle_status` ,
`currency`,
`amount`,
`fee`,
`convert_rate`,
`refund_amount`,
`bank_name`,
`bank_code`,
`card_number`,
`payer_name` ,
`phone_no`,
`id_type`,
`id_number`,
`remark`,
`transaction_time`,
`payment_time`,
`settle_time`,
`update_time`,
`update_time`

 FROM ght_transaction_log
 ORDER BY `settle_time` ASC;



INSERT INTO processor_transaction_log (
`pc_internal_id`,
`processor`,
`payment_no`,
`processor_account_no`,
`currency`,
`status_text`,
`amount`,
`fee`,
`bank_name`,
`bank_code`,
`remark`,
`transaction_time`,
`payment_time`,
`settle_time`,
`create_time`,
`update_time`
)
SELECT 
`merchant_order_no`,
'GPAY',
`order_no`,
`merchant_no`,
`currency`,
`status`,
`amount`,
`fee`,
`bank_name`,
`bank_code`,
`results`,
`transaction_time`,
`transaction_time`,
`transaction_time`,
`update_time`,
`update_time`

 FROM gpay_transaction_log 
 ORDER BY `transaction_time` ASC;
 

# For GPAY, copy bank_code if available
UPDATE `transaction_log` tx, `processor_transaction_log` ptx 
SET tx.bank_code = ptx.bank_code 
WHERE ptx.bank_code IS NOT NULL 
AND (ptx.pc_internal_id = tx.internal_id AND tx.internal_id IS NOT NULL) 
AND ptx.processor = 'GPAY' 
AND  ptx.transaction_time >= '2017-06-01' 
AND ptx.transaction_time < '2017-08-01'
;

SET SQL_SAFE_UPDATES = 1;
SET foreign_key_checks = 1;