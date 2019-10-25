<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;

/*
 * Call Avoda REST API
 */
class AvodaAPI {
    //POST
    const LOG_TABLE = 'remittance_api_log';
    const API_URL = 'https://service.avodapay.com/api/banktransfer/process';
    const API_AUTH_URL = 'https://service.avodapay.com/api/authenticate';
    //State Search
    const API_STATE_URL = 'https://service.avodapay.com/api/transaction/statesearch';
    const SECURE_KEY = '5zg8Pj4fDpmt';
    const TIMENOW_STR = 'Y-m-d H:i:s';
    /*
0 	Pending
1 	Authed [Successful Transaction]
2 	Captured
4 	Blocked
5 	Cancelled
6 	Voided.
7 	Returned (ACH only)
8 	Chargeback
9 	Represented
12 	Declined
13 	Refunded
16 	Sale [Successful Transaction]
     */

    private $debug, $logger;
    private $log_file = '/logs/avoda_api.log';
    private $username = 'xoomapi';
    private $password = '70BL09Rz61Dj';
    //'67d45fcdda385cc746a34619322d5bcc';
    private $siteid = '0ce4e258-c5c5-11e7-85ea-0242ac110002';
    //maybe updated by API
    private $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VySWQiOjQxMDYsInVzZXJOYW1lIjoieG9vbWFwaSJ9.OiHx7RtDLo4RPjwi1fgFgLjO1wq_hGXAVcfKyKYGwz4';
    public $orderid, $isprd;
    public $all_states = [
        'authed',
    'authBatched',
    'captured',
    'sale',
    'blocked',
    'voided',
    'returned',
    'chargeback',
    'represented',
    'declined',
    'refunded',
    'partialRefund',
    'refundDeclined',
    'refundReversed',
    'partialRefundReversed',
    'retrieved',
    'retrievalDisputed',
    'arbitration',
    'chargebackDisputed',
    'chargebackRepresented',
    'pending',
    'cancelled',
    'refundPending',
    'loaded',
    'payoutTransfer',
    'verificationSuccessful',
    'verificationFailed',
    'verificationCancelled',
    'paid',
    'adjustment',
        ];


    function __construct($is_prd = false, $debug = false) {
        if (!defined('ROOT'))
            define('ROOT',dirname(__DIR__));

        $this->debug = $debug;
        $this->isprd = $is_prd;
        $this->logger = new Logger('avoda_api');
        $this->log_file = ROOT.'/'.$this->log_file;
        $this->logger->pushHandler(new StreamHandler($this->log_file, Logger::DEBUG));
    }

    public function setOrderId($id) {
        $oid = str_pad($id, 8, '0', STR_PAD_LEFT);
        //return uniqid().$oid;
        //$this->orderId = uniqid().$oid;
        $tmp = preg_replace('/[^A-Za-z0-9]/','',uniqid().$oid);
        $this->orderid = substr($tmp, -36);
    }

    function authenticate() {
        $jsons = $this->post_request(self::API_AUTH_URL, null, ['Username: '.$this->username, 'Password: '.$this->password, 'Content-Type: application/json']);
        // 'Content-Type: application/x-www-urlencoded']);
            //, 'Accept: application/json']);
        if (isset($jsons["token"])) {
            $this->token = $jsons["token"];
            return $this->token;
        }

        return null;
    }

    public function sendRemittance($params) {
        $this->setOrderId($params['id']);
        $date = date(self::TIMENOW_STR);
        $amount = ($params['currency'] == 'CNY' ? $params['amount'] : $params['convert_amount']);
        if (isset($params['gross_amount_cny']))
            $amount = $params['gross_amount_cny'];

        //set uppercase for full English name
        if (isset($params['beneficiary_name'])) {
            $name=trim($params['beneficiary_name']);
            if (preg_match('/^([a-zA-Z\s\.]+)$/', $name))
                $name = strtoupper($name);
            $params['beneficiary_name'] = $name;
            $this->logger->debug('update beneficiary_name:', [$name]);
        }

        $data = [
            'attemptMode' => 2,
            'siteId' => $this->siteid,
            'transRef' => $this->orderid,
            'testTransaction' => ($this->isprd?0:1), //1 = True, 0 = False.
            'mode' => 'D',
            'bankDetails' => [
                'accountNumber'=> $params['account'],
                'accountName'=> $params['beneficiary_name'],
                'transferType' => 2,
            ],
            'customer' => [
                'email'=>'a@a.com',
                'ipAddress'=>'127.0.0.1',
                'details' => [
                    'firstName'=> $params['beneficiary_name'],
                    'lastName'=> '.',
                    'address1'=>'NA',
                    'city'=>'NA',
                    'state'=>'NA',
                    'country'=>'CN',
                    'postcode'=>'NA',
                ]
            ],
            'amount' => [
                'amount'=>$amount,  //For test transactions 10.01 = Decline Response
                'currency'=>'CNY'
            ]
        ]; //end data
        $this->logger->debug(__METHOD__, $data);

        $headers = ['Token: Bearer '.$this->token, 'Content-Type: application/json'];
        $request = json_encode($data);
        $response = null;

        $jsons = $this->post_request(self::API_URL, $request, $headers);
        $this->logger->debug(__METHOD__.' result', [$jsons]);

        $status = false;    //not null
        if (is_array($jsons))
            $response = json_encode($jsons);
        if (isset($jsons['transDetails']['state'])) {
            $return_code = $jsons['transDetails']['state'];
            $return_msg = $jsons['transDetails']['message'];
            $status = $this->isSuccess($return_code);
        }

        if (isset($params['batch_id']))
            $logs = ['batch_id'=> $params['batch_id'], 'log_id'=> $params['id']
                , 'request'=>$request, 'response'=>$response
                , 'status'=> $status
                , 'create_time'=> $date, 'complete_time'=> date(self::TIMENOW_STR)];
        else
            $logs = ['req_id'=> $params['id'] //,'bank_code'=> $params['gpay_code']
                , 'request'=>$request, 'response'=>$response
                , 'status'=> $status
                , 'create_time'=> $date, 'complete_time'=> date(self::TIMENOW_STR)];
        $logs['return_code'] = strtolower($return_code);    //Authed
        $logs['return_msg'] = $return_msg;

        $this->log2DB($logs);
        return ['result'=>$status, 'return'=> $response, 'order_id'=>$this->orderid, 'processing'=>($status===0)];
    }

