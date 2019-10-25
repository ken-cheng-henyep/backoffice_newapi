<?php
/*
 * permission sample:
 * fxrate_lookup
 * remittance_upload
 * remittance_search
 * remittance_update
 * remittance_approve
 * gpay_status_update
 * civs_usage_report
 *
 */

require dirname(__DIR__) . '/config/bootstrap.php';
include 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;

$reader = new RemittanceReportReader('srd_dev');
$reader->updateAllAvodaApiCallback();

exit;

$symbol = 'HKD';
$symbol = 'USD';

$amt = 3.145566;
printf("%s\n", $amt);
wcRoundNumber($amt, true);
printf("%f\n", $amt);
printf("%s\n", $amt);

$a = ['total'=>1.25678, 'other'=>123];
var_dump($a);
$n = wcRoundNumber($a['total']);
var_dump($a);
var_dump($n);
exit;

var_dump(preg_match("/^CNY/", 'JPYCNY'));
var_dump(preg_match("/^$symbol/", 'HKDR01'));

exit;
$reader = new RemittanceReportReader('srd_dev');
$date='14.08.2017';
$date='14/8/2017';

printf("%d, %s\n", strtotime($date), $reader->getExcelDate($date));
printf("%s\n", date('Y-m-d H:i',strtotime($date))) ;
exit;


$reader = new RemittanceReportReader('srd_dev');
$bid='58082ccf103e0';
printf("%s\n", $reader->exportJoinPayExcel($bid));
exit;

$txid='04d52418-7ca2-11e7-a4d2-0242ac110002';
$res = payconn_tx_details($txid);
var_dump($res);
exit;

$id='112345890604123';
$id='312345891204123';

$b = rid15($id);
var_dump($b);
exit;


function payconn_tx_details($txid)
{

//$transactionId = "04d52418-7ca2-11e7-a4d2-0242ac110002";

//$url = "https://service.wecollect.com/api/transaction/$transactionId/query";

    $url = "https://service.wecollect.com/api/transaction/$txid/query";
    $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOi0zNywidXNlck5hbWUiOiJ3Y21hc3RlciJ9.SM_lKUkCCmfKRchvdh10BhZunpYIt_G51k9y4Sj7YL4';

    $headers = array(
        'Content-Type: application/json',
        'Token: Bearer ' . $token
    );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch) != 0) {
        die('Curl Error : ' . curl_error($ch));
    }

    return $response;
}
// 15位身份证号
// 2013年1月1日起将停止使用
// http://www.gov.cn/flfg/2011-10/29/content_1981408.htm
function rid15($id) {
    if (strlen($id)!=15)
        return false;

    $pattern = '/[1-9]\d{5}(\d{2})(\d{2})(\d{2})\d{3}/';
    preg_match($pattern, $id, $matches);
    if (!$matches)
        return false;
    var_dump($matches);
    $y = '19' . $matches[1];
    $m = $matches[2];
    $d = $matches[3];

    /*
        date = new Date(y, m-1, d);
        return (date.getFullYear()===y && date.getMonth()===m-1 && date.getDate()===d);
    */
    $reader = new RemittanceReportReader();
    return $reader->isValidBirthday("$y$m$d");
}

$amt = '12345.6789';
$amt = '12345.78';
$amt = '4.10';
preg_match('/^[0-9]+(\.[0-9]{1,2})?/', $amt, $matches);
var_dump($matches);

exit;
$reader = new RemittanceReportReader('srd_dev');
$reader->addBlacklistFilter('id_number', '987654321099999x', $mid='testonly');
exit;

