<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;

class gaohuitong_pay
{
    private $user_name;
    private $merchant_id;
    private $private_key_pw;
    private $pfx_path, $cert_path;
    private $url;
    private $send_data;
    private $ret_data;
    //private $log_file = './logs/ght_pay.log';
    private $log_file = '/logs/ght_pay.log';
    private $callback_url = 'https://apps.wecollect.com/gpay/ght_callback.php';

    private $msg_real_time = array(
        '0000' => array(3, '处理完成'),
        '0001' => array(2, '系统处理失败'),
        '0002' => array(2, '已撤销'),
        '1000' => array(2, '报文内容检查错或者处理错'), //具体内容见返回错误信息
        '1001' => array(2, '报文解释错'),
        '1002' => array(2, '无法查询到该交易，可以重发'),
        '2000' => array(3, '系统正在对数据处理'),
        '2007' => array(3, '提交银行处理'),
        '3028' => array(2, '系统繁忙'),
        '3045' => array(2, '协议未生效'), //例工行协议同步
        '3097' => array(2, '渠道不支持或者商户不支持此渠道'),
    );
    private $msg_query_head = array(
        '0000' => array(3, '处理完成'),
        '0001' => array(2, '系统处理失败'),
        '0002' => array(2, '已撤销'),
        '1000' => array(2, '报文内容检查错或者处理错'), //具体内容见返回错误信息
        '1001' => array(2, '报文解释错'),
        '1002' => array(2, '无法查询到该交易，可以重发'),
        '2000' => array(3, '系统正在对数据处理'),
        '2001' => array(3, '等待商户审核'),
        '2002' => array(2, '商户审核不通过'),
        '2003' => array(3, '等待高汇通受理'),
        '2004' => array(2, '高汇通不通过受理'),
        '2005' => array(3, '等待高汇通复核'),
        '2006' => array(2, '高汇通不通过复核'),
        '2007' => array(3, '提交银行处理'),
    );
    private $msg_query_detail = array(
        '0000' => array(1, '交易成功'),
        //added by jo
        '0001' => [2, '交易失败具体原因在ERR_MSG 中说明'],
        '3001' => array(2, '查开户方原因'),
        '3002' => array(2, '没收卡'),
        '3003' => array(2, '不予承兑'),
        '3004' => array(2, '无效卡号'),
        '3005' => array(2, '受卡方与安全保密部门联系'),
        '3006' => array(2, '已挂失卡'),
        '3007' => array(2, '被窃卡'),
        '3008' => array(2, '余额不足'),
        '3009' => array(2, '无此账户'),
        '3010' => array(2, '过期卡'),
        '3011' => array(2, '密码错'),
        '3012' => array(2, '不允许持卡人进行的交易'),
        '3013' => array(2, '超出提款限额'),
        '3014' => array(2, '原始金额不正确'),
        '3015' => array(2, '超出取款次数限制'),
        '3016' => array(2, '已挂失折'),
        '3017' => array(2, '账户已冻结'),
        '3018' => array(2, '已清户'),
        '3019' => array(2, '原交易已被取消或冲正'),
        '3020' => array(2, '账户被临时锁定'),
        '3021' => array(2, '未登折行数超限'),
        '3022' => array(2, '存折号码有误'),
        '3023' => array(2, '当日存入的金额当日不能支取'),
        '3024' => array(2, '日期切换正在处理'),
        '3025' => array(2, 'PIN格式出错'),
        '3026' => array(2, '发卡方保密子系统失败'),
        '3027' => array(2, '原始交易不成功'),
        '3028' => array(3, '系统忙，请稍后再提交'),
        '3029' => array(2, '交易已被冲正'),
        '3030' => array(2, '账号错误'),
        '3031' => array(2, '账号户名不符'),
        '3032' => array(2, '账号货币不符'),
        '3033' => array(2, '无此原交易'),
        '3034' => array(2, '非活期账号，或为旧账号'),
        '3035' => array(2, '找不到原记录'),
        '3036' => array(2, '货币错误'),
        '3037' => array(2, '磁卡未生效'),
        '3038' => array(2, '非通兑户'),
        '3039' => array(2, '账户已关户'),
        '3040' => array(2, '金额错误'),
        '3041' => array(2, '非存折户'),
        '3042' => array(2, '交易金额小于该储种的最低支取金额'),
        '3043' => array(2, '未与银行签约'),
        '3044' => array(2, '超时拒付'),
        '3045' => array(2, '合同（协议）号在协议库里不存在'),
        '3046' => array(2, '合同（协议）号还没有生效'),
        '3047' => array(2, '合同（协议）号已撤销'),
        '3048' => array(2, '业务已经清算，不能撤销'),
        '3049' => array(2, '业务已被拒绝，不能撤销'),
        '3050' => array(2, '业务已撤销'),
        '3051' => array(2, '重复业务'),
        '3052' => array(2, '找不到原业务'),
        '3053' => array(2, '批量回执包未到规定最短回执期限（M日）'),
        '3054' => array(2, '批量回执包超过规定最长回执期限（N日）'),
        '3055' => array(2, '当日通兑业务累计金额超过规定金额'),
        '3056' => array(2, '退票'),
        '3057' => array(2, '账户状态错误'),
        '3058' => array(2, '数字签名或证书错'),
        '3097' => array(2, '系统不能对该账号进行处理'),
        '3999' => array(3, '交易失败，具体信息见中文'), //对于不能明确归入上面的情况置为该反馈码
    );
    private $public_key = '';

