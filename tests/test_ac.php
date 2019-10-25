<?php
require 'vendor/autoload.php';

// Internal Test
$mid='9dd5a398-c897-11e4-a1b7-0211eb00a4cc';
$mid='testonly';

$wallet = new MerchantWallet($mid);
//$wallet->createAccount('Basic Account', 0);

$wallet->addTransaction(1000, MerchantWallet::TYPE_ADMIN_UPDATE, $dsc='test_'.time());
//$wallet->addTransaction(-9.99, MerchantWallet::TYPE_BATCH_REMITTANCE, $dsc='test_'.time());

printf("bal:%s\n", $wallet->getBalance());

