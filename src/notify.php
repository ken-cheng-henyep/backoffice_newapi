<?php
require dirname(__DIR__) . '/config/bootstrap.php';
include ROOT.'/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Mailer\Email;
use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
// create a log channel
$log = new Logger('RemittanceNotify');
$log->pushHandler(new StreamHandler(dirname(__DIR__).'/logs/notify.log', Logger::DEBUG));

//for dev only
//$host = 'http://test.hk.wecollect.com/merchant_dev';
$host = 'http://apps.wecollect.com/merchant';
$backoffice_host = 'http://backoffice.hk.wecollect.com/admin';

$reader = new RemittanceReportReader('srd_dev');
$method = 'email';
$lst = $reader->getAllNotifications($method);

$email = new Email('default');
$email->domain('wecollect.com');

$log->info(sprintf("Notify email total:%d\n", count($lst)));

foreach ($lst as $u) {
    //var_dump($u);
    $id = $u['id'];
    $uid = $u['users_id'];
    $batch_id = $u['batch_id'];
    $u['txid'] = $txid = (empty($batch_id)?$u['req_id']:$batch_id);
    $method = $u['method'];
    $state = $u['state'];
    $key = $u['signkey'];
    $type = $u['type'];

    if (empty($txid))
        continue;

    $return = false;
    switch ($method) {
        case 'post':
            $return = sendPost($id, $txid, $u['url'], $state, $key, $type);
            break;
        case 'email':
            if ($uid=='internal')
                sendInternalMail($email, $id, $u);
            else
                sendMail($email, $id, $u, $type);
            break;
    }
}

function sendMail(&$client, $id, $u, $type=1) {
    global $host, $reader, $log;
    //$activationUrl = Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorize', '_full'=>true,'?' => ['key' => $token]]);
    //$u['url'] = $activationUrl = $host.Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorizeBatch', '_full'=>false,'?' => ['id' => $token]]);

    if ($type==2) {
        // instant_request
        $u['url'] = $host."/remittance/instant-tx/txid/".$u['req_id'];
        $u['status'] = RemittanceReportReader::getInsReqStatus($u['state']);
        $subject="Instant Transaction State Change Notification (201)";
        $tpl = 'notify_instant_state';
    }  else {
        $u['url'] = $host."/remittance/batch/".$u['batch_id'];
        $u['status'] = RemittanceReportReader::getStatus($u['state']);
        $subject="Remittance Batch State Change Notification (200)";
        $tpl = 'notify_batch_state';
    }

    $log->info('rec:', $u);

    if (empty($u['email']))
        return false;

    $client->viewVars($u);
    //send email
    $return = $client->from(['apps@wecollect.com' => 'WeCollect'])
        ->to($u['email'])
//        ->bcc('jo.ng@wecollect.com')
        ->subject($subject)
        ->template($tpl)
        ->emailFormat('html')
        ->send();

    //$log->info("sendMail($id, {$u['email']}, $subject)",$return);
    $log->info("sendMail($id, {$u['email']}, $subject)");
    //update status
    $reader->updateNotification($id, ['data'=>$subject, 'response'=>true ]);
}

function sendInternalMail(&$client, $id, $u) {
    global $host, $backoffice_host, $reader, $log;
    //$activationUrl = Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorize', '_full'=>true,'?' => ['key' => $token]]);
    //$u['url'] = $activationUrl = $host.Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorizeBatch', '_full'=>false,'?' => ['id' => $token]]);
    $u['url'] = $backoffice_host."/remittance/batch/".$u['batch_id'];
    $u['status'] = RemittanceReportReader::getStatus($u['state']);
    $log->info('rec:', $u);

    if (empty($u['email']))
        return false;
    $subject="Batch Remittance Approval Request (100)";
    $client->viewVars($u);
    //send email
    $return = //$client->from(['backoffice@wecollect.com' => 'WeCollect Back Office System'])
        $client->from(['apps@wecollect.com' => 'WeCollect'])
        ->to($u['email'])
//        ->bcc('jo.ng@wecollect.com')
        ->subject($subject)
        ->template('notify_batch_internal')
        ->emailFormat('html')
        ->send();

    //$log->info("sendMail($id, {$u['email']}, $subject)",$return);
    $log->info("sendMail($id, {$u['email']}, $subject)");
    //update status
    $reader->updateNotification($id, ['data'=>$subject, 'response'=>true ]);
}

/*
function sendPost($id, $bid, $url, $state, $key, $title='Batch State Changed', $code='200') {
    global $reader, $log;

    if (empty($url))
        return false;

    $data = array();
    $data['code'] = $code;
    $data['notification'] = $title;
    $data['batch_id'] = $bid;
    $data['state'] = RemittanceReportReader::getStatus($state);
    $data['signature'] = $reader->getNotificationSignature($bid, $data['state'], $key);

    $post = http_build_query($data);
    $return = simpleCallURL($url, $post);

    $log->info("sendPost($url, $bid, $state, $key, $title, $code)\n$post,\n$return");

    $reader->updateNotification($id, ['data'=>$post, 'response'=>$return]);
    return true;
}
*/
function sendPost($id, $bid, $url, $state, $key, $type =1, $title='Batch State Changed', $code='200') {
    global $reader, $log;

    if (empty($url))
        return false;

    $data = array();
    if ($type==2) {
        // instant_request
        $title = 'Instant Transaction State Changed';
	    $code = '201';
        $data['id'] = $bid;
        $data['state'] = RemittanceReportReader::getInsReqStatus($state);
    } else {
        $data['batch_id'] = $bid;
        $data['state'] = RemittanceReportReader::getStatus($state);
    }
    $data['signature'] = $reader->getNotificationSignature($bid, $data['state'], $key);
    $data['code'] = $code;
    $data['notification'] = $title;

    $post = http_build_query($data);
    $return = simpleCallURL($url, $post);

    $log->info("sendPost($url, $bid, $state, $key, $title, $code)\n$post,\n$return");

    $reader->updateNotification($id, ['data'=>$post, 'response'=>$return]);
    return true;
}
