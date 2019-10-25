<?php
require dirname(__DIR__) . '/config/bootstrap.php';
include 'vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Cake\Datasource\ConnectionManager;

define ('API_BASE_URL', 'http://127.0.0.1/admin/api/remittance/');
// create a log channel
$log = new Logger('RemittanceBatch');
$log->pushHandler(new StreamHandler(dirname(__DIR__).'/logs/api_status_update.log', Logger::DEBUG));

$db_name = ConnectionManager::get('default')->config()['database'];
$reader = new RemittanceReportReader($db_name);

/*
$batch_id = '595b6296be96b';
$batch_id = '595a9ba68e74a';

$batch_id = '595eff6251858';
$batch_id = '59678f52d3d1c';
$batch_id = '5968386dba151';
$batch_id = '59703a5099a4f';
$batch_id = '598877e3a65c5';
$batch_id = '59884092c2042';
*/
$options = getopt("b:");
if (!empty($options['b']))
    $batch_id = $options['b'];
//select request with pending status, 200X
$table = 'remittance_api_log';
$apis = TableRegistry::get($table);
$query = $apis->find('all')
    //->where(['status' => 1, 'processor'=>'ght', 'return_code like'=>'200%', 'callback is'=> null])
    //->where(['batch_id' => $batch_id,])
    ->where(['req_id' => $batch_id,])
//    ->where(['create_time >' => '2017-07-01 0:00', 'create_time <' => '2017-07-05 0:00', 'processor'=>'gpay'])
    // or gpay
    ->order(['create_time' => 'ASC']);
/*
 *
$query->hydrate(false);

//$res = $query->toList();
*/
$res = $query->toArray();
//Add data to Array
foreach ($res as $k=>$r) {
    //$r['id']='594a154016b1500009434';
    //var_dump($r);
    printf("[%s] %s / %s\n", $r['id'] ,$r['log_id'], $r['req_id']);
    $log->info("{$r['id']}: {$r['log_id']},{$r['req_id']}");

    $processor = strtolower($r['processor']);
    $code = $r['return_code'];
    switch ($processor) {
        case 'ght':
            //dev
            //$ght = new gaohuitong_pay(false);
            $ght = new gaohuitong_pay(true);
            $returns = $ght->query(['order_id'=>$r['id']]);

            parse_str($returns, $results);
            var_dump($results);
            $log->info("result", $results);
            if (empty($results['return_code']) || empty($results['err_msg']))
                continue;
            if ($code==$results['return_code']) {
                $log->info("skip for same code: $code");
                continue;
            }
            //update api_log
            $status = ($results['code']=='1');
            $dba = ['status'=>$status, 'callback' => urldecode($results['xml']) , 'return_code'=> $results['return_code'], 'return_msg'=>$results['err_msg'], 'callback_time'=> date('Y-m-d H:i:s') ];

            /*
            $apis->patchEntity($r, $dba,['validate' => false]);
            //var_dump($r);
            $apis->save($r, ['associated' => []]);
*/
            $log->info("update $table", $dba);
            DB::update($table, $dba, "id=%s", $r['id']);
            sleep(3);
            break;
        case 'gpay':
            $gpay = new ChinaGPayAPI(true);
            $res = $gpay->query($r['id'], false);
            var_dump($res);
            $msg = $res["transaction"]["respMsg"];
            $msg = $gpay->decodeMsg($msg);
            printf("respMsg:%s\n", $msg);

            if ($res["transaction"]["respCode"]!='1001' && $r['status']==1) {
                print("MISMATCH [{$r['log_id']}]\n");
                sleep(5);
                //return;
            }
            sleep(1);
            break;
    }

}