$xml = '<?xml version="1.0" encoding="GBK"?><GHT><INFO><TRX_CODE>100005</TRX_CODE><VERSION>04</VERSION><DATA_TYPE>2</DATA_TYPE><REQ_SN>58e4a2a5099c600003924</REQ_SN><RET_CODE>0000</RET_CODE><ERR_MSG>交易处理成功</ERR_MSG><SIGNED_MSG>08d8abbe3f9973699919816ac14f410e5aeb59524e888821c399f2fef5f4820d69cef711bae289e2c87f32822ace66b7fe18d4255cf3e90ffe05dc8b5dd2c5fbd7e84bd6ce349e935f46244593771837aca1dbc65ee1919b8ae240e62d3fa3e38c69febd99abc439b0e358b2f0a307b82e56f0b6b9c5a887a8fc4b31bb10bfb5</SIGNED_MSG></INFO><BODY><RET_DETAILS><RET_DETAIL><SN>0001</SN><ACCOUNT_NO>6216666100001511863</ACCOUNT_NO><ACCOUNT_NAME>赵正山</ACCOUNT_NAME><AMOUNT>2737800</AMOUNT><CUST_USERID></CUST_USERID><RET_CODE>0000</RET_CODE><ERR_MSG>CI:交易成功</ERR_MSG><REMARK/><RESERVE1/><RESERVE2/></RET_DETAIL></RET_DETAILS></BODY></GHT>';
/*
 *
 * $xml = '<?xml version="1.0"?><GHT><INFO><TRX_CODE>100005</TRX_CODE><VERSION>04</VERSION><DATA_TYPE>2</DATA_TYPE><REQ_SN>58e4a2a5099c600003924</REQ_SN><RET_CODE>0000</RET_CODE><ERR_MSG>交易处理成功</ERR_MSG><SIGNED_MSG>08d8abbe3f9973699919816ac14f410e5aeb59524e888821c399f2fef5f4820d69cef711bae289e2c87f32822ace66b7fe18d4255cf3e90ffe05dc8b5dd2c5fbd7e84bd6ce349e935f46244593771837aca1dbc65ee1919b8ae240e62d3fa3e38c69febd99abc439b0e358b2f0a307b82e56f0b6b9c5a887a8fc4b31bb10bfb5</SIGNED_MSG></INFO><BODY><RET_DETAILS><RET_DETAIL><SN>0001</SN><ACCOUNT_NO>6216666100001511863</ACCOUNT_NO><ACCOUNT_NAME>赵正山</ACCOUNT_NAME><AMOUNT>2737800</AMOUNT><CUST_USERID></CUST_USERID><RET_CODE>0000</RET_CODE><ERR_MSG>CI:交易成功</ERR_MSG><REMARK/><RESERVE1/><RESERVE2/></RET_DETAIL></RET_DETAILS></BODY></GHT>';
 */
$xml = str_replace(' encoding="GBK"','',$xml);
var_dump(simplexml_load_string($xml));

exit;

$logger = new Logger('wc_logger');
	if (!defined('ROOT'))
            define('ROOT',dirname(__DIR__));
$logger->pushHandler(new StreamHandler(ROOT.'/logs/test.log', Logger::DEBUG));

$gpay = new ChinaGPayAPI();

$params=['gpay_code'=>'03080000','id'=>'wctest12345','beneficiary_name'=>'王','amount'=>10, 'account'=>'12345678','currency'=>'CNY'];
$gpay->sendRemittance($params);
exit;

$url='http://404test.com';
$url='http://gmail.com?';
$gpay->sendQuery($url, $data=null);

exit;

$data = [0=>['ac_no'=>'549440153990308549440153990308', 'key'=>'549440153990399', 'bank_no'=>'54944015399039988'],
    1=>['ac_no'=>'549440153990308549440153990308', 'key'=>'549440153990399', 'bank_no'=>'0054944015399039988']
];

print saveToExcel($data, $filename="./tmp/test0213.xlsx");
exit;

$city='BOCQBY';
printf("%s\n", preg_replace('/QBY$/','',$city));
exit;

$city='广市州市';
printf("%s\n", preg_replace('/市$/','',$city));
exit;