    const LOG_TABLE = 'remittance_api_log';
    private $return_code;
    var $orderId, $logger;

    public function __construct($prd = true)
    {
        if (! $prd) {
            //$this->user_name = 'merchant01';          //测试的用户名
            //$this->merchant_id = '890980900809898';     //测试的商户号
            $this->user_name = '000000000100641';
            $this->merchant_id = '000000000100641';
            $this->pfx_path = __DIR__ . "/../../cert/TESTUSER.pfx";
            //$this->cert_path = __DIR__ . "/../../cert/TESTUSER.cer";
            $this->cert_path = __DIR__ . "/../../cert/TESTUSER_x509.cer";
            $this->private_key_pw = '123456';
            $this->url = 'http://120.31.132.118:8080/d/merchant/';
        } else {
            $this->user_name = $this->merchant_id = '000000000101244';  // PRD
		// merchant private key
            $this->pfx_path = __DIR__ . "/../../cert/000000000101244.pfx";
            //$this->cert_path = __DIR__ . "/../cert/000000000101244.crt";
            $this->cert_path = __DIR__ . "/../../cert/ght_root.cer";

            $this->private_key_pw = '123456';           //私钥密码
            //$this->url = 'http://rps.gaohuitong.com:8443/d/merchant/';                //测试的接口地址
            $this->url = 'https://rps.gaohuitong.com:8443/d/merchant/'; //PRD
        }

        if (is_file($this->cert_path)) {
            //printf("cert:%s\n%s\n", $this->cert_path, file_get_contents($this->cert_path));
            $pub_key = openssl_pkey_get_public(file_get_contents($this->cert_path));
            $pub_keys = openssl_pkey_get_details($pub_key);
            if (!empty($pub_keys['key']))
                $this->public_key = $pub_keys['key'];
        }

        $this->logger = new Logger('wc_logger');
        if (!defined('ROOT'))
            define('ROOT',dirname(__DIR__));

        $this->log_file = ROOT.'/'.$this->log_file;
        $this->logger->pushHandler(new StreamHandler($this->log_file, Logger::DEBUG));

        $this->logger->debug("URL:".$this->url);
        $this->logger->debug("pfx_path:".$this->pfx_path);
        $this->logger->debug("cert_path:".$this->cert_path);
        //$this->logger->debug("public_key:".$this->public_key);
    }

    // Wecollect custom functions
    public function setOrderId($id) {
        $oid = str_pad($id, 8, '0', STR_PAD_LEFT);
        //return uniqid().$oid;
        //$this->orderId = uniqid().$oid;
        $tmp = preg_replace('/[^A-Za-z0-9]/','',uniqid().$oid);
        // CN(30)
        $this->orderId = substr($tmp, 0, 30);
    }

    static public function getTimeNow() {
        return date('Y-m-d H:i:s');
    }

