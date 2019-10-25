<?php
require dirname(__DIR__) . '/config/bootstrap.php';
include ROOT . '/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\Mailer\Email;
use Cake\Mailer\Mailer;
use Cake\Routing\Router;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
// create a log channel
$log = new Logger('RemittanceEmail');
$log->pushHandler(new StreamHandler(dirname(__DIR__).'/logs/email.log', Logger::DEBUG));
/*
$email = new Email('default');
$email->domain('wecollect.com');
    $email->from(['apps@wecollect.com' => 'WeCollect'])
        ->to('mocpac@gmail.com')
        ->bcc('jo.ng@wecollect.com')
        ->subject('testing')
        ->template('auth')
        ->emailFormat('html')
        ->send();
exit;
*/
//for dev only
//$host = 'http://test.hk.wecollect.com/merchant_dev';
$host = 'http://apps.wecollect.com/merchant';
$reader = new RemittanceReportReader('srd_dev');
$lst = $reader->getAuthorizationList();

$email = new Email('default');
$email->domain('wecollect.com');

$log->info(sprintf("Email total:%d\n", count($lst)));

foreach ($lst as $u) {
    // https://apps.wecollect.com/merchant/remittance/authorize?key=e62efb53dc1dcbc96b625e4ea104ce5ddfd1f677
    /*
    $url = $this->Html->url(
        //'Authorize',
        array(
            'controller' => 'RemittanceBatch',
            'action' => 'authorize',
            'full_base' => true,
            '?' => ['key' => $u['id']],
        )
    );
    */
    //var_dump($u);
    $token = $u['id'];
    $batch_id = $u['batch_id'];
    $first_name = $u['first_name'];
    //$activationUrl = Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorize', '_full'=>true,'?' => ['key' => $token]]);
    //$u['url'] = $activationUrl = $host.Router::url(['controller' => 'RemittanceBatch', 'action' => 'authorizeBatch', '_full'=>false,'?' => ['id' => $token]]);
    $u['url'] = $activationUrl = $host."/remittance/authorize/$token";
    $log->info('rec:', $u);

    $subject="Remittance Authorization Request (Batch ID: $batch_id)";
    $email->viewVars($u);
    //send email
    $email->from(['apps@wecollect.com' => 'WeCollect'])
        ->to($u['email'])
//        ->bcc('jo.ng@wecollect.com')
        ->subject($subject)
        ->template('auth')
        ->emailFormat('html')
        ->send();
    //update status
    $reader->setAuthorizationStatus($token);

}

