<?php
require dirname(__DIR__) . '/config/bootstrap.php';
include ROOT.'/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

define ('API_BASE_URL', 'http://127.0.0.1/admin/api/remittance/');
// create a log channel
$log = new Logger('RemittanceBatch');
$log->pushHandler(new StreamHandler(dirname(__DIR__).'/logs/batch_update.log', Logger::DEBUG));

//select merchant with remittance_preauthorized
$merchants = TableRegistry::get('Merchants');
$query = $merchants->find('all')
    ->where(['remittance_preauthorized >' => 0])
    ->order(['name' => 'ASC']);

$res = $query->toArray();
//Add data to Array
foreach ($res as $k=>$r) {
    //printf("%s: %s\n", $r['id'], $r['name']);
    $log->info("{$r['id']}: {$r['name']}");
    $result = _callApiStatusUpdate($r['id']);

    $log->info("RESULT: {$result}");
}

//call API batchRequestStatusUpdate
function _callApiStatusUpdate($id) {
    if (empty($id))
        return false;

    $url = API_BASE_URL.'batch_request/status_update/';
    $posts = ['merchant_id'=>$id, 'action'=>'process'];
    $result = simpleCallURL($url, $posts);
    return $result;
}