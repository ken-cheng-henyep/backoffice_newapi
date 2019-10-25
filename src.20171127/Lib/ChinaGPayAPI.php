<?php
/*
 网关、快捷收银台业务：gpay.chinagpay.com
代付API业务：remit.chinagpay.com
微支付、快捷、代扣、鉴权等API业务：api.chinagpay.com
同时，为了商户能够平滑迁移，原有域名pay.chinagpay.com会并行运行至7月31日。
2017
*/

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;


class ChinaGPayAPI  {
    const USERAGENT_STRING = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:14.0) Gecko/20100101 Firefox/14.0.1';
    const WECOLLECT_URL = 'https://secure.wecollect.com/postback/chinagpay';
    //PRD, updated on 2017/06/27
    const GPAY_BACKEND_URL = 'http://remit.chinagpay.com/bas/BgTrans';
    //const GPAY_BACKEND_URL = 'http://pay.chinagpay.com/bas/BgTrans'; //PRD
    //const GPAY_BACKEND_URL = 'http://27.115.49.122:38280/bas/BgTrans?'; // DEV
    const MERCHANT_ID = '929000000000015';
    const MERCHANT_KEY = 'zxM6YyhPfy5pjTHUj7JATmgKYda3hVDg';
    const WECOLLECT_CALLBACK_URL = 'https://apps.wecollect.com/gpay/callback.php';
    const LOG_TABLE = 'remittance_api_log';
    const TIMENOW_STR = 'Y-m-d H:i:s';

    var $orderId;
    //DB field to API field, empty means same name
    private $api_field_mappings = [
        //'transaction_id'=> ,
        'currency'=> '',
        'transaction_time'=> 'succTime',
        'merchant_order_no'=> 'merOrderId',
        'status'=>'decodeMsg',
        'merchant_no'=>'merId',
        //'amount'=>'txnAmt',
    ];

    function __construct($prd = true) {
        $this->debug= (!$prd);
        $this->logger = new Logger('wc_logger');
        if (!defined('ROOT'))
            define('ROOT',dirname(__DIR__));

        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/ChinaGPayAPI.log', Logger::DEBUG));
    }

    public function setOrderId($id) {
        $oid = str_pad($id, 8, '0', STR_PAD_LEFT);
        //return uniqid().$oid;
        //$this->orderId = uniqid().$oid;
        $tmp = preg_replace('/[^A-Za-z0-9]/','',uniqid().$oid);
        $this->orderId = substr($tmp, -64);
    }

    public function sendRemittance($params) {
        if (empty($params['gpay_code']))
            return false;

        $date=date('YmdHis');
        $this->setOrderId($params['id']);
        $amount = ($params['currency']=='CNY'?$params['amount']:$params['convert_amount']);

        if (isset($params['gross_amount_cny']))
            $amount = $params['gross_amount_cny'];
        /*
    'issInsProvince'=>'广东',
    'issInsCity'=>'深圳',
    'iss_ins_name'=>'中国银行',
*/
        /*
        $customerInfo=[
            'issInsProvince'=>'',
            'issInsCity'=>'',
            'iss_ins_name'=>'',
            //'certifTp'=>'01',
            'certifTp'=>'',
            'certify_id'=>'',
            'customerNm'=> $params['beneficiary_name'],
            'phoneNo'=>'',
        ];
        */
        //set uppercase for full English name
        if (isset($params['beneficiary_name'])) {
            $name=trim($params['beneficiary_name']);
            if (preg_match('/^([a-zA-Z\s\.]+)$/', $name))
                $name = strtoupper($name);
            $params['beneficiary_name'] = $name;
            $this->logger->debug('update beneficiary_name:', [$name]);
        }

        $customerInfo=[
            'customerNm'=> $params['beneficiary_name'],
        ];
        ksort($customerInfo);

        $amount = round($amount*100);
        $data =  [
            'signMethod'=>'MD5',
            'version'=>'1.0.0',
            'txnType'=>'12', 'txnSubType'=>'01',
            'bizType'=>'000401',  //代付
            'accessType'=>'0',
            'accessMode'=>'01',
            'merId'=> self::MERCHANT_ID,
            // Internal Id
            'merOrderId'=> $this->orderId,
            'accNo'=> $params['account'],
            //'accNo'=> '',   //test only
            // 01040000
            'bankId'=> str_pad($params['gpay_code'], 8, '0', STR_PAD_LEFT),
            'ppFlag'=>'01', //对私
            'customerInfo'=> json_encode($customerInfo),
            //channelId
            'txnTime'=>$date,
            'txnAmt'=> $amount,
            'currency'=>'CNY',
//    'backUrl'=>'http://api.wecollect.com:1234/cb/',
            'backUrl'=> self::WECOLLECT_CALLBACK_URL, //'https://api.wecollect.com/development-callback/',
            'payType'=>'0401', //代付
//    'payType'=>'0501',  //代付
        ];
        //test only
        if ($this->debug)
            $data['accNo']='';

        $data['signature'] = $this->getSignature($data, self::MERCHANT_KEY);

        $this->logger->debug('customerInfo:', $customerInfo);
        $this->logger->debug('request:', $data);
        if (!empty($data['customerInfo'])) {
            $data['customerInfo'] = base64_encode($data['customerInfo']);
        }

        $request = http_build_query($data);
        $url = self::GPAY_BACKEND_URL;  // . $request;
        //$response = $this->sendQuery($url, array(), false);
        $response = $this->sendQuery($url, $request);
        $status = false;

        if (isset($params['batch_id']))
            $logs = ['batch_id'=> $params['batch_id'], 'log_id'=> $params['id'], 'bank_code'=> $params['gpay_code'], 'request'=>$request, 'response'=>$response, 'status'=> $status
            , 'create_time'=> $date, 'complete_time'=> date(self::TIMENOW_STR)];
        else
            $logs = ['req_id'=> $params['id'], 'bank_code'=> $params['gpay_code'], 'request'=>$request, 'response'=>$response, 'status'=> $status
                , 'create_time'=> $date, 'complete_time'=> date(self::TIMENOW_STR)];

        if (! $this->verifySignature($response)) {
            $this->log2DB($logs);
            return false;
        }

        if ($response) {
            parse_str($response, $result);
            $this->logger->debug('result:', $result);
            /*
             * response {"txnType":"12","respCode":"1001","channelId":"chinaGpay","merId":"929000000000015","settleDate":"20170214","txnSubType":"01","txnAmt":"0000000000001000","currency":"CNY","version":"1.0.0",
             * "settleCurrency":"","signMethod":"MD5","settleAmount":"","respMsg":"5Lqk5piT5oiQ5Yqf","bizType":"000401","resv":"","merResv1":"","merOrderId":"wc1487046595",
             * "signature":"AJ4EOkesLLq9qaddKhoDGg==","succTime":"20170214123003","accessType":"0","txnTime":"20170214122955","msg":"u4ea4u6613u6210u529f"}
             */

            if (isset($result["respMsg"])) {
                $result["respMsg"] = $this->decodeMsg($result["respMsg"]);
                $logs['response'].="&decodeMsg={$result["respMsg"]}";
                $logs['return_msg'] = substr($result["respMsg"], 0, 512);

                $this->logger->debug("{$result["merOrderId"]} respMsg:", [$result["respMsg"]]);
            }

            if ($result['respCode'] == '1001') {
                //Transaction success
                $status = true;
            } else {
                //print_r($result);
            }

            //log to database
            $logs['status'] = $status;
            $logs['return_code'] = $result['respCode'];
            $this->log2DB($logs);

            return ['result'=>$status, 'return'=> $result, 'order_id'=>$this->orderId];
        } else {
            $this->logger->debug('No response');
        }

        $this->log2DB($logs);
        return false;

    }

    public function log2DB($arr) {
        //if (!is_array($arr) || empty($arr['batch_id']))
        if (!is_array($arr))
            return false;
        $arr['id'] = $this->orderId;
        $arr['processor'] = 'gpay';
        $arr['url'] = self::GPAY_BACKEND_URL;

        DB::insert(self::LOG_TABLE, $arr);
    }

    function getSignature(Array $params, $key)
    {
        unset($params['signMethod']);
        unset($params['signature']);
        if (isset($params["respMsg"])) {
            $params["respMsg"] = $this->decodeMsg($params["respMsg"]);
            $this->logger->debug("respMsg: {$params["respMsg"]}");
        }

        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= "{$k}={$v}&";
        }
        $str = substr($str, 0, -1);
        $str .= $key; // "88888888";r
        $signature = base64_encode(pack('H*', md5($str)));
        return $signature;
    }

    function verifySignature($response) {
        parse_str($response, $result);
        if (!is_array($result) || empty($result["signature"]))
            return false;
        $sign = $result["signature"];
        $sign2 = $this->getSignature($result, self::MERCHANT_KEY);
        $sign2 = urldecode($sign2);

        $this->logger->debug(sprintf("verifySignature:\n%s VS %s\n", $sign, $sign2), $result);
        return ($sign==$sign2);
    }

    function sendQuery($url, $data, $postdata=true)
    {
        $this->logger->debug("sendQuery($url)", [$this->orderId]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        /*
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // server max_execution = 120
        */
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 180);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180); // server max_execution = 360
        if ($postdata) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (strpos($url,'https://')!==FALSE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            self::USERAGENT_STRING
        );
        $content = curl_exec($ch);
        $this->logger->debug(sprintf('return: "%s"', $content), [$this->orderId]);
        if(($errno = curl_errno($ch))) {
            $this->logger->debug("Curl error: ($errno) ". curl_error($ch));
            $this->logger->debug("Curl info:", curl_getinfo($ch));
        }

        return $content;
    }

    function resend2WC($data)
    {
        //$url = 'https://secure.wecollect.com/postback/chinagpay';
        $url = self::WECOLLECT_URL;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        $this->logger->debug("resend2WC", $data, [$response]);
        //echo "\n wecollect response.. :" . $data['merOrderId'] . ':' . $response;
        return $response;
    }

    //100170,123456
    function querys($req) {
        if (empty($req))
            return false;
        $req = trim($req,' ,');
        $ids = explode(',', $req);
        if (!is_array($ids))
            return false;
        $results = array();
        foreach ($ids as $id) {
            if (empty($id))
                continue;
            $results[] = $this->query($id);
        }
        return $results;
    }

    function query($merOrderId, $resend2WC=true)
    {
        $v = array(
            'signMethod' => 'MD5',
            'version' => '1.0.0',
            'txnType' => '00',
            'txnSubType' => '01',
            'merId' => self::MERCHANT_ID,
            'merOrderId' => $merOrderId
        );
        $v['signature'] = $this->getSignature($v, self::MERCHANT_KEY);

        $url = self::GPAY_BACKEND_URL .'?'. http_build_query($v);

        $response = $this->sendQuery($url, array(), false);

        if (! $this->verifySignature($response))
            return false;

        if ($response) {
            parse_str($response, $result);
            $this->logger->debug('result:', $result);

            if ($result['respCode'] == '1001') {
                //Transaction success
                //echo "\n need to send... :" . $merOrderId;
                $this->logger->debug("query($merOrderId): resend now");
                /*
                 *
                 */
                if ($resend2WC) {
                    $return = $this->resend2WC($result);
                    $this->logger->debug("return:\n$return");
                    return ['return' => trim($return), 'transaction' => var_export($result, true), 'id' => $merOrderId];
                } else {
                    return ['return' => trim($return), 'transaction' => $result, 'id' => $merOrderId];
                }
            } else {
                //print_r($result);
                $msg = $result["respMsg"];
                $msg = $this->decodeMsg($msg); //base64_decode(str_replace(' ','+',$msg));
                $this->logger->debug("query($merOrderId) result (not success transaction), msg=$msg", $result);

                return ['return'=>"OrderId NOT FOUND ($msg)", 'transaction'=>var_export($result,true), 'id'=>$merOrderId];
            }

        } else {
            //echo "\n error...:: " . $merOrderId;
            $this->logger->debug('No response');
        }

        return false;
    }

    function decodeMsg($m) {
        //if (!isset($r['respMsg']))
        if (empty($m))
            return false;
        return base64_decode(str_replace(' ','+',$m));
    }

    function mapApiFields($txid, $data) {
        /*
         *  txnType=01&respCode=1001&canRefAmt=0000000001234908&refCnt=0&channelId=chinaGpay&refAmt=0000000000000000&merId=929000000000015&settleDate=20170207&txnSubType=01&txnAmt=0000000001234908
         * &currency=CNY&version=1.0.0&settleCurrency=CNY&signMethod=md5&settleAmount=1231203&respMsg=5Lqk5piT5oiQ5Yqf&bizType=000201&resv=&merResv1=&merOrderId=269652&signature=th8xczVr8KsBdL1LQawTUw==
         * &txnTime=20170206220228&succTime=20170207061828&accessType=0
         */
        if (empty($txid) || !is_array($data))
            return false;
        $rtn = array();
        //get fee
        $total = intval($data['txnAmt']);   //transaction amt
        $rtn['amount'] = $total/100;

        if (isset($data['txnAmt']) && isset($data['settleAmount'])) {
            $settle = intval($data['settleAmount']);    //settle amt
            if ($total>0 && $settle>0) {
                $rtn['fee'] = ($total - $settle) / 100;
            }
        }

        foreach ($this->api_field_mappings as $k=>$v) {
            if (empty($v))
                $rtn[$k] = $data[$k];
            elseif (isset($data[$v]))
                $rtn[$k] = $data[$v];

        }
        return $rtn;
    }

}