    public function log2DB($arr) {
        if (!is_array($arr))
            return false;
        $arr['id'] = $this->orderId;
        $arr['processor'] = 'ght';
        $arr['url'] = $this->url;

        $this->logger->debug('log2DB', $arr);
        DB::insert(self::LOG_TABLE, $arr);
    }

    public function isSuccess($code) {
        /*
success code
0000	交易成功
2000 	系统正在对数据处理
2001 	等待商户审核
2002 	等待高汇通受理
2003 	等待高汇通复核
2004 	提交银行处理
     */
        if (!empty($code) && in_array($code, ['0000','2000','2001','2002','2003','2004']))
            return true;
        return false;
    }

    public function sendRemittance($params) {
        if (!is_array($params) || empty($params['ght_code'])) {
            $this->logger->debug('missing parameter', [$params]);
            return false;
        }

        $this->setOrderId($params['id']);
        $amount = ($params['currency']=='CNY'?$params['amount']:$params['convert_amount']);
        if (isset($params['gross_amount_cny']))
            $amount = $params['gross_amount_cny'];

        $infos = [
            'order_id'=>$this->orderId,
            'amount'=> round($amount*100),
            'bank_code'=> $params['ght_code'],
            //'account_no'=> '',  //test only
            'account_no'=> $params['account'],
            'account_name'=> $params['beneficiary_name'],
        ];
        $this->logger->debug('sendRemittance:'.$this->orderId, $infos);

        if (isset($params['batch_id']))
            $logs = ['batch_id'=> $params['batch_id'], 'log_id'=> $params['id'], 'bank_code'=> $params['ght_code'],
            //'request'=>$request, 'response'=>$response, 'status'=> $status,
            'create_time'=> self::getTimeNow(),
            ];
        else
            $logs = ['req_id'=> $params['id'], 'bank_code'=> $params['ght_code'],
                //'request'=>$request, 'response'=>$response, 'status'=> $status,
                'create_time'=> self::getTimeNow(),
            ];

        $response =  $this->pay($infos);
        // 'code=1&msg=交易成功';
        parse_str($response, $result);

        $this->logger->debug("response: $response");
        $this->logger->debug('result:', $result);
        $this->logger->debug('return_code:', [$this->return_code]);

        //$status = ($result['code']=='1');    //success or not, T/F
        $status = false;
        $logs['complete_time'] = self::getTimeNow();
        $logs['request'] = $this->send_data;
        $logs['request'] = iconv('GBK', 'UTF-8', $logs['request']);
        $logs['response'] = $this->ret_data;
        $logs['return_code'] = trim($this->return_code);
        if (isset($result['err_msg']))
            $logs['return_msg'] = substr($result['err_msg'], 0, 512);
        elseif (isset($result['msg']))
            $logs['return_msg'] = substr($result['msg'], 0, 512);
        /*
success code
0000	交易成功
2000 	系统正在对数据处理
2001 	等待商户审核
2002 	等待高汇通受理
2003 	等待高汇通复核
2004 	提交银行处理
         */
        /*
        if (!empty($logs['return_code']) && in_array($logs['return_code'], ['0000','2000','2001','2002','2003','2004']))
            $status = true;
        $logs['status'] = $status;
        */

        $status = $logs['status'] = $this->isSuccess($logs['return_code']);
        $this->log2DB($logs);

        //return $response;
        return ['result'=>$status, 'return'=> $result, 'order_id'=>$this->orderId];
    }
    // Wecollect custom functions END

    public function pay($info)
    {
        error_log("--------------------------分割线---------------------\n".'['.date('Y-m-d H:i:s').']$info:'."\n".var_export($info, true)."\n\n", 3, $this->log_file);
        $this->logger->debug("pay",[$info]);

        $this->set_data($info, 'pay');
        $this->curl_access($this->url);

        return $this->verify_ret('pay');
    }

    public function query($info)
    {
        $this->set_data($info, 'query');
        $this->curl_access($this->url);
        return $this->verify_ret('query');
    }

