<?php
require dirname(__DIR__) . '/config/bootstrap.php';

include 'vendor/autoload.php';

//$ght = new GhtAPI($prd = false);
$ght = new gaohuitong_pay(false);


//$ght->insertAllGhtBankCode();
$prd = new gaohuitong_pay(true);

$oid='eca0d55aa24411e6b7b70211eb00a4cc';
$oid = '59396dbb7167c8ac2d6980a3a466bb';
$oid = '5939124e695d600008738';
$oid = '593a0acb5a95400008740';
$oid = '593a3ec3f3b1800008791';
$oid='5934ee3288dc500008305';
$oid='592549c641ab400007777';
$oid= '5912cf2ab269c00006900' ;
$oid='58db1adb7eaa800003658';

$query = $prd->query(['order_id'=>$oid]);
var_dump($query);
exit;

/*
$amount = 3.3;

$infos = [
    'order_id'=>time(),
    //$this->orderId,
    'amount'=> round($amount*100),
    'bank_code'=> $params['ght_code'],
    //'account_no'=> '',  //test only
    'account_no'=> $params['account'],
    'account_name'=> $params['beneficiary_name'],
];
*/
$account = '6222621310015482538';
$account = '12345678';
$infos = ['order_id'=>time(), 'amount'=>500, 'bank_code'=>'104','account_no'=>$account,'account_name'=>'程义'];
$results = $ght->pay($infos);
var_dump($results);
exit;