    public function log2DB($arr) {
        if (!is_array($arr))
            return false;
        $arr['id'] = $this->orderid;
        $arr['processor'] = 'avoda';
        $arr['url'] = self::API_URL;

        DB::insert(self::LOG_TABLE, $arr);
    }

    public function query($orderid) {
        if (empty($orderid))
            return null;

        $requests = [
            'stateTypes' => $this->all_states,
            //string - format('Y-m-d H:i:s'), IN UTC
            'dateFrom' => date('Y-m-d H:i:s', strtotime('-365 days')),
            'dateTo' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'siteIds' => [$this->siteid],
            // fullMessage = message from bank
            'customFields' => ['firstName','lastName','adjustment','verifiedName','mobile','cupIdNumber','cupBankCode','cupAccountNumber','fullMessage'],
            'includeTest' => 1,
            'transRef' => $orderid,
        ];

        $headers = ['Token: Bearer '.$this->token, 'Content-Type: application/json'];
        $jsons = $this->post_request(self::API_STATE_URL, json_encode($requests), $headers);
        //$this->logger->debug(__METHOD__.' result', [$result]);
        if (isset($jsons['resultCount']) && $jsons['resultCount']>0) {
            /*
state 	State
transState 	Current state of the transaction
             * [{"transactionId":"79f2627e-cdbf-11e7-8c8e-0242ac110002","code":"D","type":"ach_debit","stateTime":"2017-11-20 06:53:18","state":"VOIDED","transState":"VOIDED","transRef":"5a127bd6a76cb1511160790","responseCode":"005","amount":"0.10","currency":"CNY"}]
             */
            $this->logger->debug('searchResults', $jsons['searchResults']);
            $return = $jsons['searchResults'][0];

            return $return;
        }

        return null;
    }

    public function isSuccess($state) {
        /*
sale
retrieved
paid
         */
        // AUTHED, AUTH_BATCHED = Processing
        // DECLINED, VOIDED = Failed

        $state = strtolower(trim($state));
        if (!empty($state) && in_array($state, ['sale','paid','captured']))
            return true;
        if (!empty($state) && in_array($state, ['declined','voided']))
            return false;
        //false for processing
        return 0;
    }
        /*
         * verify key in postback response
         */
    public function checkSecureKey($p) {
        if (!is_array($p))
            return false;

        $key = md5(self::SECURE_KEY . $p["TransId"] . $p["Amount"]);

        if (isset($p["Key"]) && $key == trim($p["Key"])) {
            return true;
        }

        return false;
    }

    /*
     * Return array of JSON
     */
    function post_request($url, $fields, $headers = null)
    {
        if (empty($url)) {
            return null;
        }

        if (is_array($fields))
            $output = http_build_query($fields);
        else
            $output = trim($fields);
        //open connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if (count($headers)>0) {
            if ($this->debug) {
                var_dump($headers);
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            //return header
            //curl_setopt($ch, CURLOPT_HEADER, true);
        }
        if (strpos($url, 'https://')!==false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($ch, CURLOPT_POST, true);    //count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $output);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $this->logger->debug(__METHOD__, [$url, $output, $headers]);
        //execute post
        $result = curl_exec($ch);
        if ($result === false) {
            $info = curl_getinfo($ch);
            $this->logger->error('error occur in curl exec.', [$info]);
        }
        curl_close($ch);
        $this->logger->debug(__METHOD__, [$result]);

        //return $result;
        if ($result) {
            return json_decode($result, true);
        }
        return null;
    }

}