    /**
     * code的意义：
     * 1：支付成功
     * 2：支付失败
     * 3：结果不明确
     */
    private function verify_ret($type)
    {
        if (trim($this->ret_data) == '') {
            return 'code=3&msg=官方返回为空';
        }
        //convert to UTF-8 before processing
        //$this->ret_data = iconv('GBK','UTF-8',$this->ret_data);
        //$this->ret_data = str_replace(' encoding="GBK"','',$this->ret_data);

        error_log('RAW XML:'."\n".$this->ret_data."\n", 3, $this->log_file);
        //todo: detect XML encoding

        //$xml_obj convert to UTF-8 itself
        $xml_obj = @simplexml_load_string($this->ret_data);
        //$xml_obj = @simplexml_load_string(str_replace(' encoding="GBK"','',$this->ret_data));
        //var_dump($xml_obj);

        if (empty($xml_obj->INFO)) {
            return 'code=3&msg=官方返回格式错误';
        }
        $info = (array)$xml_obj->INFO;
        //error_log('['.date('Y-m-d H:i:s').']$xml_obj:'."\n".iconv('UTF-8', 'GBK', var_export($xml_obj, true))."\n\n", 3, $this->log_file);
        error_log('['.date('Y-m-d H:i:s').']$xml_obj:'."\n".print_r($xml_obj, true)."\n", 3, $this->log_file);

        //convert to UTF-8 before processing
        $this->ret_data = iconv('GBK','UTF-8',$this->ret_data);
        //校验签名
        $sign_data = preg_replace('/<SIGNED_MSG>(.+)<\/SIGNED_MSG>/', '', $this->ret_data);
        preg_match('/<SIGNED_MSG>(.+)<\/SIGNED_MSG>/', $this->ret_data, $match);
        $verify_result = $this->verify_sign($sign_data, $match[1]);
        if ($verify_result !== 1) {
            return 'code=3&msg=签名校验错误';
        }

        //处理返回数据
        $result = 'code=3&msg=未知结果';
        $this->return_code = $info['RET_CODE'];

        if ($type == 'pay') {
            if ($info['RET_CODE'] == '0000') {
                $ret_code = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->RET_CODE;
                // to save to database
                $this->return_code = $ret_code;
                if ($ret_code == '0000') {
                    $result = 'code=1&msg=交易成功';
                } elseif (isset($this->msg_real_time[$ret_code])) {
                    $result = 'code='.$this->msg_real_time[$ret_code][0].'&msg='.$this->msg_real_time[$ret_code][1];
                } else {
                    $err_msg = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG;
                    //$err_msg = iconv('UTF-8', 'GBK', $err_msg);
                    $result = 'code=3&msg='.$err_msg;
                }
            } elseif (isset($this->msg_real_time[$info['RET_CODE']])) {
                $result = 'code='.$this->msg_real_time[$info['RET_CODE']][0].'&msg='.$this->msg_real_time[$info['RET_CODE']][1];
            } else {
                $result = 'code=3&msg=请求已接收，结果需通过查询交易接口获取';
            }
            //details msg in XML
            if (isset($xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG)) {
                $err_msg = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG;
                $result.="&err_msg=$err_msg";
            }
        } elseif ($type == 'query') {
            if ($info['RET_CODE'] == '0000') {
                $this->return_code = $ret_code = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->RET_CODE;

                if ($ret_code == '3999') {
                    $err_msg = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG;
                    if ($err_msg) {
                        //$err_msg = iconv('UTF-8', 'GBK', $err_msg);
                        $result = 'code=3&msg='.$err_msg;
                    } else {
                        $result = 'code=3&msg='.$this->msg_query_detail[$ret_code][1];
                    }
                } elseif (isset($this->msg_query_detail[$ret_code])) {
                    $result = 'code='.$this->msg_query_detail[$ret_code][0].'&msg='.$this->msg_query_detail[$ret_code][1];
                }
            } elseif (isset($this->msg_query_head[$info['RET_CODE']])) {
                $result = 'code='.$this->msg_query_head[$info['RET_CODE']][0].'&msg='.$this->msg_query_head[$info['RET_CODE']][1];
            }
            //details msg in XML
            if (isset($xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG)) {
                $err_msg = (string)$xml_obj->BODY->RET_DETAILS->RET_DETAIL->ERR_MSG;
                $result .= "&err_msg=$err_msg";
            }

            printf("XML:%s\n", $this->ret_data);
            $result .= "&return_code=" . $this->return_code ."&xml=".urlencode($this->ret_data);
        }   //end query

        $this->logger->debug("verify_ret \$result:", [$result]);

        return $result;
    }

