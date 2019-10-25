<?php
require dirname(__DIR__) . '/config/bootstrap.php';
include ROOT.'/vendor/autoload.php';

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

//Avoda query status
$reader->updateAllAvodaApiCallback();

//select request with pending status, 200X
$table = 'remittance_api_log';
$apis = TableRegistry::get($table);
$query = $apis->find('all')
    ->where(['status' => 1, 'processor'=>'ght', 'return_code like'=>'200%', 'callback is'=> null])
    // or gpay
    ->orWhere(['status' => 0, 'processor'=>'gpay', 'return_code like'=>'1111'])
    ->andWhere(['create_time >=' => date('Y-m-d H:i:s', strtotime('-7 days'))])
    ->order(['create_time' => 'DESC']);

$log->info($query);
/*
 *
$query->hydrate(false);

//$res = $query->toList();
*/
$res = $query->toArray();
//Add data to Array
$log->info('count:', [count($res)]);

foreach ($res as $k=>$r) {
    //$r['id']='594a154016b1500009434';
    //var_dump($r);
    printf("[%s] %s / %s\n", $r['id'] ,$r['log_id'], $r['req_id']);
    $log->info("{$r['id']}: {$r['log_id']},{$r['req_id']}");

    $bid = $r['batch_id'];
    $processor = strtolower($r['processor']);
    $code = $r['return_code'];
    switch ($processor) {
        case 'ght':
            //dev
            //$ght = new gaohuitong_pay(false);
            $ght = new gaohuitong_pay(true);
            $returns = $ght->query(['order_id'=>$r['id']]);

            parse_str($returns, $results);
            //var_dump($results);
            $log->info("result", $results);
            if (empty($results['return_code']) || empty($results['err_msg']))
                continue;
            if ($code==$results['return_code']) {
                $log->info("skip for same code: $code");
                continue;
            }
            //update api_log
            $status = ($results['code']=='1');
            $logstatus = ($status?RemittanceReportReader::RM_STATUS_OK:RemittanceReportReader::RM_STATUS_FAILED);

            $dba = ['status'=>$status, 'callback' => urldecode($results['xml']) , 'return_code'=> $results['return_code'], 'return_msg'=>$results['err_msg'], 'callback_time'=> date('Y-m-d H:i:s') ];

            /*
            $apis->patchEntity($r, $dba,['validate' => false]);
            //var_dump($r);
            $apis->save($r, ['associated' => []]);
*/
            $log->info("update $table", $dba);
            DB::update($table, $dba, "id=%s", $r['id']);

            $reader->setBatchLogStatus($bid, $r['log_id'], $logstatus);
            //update batch count & total
            $reader->updateBatch($bid, $rate_update=FALSE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=true);

            break;
        case 'gpay':
            /*
respcode	msg	Transaction State
1001	交易成功	OK
1111	交易进行中	Processing
Other		Failed
            */
            $gpay = new ChinaGPayAPI(true);
            $results = $gpay->query($r['id'], false);

            $log->info("result", $results);
            var_dump($results);
            $msg = $results["transaction"]["respMsg"];
            $msg = $gpay->decodeMsg($msg);
            $log->info("msg", [$msg]);

            $rcode = trim($results["transaction"]["respCode"]);
            if ($code==$rcode) {
                $log->info("skip for same code: $code");
                continue;
            }
            //update api_log
            $status = ($rcode=='1001');
            $logstatus = ($status?RemittanceReportReader::RM_STATUS_OK_AMENDED:RemittanceReportReader::RM_STATUS_FAILED_AMENDED);
            $dba = ['status'=>$status, 'callback' => http_build_query($results), 'return_code'=> $rcode, 'return_msg'=>$msg, 'callback_time'=> date('Y-m-d H:i:s') ];
            $log->info("update $table", $dba);
            DB::update($table, $dba, "id=%s", $r['id']);

            $reader->setBatchLogStatus($bid, $r['log_id'], $logstatus);
            //update batch count & total
            $reader->updateBatch($bid, $rate_update=FALSE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=true);

            break;
    }
    sleep(3);
}