use Cake\Mailer\Email;
$email = new Email('default');
$st = $email->from(['apps@wecollect.com' => 'My Apps'])
    //->to('mocpac@gmail.com')
    ->to('jo.ng@wecollect.com')
    ->subject('About WC')
    ->send('My message:'.time());

var_dump($st);
exit;

$pc = new PayConnectorAPI();
$f="./tests/PC_Transaction_State1476935868.xls";
$f='./tests/Transaction_History1477281889.xls';

// 订单日期(Order date)	订单号(Order No.)	商户订单号(Merchant order No.)	商户号(Merchant No.)	商户名称(Merchant name)	交易类型(trade type)	交易状态(trade status)	姓名(name)	span	银行名称(bank name)	交易金额(元)[transaction amount(RMB)]	交易手续费(元)[transaction fee(RMB)]	交易结果(trade results)
$key='订单日期(Order date)';
$key='订单号(Order No.)';
$key='商户订单号(Merchant order No.)';
$key='交易金额(元)[transaction amount(RMB)]';
$key='交易手续费(元)[transaction fee(RMB)]';

var_dump($pc->updateAllBankCode());
exit;

    function sendQuery($url, $data, $postdata=true)
    {
	global $logger;
//        $this->logger->debug("sendQuery($url)", [$this->orderId]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /*
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // server max_execution = 120
        */
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // server max_execution = 360
        if ($postdata) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (strpos($url,'https://')!==FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
/*
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            self::USERAGENT_STRING
        );
*/
        $content = curl_exec($ch);
	var_dump(curl_getinfo($ch));
        $logger->debug("curl_getinfo", curl_getinfo($ch));
	if(curl_errno($ch))
{
        $logger->debug("error:". curl_error($ch));
    echo 'Curl error: ' . curl_error($ch);
}

        //$this->logger->debug(sprintf('return: "%s"', $content), [$this->orderId]);

        return $content;
    }

function saveToExcel($data, $filename, $ext = '.xlsx') {
    if (!is_array($data))
        return FALSE;

    $excel = new \PHPExcel();
    $excel->setActiveSheetIndex(0);


    $meta = array_keys(array_change_key_case($data[0]));
    PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_WcColumnValueBinder($meta));

    $excel->getActiveSheet()->fromArray($meta, null, 'A1');
    $excel->getActiveSheet()->fromArray($data, null, 'A2');
    //column auto width
    $lastCol = \PHPExcel_Cell::stringFromColumnIndex(count($meta)-1);
    $row = count($data)+2;

    foreach (range('A', $lastCol) as $colidx)
        $excel->getActiveSheet()->getColumnDimension($colidx)->setAutoSize(true);
    /*
    foreach ($meta as $k=>$v) {
        if (preg_match('/_no$/i', $v)!=false) {
            $colkey = \PHPExcel_Cell::stringFromColumnIndex($k);
            print("col:$colkey$row\n");
            //$excel->getActiveSheet()->getStyle("{$colkey}2:{$colkey}$row")->getNumberFormat()->setFormatCode('0');
            $excel->getActiveSheet()->getStyle("{$colkey}2:{$colkey}$row")->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_TEXT);
            //$excel->getActiveSheet()->getStyle("{$colkey}2:{$colkey}$row")->getNumberFormat()->setFormatCode('General');
        }
    }
    */
    //$excel->getActiveSheet()->fromArray($data, null, 'A2');
    //PHPExcel_Cell_DataType::TYPE_STRING

    /*
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="your_name.xls"');
header('Cache-Control: max-age=0');
*/
    //$writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
    $basef1 = basename($filename);
    $basef2 = str_replace(['/',' '],'-', $basef1);
    //$this->logger->debug("saveToExcel: str_replace($basef1, $basef2, $filename)");

    $filename = str_replace($basef1, $basef2, $filename);
    $filename.= $ext;
    $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
    $writer->save($filename);
    return $filename;
}
?>