    private function set_data($info, $type = 'pay')
    {
        $xml = '';
        if ($type == 'pay') {
            $xml = '<GHT>
                    <INFO>
                        <TRX_CODE>100005</TRX_CODE>
                        <VERSION>04</VERSION>
                        <DATA_TYPE>2</DATA_TYPE>
                        <LEVEL>0</LEVEL>
                        <USER_NAME>'.$this->user_name.'</USER_NAME>
                        <REQ_SN>'.$info['order_id'].'</REQ_SN>
                        <SIGNED_MSG></SIGNED_MSG>
                    </INFO>
                    <BODY>
                    <TRANS_SUM>
                        <BUSINESS_CODE>09100</BUSINESS_CODE>
                        <MERCHANT_ID>'.$this->merchant_id.'</MERCHANT_ID>
                        <SUBMIT_TIME>'.date('YmdHis').'</SUBMIT_TIME>
                        <TOTAL_ITEM>1</TOTAL_ITEM>
                        <TOTAL_SUM>'.$info['amount'].'</TOTAL_SUM>
                    </TRANS_SUM>
                    <TRANS_DETAILS>
                        <TRANS_DETAIL>
                            <SN>0001</SN>
                            <BANK_CODE>'.$info['bank_code'].'</BANK_CODE>
                            <ACCOUNT_TYPE>00</ACCOUNT_TYPE>
                            <ACCOUNT_NO>'.$info['account_no'].'</ACCOUNT_NO>
                            <ACCOUNT_NAME>'.$info['account_name'].'</ACCOUNT_NAME>
                            <ACCOUNT_PROP>0</ACCOUNT_PROP>
                            <AMOUNT>'.$info['amount'].'</AMOUNT>
                            <CURRENCY>CNY</CURRENCY>
                            <TRADE_NOTIFY_URL>'.$this->callback_url.'</TRADE_NOTIFY_URL>
                            <REFUND_NOTIFY_URL>'.$this->callback_url.'</REFUND_NOTIFY_URL>
                            <REMARK></REMARK>
                        </TRANS_DETAIL>
                    </TRANS_DETAILS>
                    </BODY>
                </GHT>';
        } elseif ($type == 'query') {

            // <REQ_SN>'.'qy'.$info['order_id'].time().'</REQ_SN>
            $xml = '<GHT>
                    <INFO>
                        <TRX_CODE>200001</TRX_CODE>
                        <VERSION>03</VERSION>
                        <DATA_TYPE>2</DATA_TYPE>
                        <REQ_SN>'.$info['order_id'].'</REQ_SN>
                        <USER_NAME>'.$this->user_name.'</USER_NAME>
                        <SIGNED_MSG></SIGNED_MSG>
                    </INFO>
                    <BODY>
                        <QUERY_TRANS>
                            <QUERY_SN>'.$info['order_id'].'</QUERY_SN>
                        </QUERY_TRANS>
                    </BODY>
                </GHT>';
        }

        $xml = str_replace(array(' ', "\n", "\r"), '', $xml);
        $xml = '<?xml version="1.0" encoding="GBK"?>'.$xml;
        $this->logger->debug('xml after str_replace:', [$xml]);
        //fix English name
        if (isset($info['account_name']) && !empty($info['account_name'])) {
            $ac_name  = str_replace(array(' ', "\n", "\r"), '', $info['account_name']);
            if (!empty($ac_name) && $ac_name != $info['account_name']) {
                $xml = str_replace("<ACCOUNT_NAME>$ac_name</ACCOUNT_NAME>", "<ACCOUNT_NAME>{$info['account_name']}</ACCOUNT_NAME>", $xml);
                $this->logger->debug('xml after fix:', [$xml]);
            }
        }

        $sign_data = str_replace('<SIGNED_MSG></SIGNED_MSG>', '', $xml);
        $sign = $this->create_sign($sign_data);
        $xml = str_replace('<SIGNED_MSG></SIGNED_MSG>', '<SIGNED_MSG>'.$sign.'</SIGNED_MSG>', $xml);

        //error_log('['.date('Y-m-d H:i:s').']$xml:'."\n".$xml."\n\n", 3, $this->log_file);
        $this->logger->debug('xml after sign:', [$xml]);
        //$this->send_data = $xml;
        $this->send_data = iconv('UTF-8', 'GBK', $xml);

        error_log('GBK $xml:'."\n".$this->send_data."\n", 3, $this->log_file);
        $this->logger->debug('GBK $xml:', [$this->send_data]);
    }

