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
$reader = new RemittanceReportReader('srd_dev');
$method = 'post';
$lst = $reader->getAllNotifications($method);

$email = new Email('default');
$email->domain('wecollect.com');

$log->info(sprintf("Notify POST total:%d\n", count($lst)));

foreach ($lst as $u) {
    //var_dump($u);
    $id = $u['id'];
    $batch_id = $u['batch_id'];
    $method = $u['method'];
    $state = $u['state'];
    $key = $u['signkey'];

    if (empty($batch_id))
        continue;

    $return = false;
    switch ($method) {
        case 'post':
            $return = sendPost($id, $batch_id, $u['url'], $state, $key);
            break;
        case 'email':
            sendMail($email, $id, $u);
            break;
    }
}

function sendMail(&$client, $id, $u) {
    global $host, $reader, $log;
    //$activationUrl = Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorize', '_full'=>true,'?' => ['key' => $token]]);
    //$u['url'] = $activationUrl = $host.Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorizeBatch', '_full'=>false,'?' => ['id' => $token]]);
    $u['url'] = $host."/remittance/batch/".$u['batch_id'];
    $u['status'] = RemittanceReportReader::getStatus($u['state']);
    $log->info('rec:', $u);

    if (empty($u['email']))
        return false;
    $subject="Remittance Batch State Change Notification (200)";
    $client->viewVars($u);
    //send email
    $return = $client->from(['apps@wecollect.com' => 'WeCollect'])
        ->to($u['email'])
        ->bcc('jo.ng@wecollect.com')
        ->subject($subject)
        ->template('notify_batch_state')
        ->emailFormat('html')
        ->send();

    $log->info("sendMail($id, {$u['email']}, $subject)",$return);
    //update status
    $reader->updateNotification($id, ['data'=>$subject, 'response'=>true ]);
}

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