    private function create_sign($data)
    {
        //$data = iconv('GBK', 'UTF-8', $data); //高汇通那边计算签名是用UFT-8编码
        $pkey_content = file_get_contents($this->pfx_path); //获取密钥文件内容
        openssl_pkcs12_read($pkey_content, $certs, $this->private_key_pw); //读取公钥、私钥
        //var_dump($certs);
        $pkey = $certs['pkey']; //私钥
        /*
        if (!empty($certs['cert']))
            $this->public_key = $certs['cert'];
*/
        openssl_sign($data, $signMsg, $pkey, OPENSSL_ALGO_SHA1); //注册生成加密信息
        $signMsg = bin2hex($signMsg);
        return $signMsg;
    }

    public function dump_cert() {
        $pkey_content = file_get_contents($this->pfx_path); //获取密钥文件内容
        openssl_pkcs12_read($pkey_content, $certs, $this->private_key_pw); //读取公钥、私钥
        var_dump($certs);
/*
        if (is_file($this->cert_path)) {
            //printf("cert:%s\n%s\n", $this->cert_path, file_get_contents($this->cert_path));
            $pub_key = openssl_pkey_get_public(file_get_contents($this->cert_path));
            //$pub_key = openssl_x509_parse(file_get_contents($this->cert_path));
            $pub_keys = openssl_pkey_get_details($pub_key);
        }
*/
        var_dump($this->public_key);
    }

    private function verify_sign($data, $sign) {

        //$data = iconv('GBK', 'UTF-8', $data); //高汇通那边计算签名是用UFT-8编码
        error_log(sprintf("verify_sign\n%s\n%s\n", $data, $sign), 3, $this->log_file);

        //XML converted to UTF-8 already
        $hex = $sign;
        $sign = $this->HexToString($sign);

        $public_key_id = openssl_pkey_get_public($this->public_key);
        $res = openssl_verify($data, $sign, $public_key_id);   //验证结果，1：验证成功，0：验证失败

        //error_log(sprintf("public_key:%s\n",$this->public_key), 3, $this->log_file);
        //error_log('['.date('Y-m-d H:i:s').']签名验证结果$res:'."\n".$res."\n\n", 3, $this->log_file);
        error_log('['.date('Y-m-d H:i:s')."]签名验证结果\nhex:$hex\n\$res:\n".$res."\n", 3, $this->log_file);
        return $res;
    }

    private function curl_access($url)
    {
        $ch = curl_init();
        /*
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,60);
        curl_setopt($ch,CURLOPT_TIMEOUT,60);
        */
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,180);
        curl_setopt($ch,CURLOPT_TIMEOUT,180);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POST,true);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$this->send_data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);

        if (strpos($url, 'https') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSLVERSION, 1);    //高汇通那边的版本
        }

        $ret_data = trim(curl_exec($ch));

        error_log("\n[".date('Y-m-d H:i:s').']REQUEST:'."\n".$this->send_data."\nURL:$url\n", 3, $this->log_file);
        error_log('['.date('Y-m-d H:i:s').']官方返回:'."\n".$ret_data."\n", 3, $this->log_file);
        /*
        error_log('['.date('Y-m-d H:i:s').']curl_errno:'."\n".curl_errno($ch)."\n\n", 3, $this->log_file);
        error_log('['.date('Y-m-d H:i:s').']curl_error:'."\n".curl_error($ch)."\n\n", 3, $this->log_file);
        error_log('['.date('Y-m-d H:i:s').']curl_getinfo:'."\n".var_export(curl_getinfo($ch), true)."\n\n", 3, $this->log_file);
*/
        curl_close($ch);

        $this->ret_data = $ret_data;
    }

    private function HexToString($s){
        $r = "";
        for($i=0; $i<strlen($s); $i+=2){
            $r .= chr(hexdec('0x'.$s{$i}.$s{$i+1}));
        }
        return $r;
    }
}
