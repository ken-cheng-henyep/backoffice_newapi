<?php
//namespace WeCollect;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;

use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;

class PayConnectorAPI
{
    const API_URL = 'https://service.wecollect.com/Transaction/rawpost.svc';
    const FX_RATE_API_URL = 'http://fxrate.wecollect.com/service/getrate?symbol=%s&merchantid=%s&datetime=%s';
    const DATABASE_ADDRESS ='localhost';
    const DATABASE_NAME = 'srd_dev';    //'payment_dev';
    const DATABASE_USER = 'mysqlu';
    const DATABASE_PASSWORD = '362gQtSA_QA7QroNS';
    const DATABASE_TABLE_BANKS = 'banks';
    const TRANSACTION_TABLE = 'transaction_log';
    const PROCESSOR_TRANSACTION_TABLE = 'processor_transaction_log';
    const GPAY_TRANSACTION_TABLE = 'gpay_transaction_log';
    const GHT_TRANSACTION_TABLE = 'ght_transaction_log';
    const PNR_TRANSACTION_TABLE = 'pnr_transaction_log';
    const MERCHANTS_TABLE = 'merchants';
    const PROCESSOR_AC_TABLE = 'processor_account';
    const PLATFORM_NAME = 'Pay Connector';
    const CUTOFF_TIME = '2016-01-09 00:00:00';
    const SKIP_GET_FX_RATE = 'HKD';
    const MIN_COLUMN_PER_ROW = 3;
    const ROUND_PRECISION = 2;

    public $debug = false;
    public $update_mode = false;
    public $update_fxrate_mode = false;
    public $database_conn;
    public $merchants;
    public $payconn_file, $gpay_file;
    public $validation_errors;
    private $logger;
    private $excel_keys = null;
    private $processor_account_data = null;
    private $merchant_data = null;
    private $processor_table_mappings = [
        'GHT'=>[
            'pc_transaction_id'=>'transaction_id',
            'payment_no'=>'payment_no',
            'status_text'=>'status',
            'settle_status_text'=>'settle_status',
            'currency'=>'currency',
            'amount'=>'amount',
            'fee'=>'fee',
            'convert_rate'=>'convert_rate',
            'refund_amount'=>'refund_amount',
            'bank_name'=>'bank_name',
            'bank_code'=>'bank_code',
            'bank_card_number'=>'card_number',
            'payer_name'=>'payer_name',
            'phone_no'=>'phone_no',
            'id_type'=>'id_type',
            'id_number'=>'id_number',
            'remark'=>'remark',
            'transaction_time'=>'transaction_time',
            'settle_time'=>'settle_time',
        ],
        'GPAY'=>[
            'pc_internal_id'=>'merchant_order_no',
            'processor_account_no'=>'merchant_no',
            'payment_no'=>'order_no',
            'status_text'=>'status',
            'currency'=>'currency',
            'amount'=>'amount',
            'fee'=>'fee',
            'bank_name'=>'bank_name',
            'bank_code'=>'bank_code',
            'remark'=>'results',
            'transaction_time'=>'transaction_time',
            'payment_time'=>'transaction_time',
            'settle_time'=>'transaction_time',
        ],
        'PNR'=>[
            'pc_transaction_id'=>'order_id',
            'processor_account_no'=>'merchant_acct_id',
            'amount'=>'order_amount',
            'currency'=>'order_currency',
            'transaction_time'=>'order_time',
            'payment_time'=>'deal_time',
            'status_text'=>'pay_result',
            'payment_no'=>'deal_id',
            'payment_code'=>'bank_deal_id',
            'bank_code'=>'bank_id',
        ],
    ];
    //Pay Connector db to xls mapping
    //
    private $pc_table_mappings = [
        'state_time'=> ['name'=>'State Time'],
        'transaction_state'=> ['name'=>'Status'],
        'transaction_time'=> ['name'=>'Transaction Time'],
        'response_code'=> ['name'=>'Response Code'],
        'transaction_id'=> ['name'=>'Transaction Id', 'tags'=>['TRANSACTION_ID']],
        'product'=> ['name'=>'Product', 'tags'=>['PRODUCT']],
        'ip_address'=> ['name'=>'IP Address', 'tags'=>['IP_ADDRESS']],
        'internal_id'=> ['name'=>'Internal Id', 'tags'=>['INTERNAL_ID']],
        'state_id'=> ['name'=>'State Id', 'tags'=>['STATE_ID']],
    ];
    private $gpay_table_mappings = [
        'transaction_time'=> ['name'=>'Order date'],
        'order_no'=> ['name'=>'Order No'],
        'merchant_no'=> ['name'=>'Merchant No'],
        'merchant_order_no'=> ['name'=>'Merchant order No'],
        'type'=> ['name'=>'trade type'],
        'status'=> ['name'=>'trade status'],
        'results'=> ['name'=>'trade results'],
        'bank_name'=> ['name'=>'bank name'],
        // bank_code
        'amount'=> ['name'=>'transaction amount'],
        'fee'=> ['name'=>'transaction fee'],
    ];
    private $ght_table_mappings = [
        'transaction_id'=> ['name'=>'订单号', 'tags'=>['Order Number']],
        'payment_no'=> ['name'=>'支付单号', 'tags'=>['Payment Number']],
        'currency'=> ['name'=>'交易币种', 'tags'=>['Transaction Currency']],
        'status'=> ['name'=>'支付状态', 'tags'=>['Payment Status']],
        'settle_status'=> ['name'=>'结算状态', 'tags'=>['Settlement Status']],
        'transaction_time'=> ['name'=>'订单时间', 'tags'=>['Time of Order']],
        'payment_time'=> ['name'=>'支付时间', 'tags'=>['Payment Time']],
        'settle_time'=> ['name'=>'结算时间', 'tags'=>['Settlement Time']],
        'remark'=> ['name'=>'备注', 'tags'=>['Remarks']],
        'bank_name'=> ['name'=>'银行名称', 'tags'=>['Bank Name']],
        'bank_code'=> ['name'=>'银行编码', 'tags'=>['Bank Code']], //change to internal code later
        'payment_code'=> ['name'=>'银行编码', 'tags'=>['Bank Code']],
        'amount'=> ['name'=>'交易金额', 'tags'=>['Transaction Amount']],
        'refund_amount'=> ['name'=>'退款金额', 'tags'=>['Refund Amount']],
        'fee'=> ['name'=>'手续费', 'tags'=>['Transaction Fee']],
        'convert_rate'=> ['name'=>'清算汇率', 'tags'=>['Settlement Exchange Rate']],
        'id_number'=> ['name'=>'用户证件号码', 'tags'=>['Buyer ID Number']],
        'card_number'=> ['name'=>'银行卡号', 'tags'=>['Bank Card Number']],
    ];

    private $pnr_table_mappings = [
        'order_id'=> ['name'=>'Order No'],
        'order_time'=> ['name'=>'Order Time'],
        'deal_id'=> ['name'=>'Deal No'],
        'deal_time'=> ['name'=>'Deal Time'],
        'bank_id'=> ['name'=>'Bank No'],
        'bank_deal_id'=> ['name'=>'Bank Deal Id'],
        'merchant_acct_id'=> ['name'=>'Merchant Account Id'],
        'order_amount'=> ['name'=>'Transaction amount'],
        'order_currency'=> ['name'=>'Transaction currency'],
        'pay_result'=> ['name'=>'Pay Result'],
        'pay_type'=> ['name'=>'Pay Type'],
        'terminal_id'=> ['name'=>'Terminal Id'],
    ];
    
    function __construct($debug = false)
    {
        $this->debug=$debug;

        $db_host = self::DATABASE_ADDRESS;
        $db_name = self::DATABASE_NAME;

        if (!empty($_SERVER['DB_HOST'])) {
            $db_host = $_SERVER['DB_HOST'];
        }

        if (!empty($_ENV['DB_HOST'])) {
            $db_host = $_ENV['DB_HOST'];
        }

        if (!empty($_SERVER['DB_NAME'])) {
            $db_name = $_SERVER['DB_NAME'];
        }

        if (!empty($_ENV['DB_NAME'])) {
            $db_name = $_ENV['DB_NAME'];
        }

        //print_r($_SERVER);
        //printf("Connecting to database server %s %s".PHP_EOL, $db_host , $db_name);

        $this->debug=$debug;
        $this->database_conn = new \mysqli($db_host, self::DATABASE_USER, self::DATABASE_PASSWORD, $db_name);
        /* change character set to utf8 */
        if (!$this->database_conn->set_charset("utf8")) {
            if ($this->debug) {
                printf("Error loading character set utf8: %s\n", $this->database_conn->error);
            }
        } else {
            //if ($this->debug)	printf("Current character set: %s\n", $this->database_conn->character_set_name());
        }
        \DB::$host = $db_host;
        \DB::$user = self::DATABASE_USER;
        \DB::$password = self::DATABASE_PASSWORD;
        \DB::$dbName = $db_name;
        //DB::$host = '123.111.10.23'; //defaults to localhost if omitted
        //DB::$port = '12345'; // defaults to 3306 if omitted
        \DB::$encoding = 'utf8'; // defaults to latin1 if omitted
        if ($this->debug) {
            \DB::debugMode();
        }

        $this->initProcessorAccount();
        $this->logger = new Logger('wc_logger');
        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/PayConnectorAPI.log', Logger::DEBUG));
    }
    
    public function api_request($reqs)
    {
        if (empty($reqs['action'])) {
            $reqs['action']='STATESEARCH';
        }
        return $this->post_request(self::API_URL, $reqs);
    }

    function post_request($url, $fields, $headers = null)
    {
        if (empty($url)) {
            return false;
        }
            
/*
		$output='';
		foreach($fields as $key=>$value) {
			if (is_array($value))
				$output .= sprintf("%s=%s&",$key,serialize($value)); 
			else
				$output .= sprintf("%s=%s&",$key,urlencode($value)); 
		}
		$output = trim($output,'&');
		*/
        $output = http_build_query($fields);
        
        if ($this->debug) {
            print("POST:$output\n");
        }
        //open connection
        $ch = curl_init();
        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);

        if (count($headers)>0) {
            if ($this->debug) {
                var_dump($headers);
            }
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if (strpos($url, 'https://')!==false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($ch, CURLOPT_POST, 1);    //count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $output);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        //execute post
        $result = curl_exec($ch);
        curl_close($ch);
        if ($this->debug) {
            print("RETURN:$result\n");
        }
        
        return $result;
    }

    function decode_search_result($r)
    {
        parse_str($r, $res);
        $count = intval($res['result_count']);
        $result = trim($res["search_results"]);
        $csv = null;
        if ($count>0 && !empty($result)) {
            $csv = array_map('str_getcsv', explode("\n", $result));
            array_walk($csv, function (&$a) use ($csv) {
                $a = array_combine($csv[0], $a);
            });
            array_shift($csv);
        }
        return $csv;
    }

    public function utc_to_hk_time($s, $format = 'Y-m-d H:i:s')
    {
        $diff='+8 hours';
        return date($format, strtotime($diff, strtotime($s)));
    }
    
    public function hk_to_utc_time($s, $format = 'Y-m-d H:i:s')
    {
        $diff='-8 hours';
        return date($format, strtotime($diff, strtotime($s)));
    }

    public function getFxRate($merchantid, $time, $fromCurrency, $toCurrency)
    {
        $toCurrency = strtoupper($toCurrency);
        if ($toCurrency == self::SKIP_GET_FX_RATE) {
            return false;
        }
    //http://fxrate.wecollect.com/service/getrate?symbol=USDCNY&merchantid=ab124bac-a6a4-11e4-8537-0211eb00a4cc&datetime=20160629141501
        $code = strtoupper(str_replace(' ', '', "$toCurrency$fromCurrency"));
        $rurl = sprintf(self::FX_RATE_API_URL, urlencode($code), $merchantid, date('YmdHis', strtotime($time)));
        if ($this->debug) {
            print("getFxRate: $rurl\n");
        }
        $jsons = json_decode(file_get_contents($rurl), true);
    
        if (is_array($jsons) && $jsons['status']===0) {
            //var_dump($jsons);
            return floatval($jsons['rate'][$code]);
        }
        return false;
    }
    
    public function getMerchants()
    {
        $return = \DB::query("SELECT * FROM %b WHERE enabled=1 AND (`api_username` is NOT NULL AND `api_password` is NOT NULL) ORDER BY name ;", self::MERCHANTS_TABLE);
        /*
		$result = $this->database_conn->query(sprintf("SELECT * FROM %s WHERE enabled=1 AND (`api_username` is NOT NULL AND `api_password` is NOT NULL) ;", self::MERCHANTS_TABLE));
		$return = array();
		if ($result) {
			while ($row = $result->fetch_assoc()) {
				$return[] = $row;
			}
		}		
		$result->close();
		*/
        return $return;
    }

    /**
     * Gets the database transactions.
     *
     * @param      <type>   $start                The start
     * @param      <type>   $end                  The end
     * @param      string   $mid                  The middle
     * @param      string   $tid                  The tid
     * @param      integer  $limit                The limit
     * @param      integer  $offset               The offset
     * @param      boolean  $return_counter_only  The return counter only
     *
     * @return     integer|array    Returning total number of result set if $return_counter_only is true. Otherwise, returning the database transactions in array format.
     */
    public function getDatabaseTransactions($start, $end, $mid = '', $tid = '', $limit = 0, $offset = 0, $return_counter_only = false)
    {
        $startdate = date('Y-m-d', strtotime($start));
        $enddate = date('Y-m-d', strtotime('+1 day', strtotime($end)));
        $ssql = $offsql = '';
        if (!empty($mid)) {
            $ssql.=" AND tx.`merchant_id`='$mid' ";
        }
        if (!empty($tid)) {
            $ssql.=" AND tx.TRANSACTION_ID='$tid' ";
        } else {
            $ssql.=" AND `STATE_TIME` between '$startdate' AND '$enddate' ";
        }

        if ($limit>0 && !$return_counter_only) {
            $offsql = sprintf("limit %d offset %d", $limit, $offset);
        }

        $sql = sprintf(
            "SELECT STATE_TIME as state_time , 
STATE as state , 
tx.TRANSACTION_TIME as transaction_time , 
TRANSACTION_STATE, trim(concat(`FIRST_NAME`,' ',`LAST_NAME`)) as customer,
email, 
m.name AS merchant, 
tx.merchant_id, 
tx.CURRENCY as currency, 
tx.AMOUNT as amount, 
ADJUSTMENT as adjustment, 
MERCHANT_REF as merchant_ref, 
tx.TRANSACTION_ID as transaction_id , 
tx.CONVERT_CURRENCY as convert_currency, 
tx.CONVERT_RATE as convert_rate , 
tx.CONVERT_AMOUNT as convert_amount, 
tx.product, 
tx.ip_address, 
b.name as bank, 
tx.bank_code as bank_code, 
m.settle_option AS fx_package, 
(case when tx.acquirer is not null then tx.acquirer when ptx.processor is not null then ptx.processor else 'N/A' end) as acquirer, 
tx.internal_id, 
(case when tx.acquirer_mid is not null then tx.acquirer_mid when ptx.processor_account_no is not null then ptx.processor_account_no end) as processor_account_no, 
ptx.fee as processor_fee, 
(case when m.settle_option = 2 then NULL else wecollect_fee_usd end) as wecollect_fee , 
wecollect_fee_cny , 
tx.settlement_rate,
tx.settlement_status,
tx.id_card_number as id_card_no, 
tx.card_number as bank_card_no, 
tx.mobile_number as mobile_no, 
tx.verified_name, 
tx.site_id, 
tx.site_name,
' ' as master_merchant,
ptx.payment_time as processor_state_time ,
search_state_time,
reconciled_state_time,
reconciliation_batch_id,
user_agent,

(CASE 
    WHEN ua_os_name IN ('Windows','Mac','Android') THEN ua_os_name  
    WHEN ua_device_name = 'tablet' AND ua_os_name = 'iOS' THEN 'iPad' 
    WHEN ua_device_name = 'smartphone' AND ua_os_name = 'iOS' THEN 'iPhone' 
    ELSE 'Others' 
END) as device , 
trim(concat(ua_device_brand,' ', ua_device_model,' ' , ua_os_name,' ' , ua_os_version)) as device_detail,
geo_country_name as country,
tx.ref_convert_rate AS ref_convert_rate,
tx.ref_cny_amount AS ref_cny_amount

FROM %s m, %s tx 
LEFT JOIN %s ptx on (ptx.pc_log_id = tx.id AND ptx.pc_log_id IS NOT NULL)
LEFT JOIN transaction_meta tm on (tx.TRANSACTION_ID = tm.transaction_id)
LEFT JOIN banks b on (tx.bank_code = b.code AND tx.bank_code IS NOT NULL AND tx.bank_code > 0) 
WHERE tx.`merchant_id` = m.id 
AND tx.`STATE` IN ('SALE','REFUNDED','PARTIAL_REFUND','REFUND_REVERSED','PARTIAL_REFUND_REVERSED')
%s
order by tx.`STATE_TIME` DESC, STATE ASC, merchant ASC %s;
		 ",
            self::MERCHANTS_TABLE,
            self::TRANSACTION_TABLE,
            self::PROCESSOR_TRANSACTION_TABLE,
            $ssql,
            $offsql
        );

        if ($this->debug) {
            print("getDatabaseTransactions:($start, $end) \n$sql\n");
        }
        $this->logger->debug("SQL: $sql");
        
        $result = $this->database_conn->query($sql);

        // If that is only for counting rows of result set, return number directly.
        if ($return_counter_only) {
            if ($result) {
                $this->logger->debug("Total Record Found : ".$result->num_rows);
                return $result->num_rows;
            }
            $this->logger->debug("Result set not valid.");
            return 0;
        }

        $return = array();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row = array_change_key_case($row);
                $roundp = ((isset($row['round_precision']) && $row['round_precision']>=0)?$row['round_precision']:self::ROUND_PRECISION);
                unset($row['round_precision']);
                $row['convert_amount'] = round($row['convert_amount'], $roundp);
                $row['wecollect_fee'] = round($row['wecollect_fee'], $roundp);
                $row['wecollect_fee_cny'] = round($row['wecollect_fee_cny'], $roundp);
                if (($ac_name = $this->getProcessorAccountName($row['acquirer'], $row['processor_account_no']))!=false) {
                    $row['acquirer'] = $ac_name;
                }
                $return[] = $row;
            }
            $result->close();
        }

        return $return;
    }

    public function getDatabaseTransactionSummary($start, $end, $mid = '', $limit = 0, $offset = 0)
    {
        $startdate = date('Y-m-d', strtotime($start));
        $enddate = date('Y-m-d', strtotime('+1 day', strtotime($end)));
        $ssql = $offsql = '';
        if (!empty($mid)) {
            $ssql.=" AND tx.`merchant_id`='$mid' ";
        }
        if (!empty($tid)) {
            $ssql.=" AND tx.TRANSACTION_ID='$tid' ";
        } else {
            $ssql.=" AND `STATE_TIME` between '$startdate' AND '$enddate' ";
        }

        if ($limit>0) {
            $offsql = sprintf("limit %d offset %d", $limit, $offset);
        }

        $sql = sprintf(
            "select merchant, merchant_id, convert_currency as currency, count(distinct transaction_id ) as count, sum(amount) as amount_ttl, sum(round(convert_amount,round_precision)) as convert_ttl, round(AVG(convert_rate),4) as avg_rate, sum(processor_fee) as processor_fee_ttl, sum(round(wecollect_fee, round_precision)) as wecollect_fee_ttl    
 from ( 
          SELECT STATE_TIME, STATE, tx.TRANSACTION_TIME, TRANSACTION_STATE, trim(concat(`FIRST_NAME`,' ',`LAST_NAME`)) as customer,email, 
		 m.name AS merchant, tx.merchant_id, tx.CURRENCY, tx.AMOUNT, ADJUSTMENT, MERCHANT_REF, tx.TRANSACTION_ID, tx.CONVERT_CURRENCY, tx.CONVERT_RATE, tx.CONVERT_AMOUNT, 
		 tx.product, tx.ip_address, coalesce(ght.BANK_NAME, g.BANK_NAME) as bank, coalesce(ght.BANK_CODE, g.BANK_CODE) as bank_code, m.settle_option AS fx_package, m.round_precision,
		       case when g.transaction_time is not null then 'GPAY' when ght.transaction_id is not null then 'GHT' else 'N/A' end as acquirer
		       , tx.internal_id, coalesce(ght.merchant_no, g.merchant_no) as processor_account_no, coalesce(ght.fee, g.fee) as processor_fee, wecollect_fee_usd as wecollect_fee    
			FROM %s m, %s tx 
			LEFT JOIN %s g on (tx.internal_id= g.merchant_order_no AND tx.internal_id is not null)
			LEFT JOIN %s ght on (tx.transaction_id= ght.transaction_id)
WHERE tx.`merchant_id` = m.id 
%s ) as big 
group by merchant, merchant_id, convert_currency
order by merchant, convert_currency 
 %s;
		 ",
            self::MERCHANTS_TABLE,
            self::TRANSACTION_TABLE,
            self::GPAY_TRANSACTION_TABLE,
            self::GHT_TRANSACTION_TABLE,
            $ssql,
            $offsql
        );

        if ($this->debug) {
            print("getDatabaseTransactionSummary:($start, $end) \n$sql\n");
        }
        $this->logger->debug("SQL: $sql");

        $result = DB::query($sql);

        if ($result) {
        }
        return $result;
    }
    
    public function getMerchantTransactions($id, $user, $password, $startdate, $enddate, $settle_currency)
    {
        if (empty($id) || empty($user) || empty($password)) {
            return false;
        }
            
        //$start = date('Y-m-d 00:00:00', strtotime($startdate));
        //$end = date('Y-m-d 00:00:00', strtotime('+1 day',strtotime($enddate)) );
        $start = $this->hk_to_utc_time($startdate);
        $end = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($enddate)));
        $end = $this->hk_to_utc_time($end);
        
        $fields = array(
            'username'=> $user,
            'password'=> $password,
//	'action'=>'STATESEARCH',
            'date_from'=> $start,
            'date_to'=> $end,
            'state_types'=> array('SALE','REFUNDED','PARTIAL_REFUND'),
            'custom_fields'=> ['SITE_ID','REBILL_ID','FIRST_NAME','LAST_NAME','TRANS_TIME','EMAIL','ADJUSTMENT', 'MOBILE', 'CUP_ID_NUMBER',
                'CUP_BANK_CODE', 'CUP_ACCOUNT_NUMBER', 'SITE_NAME', 'VERIFIED_NAME'],
        );
        
        $response = $this->api_request($fields);
        if (empty($response)) {
            return false;
        }
        $datas = $this->decode_search_result($response);
        //var_dump($datas);
        if (is_array($datas)) {
            foreach ($datas as $k => $data) {
                $localtime = $datas[$k]['STATE_TIME_LOCAL'] = $this->utc_to_hk_time($data['STATE_TIME']);
                $datas[$k]['merchant_id'] = $id;
                $datas[$k]['CONVERT_CURRENCY'] = $settle_currency;
                /*
                $fxrate = $datas[$k]['CONVERT_RATE'] = $this->getFxRate($id, $localtime, $data['CURRENCY'], $settle_currency);
                if (isset($data["AMOUNT"])) {
                $amt = floatval($data["AMOUNT"]);
                $datas[$k]['CONVERT_AMOUNT'] = $amt*$fxrate;
                $datas[$k]['CONVERT_CURRENCY'] = $settle_currency;
                }
                 */
                //var_dump($datas[$k]);
                //if ($this->debug)		break;
            }
        }
        
        return $datas;
    }
    
    public function isTransactionExist($id, $time)
    {
        if (empty($id)||empty($time)) {
            return false;
        }
            
        $result = $this->database_conn->query(sprintf("SELECT * FROM %s WHERE transaction_id='%s' AND state_time='%s' ;", self::TRANSACTION_TABLE, $id, $time));
        $cnt = $result->num_rows;
        $result->close();
        if ($this->debug) {
            print("isTransactionExist($id, $time): $cnt\n");
        }
            
        return ($cnt>0);
    }
    
    public function insertTransactions($tx)
    {
        if (!is_array($tx)) {
            return false;
        }
        
        $tx = array_change_key_case($tx);
        if (isset($tx['trans_time'])) {
            $tx['trans_time'] = $this->utc_to_hk_time($tx['trans_time']);
        }
        //check if exists
        if ($this->isTransactionExist($tx["transaction_id"], $tx['state_time_local'])) {
            if ($this->update_mode) {
                $this->updateTransactions($tx);
            }
                
            return false;
        }
            
        //Get FX rate if not exists
        $id = $tx['merchant_id'];
        if (!isset($tx["convert_rate"]) && isset($tx['state_time_local'])) {
                //$localtime = $tx['state_time_local'];
                $localtime = $tx['trans_time'];
                $fxrate = $tx['convert_rate'] = $this->getFxRate($id, $localtime, $tx['currency'], $tx["convert_currency"]);
            if (isset($tx["amount"])) {
                $amt = floatval($tx["amount"]);
                $tx['convert_amount'] = ($fxrate>0?($amt/$fxrate):0);
                //$datas[$k]['CONVERT_CURRENCY'] = $settle_currency;
            }
        }
        
        $platform = self::PLATFORM_NAME;
        $query = sprintf("insert into `%s`(`platform`, `merchant_id`, `state`, `transaction_state`, `transaction_id`, `transaction_code`, `transaction_type`, `merchant_ref`, `response_code`, 
		`currency`, `amount`, `convert_currency`, `convert_amount`, `convert_rate`, `state_time`, `site_id`, `rebill_id`, `first_name`,`last_name`,`transaction_time`,`email`,`adjustment`) 
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", self::TRANSACTION_TABLE);
        $stmt = $this->database_conn->prepare($query);
        //var_dump($stmt);
        
        $stmt->bind_param(
            'ssssssssssdsddsssssssd',  //d=decimal
            $platform,
            $tx['merchant_id'],
            $tx["state"],
            $tx["trans_state"],
            $tx["transaction_id"],
            $tx["code"],
            $tx["type"],
            $tx["trans_ref"],
            $tx["response_code"],
            $tx["currency"],
            $tx["amount"],
            $tx["convert_currency"],
            $tx["convert_amount"],
            $tx["convert_rate"],
            $tx['state_time_local'],
            $tx['site_id'],
            $tx['rebill_id'],
            $tx['first_name'],
            $tx['last_name'],
            $tx['trans_time'],
            $tx['email'],
            $tx['adjustment']
        );
        $stmt->execute();
                
        if (mysqli_connect_errno()) {
            printf("Connect failed: %s\n", mysqli_connect_error());
        }
        if ($this->database_conn->connect_errno) {
            print $this->database_conn->connect_errno;
        }
        if ($this->database_conn->error) {
            printf("Errormessage: %s\n", $this->database_conn->error);
        }
        $stmt->close();
        return ;
            //var_dump($query);

        /*
		$query = $this->database_conn->stmt_init();
		if ($query->prepare(sprintf("insert into `%s`(`platform`, `state`, `transaction_state`, `transaction_id`, `transaction_code`, `transaction_type`, `merchant_ref`, `response_code`, 
		`currency`, `amount`, `convert_currency`, `convert_amount`, `convert_rate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", self::TRANSACTION_TABLE)) ) {
			$platform = 'Pay Connector';
		*/
        /*$this->database_conn->mysqli_stmt_bind_param($query, 'sssssssssdsdd', $platform, $tx["STATE"], $tx["TRANS_STATE"], $tx["TRANSACTION_ID"], $tx["CODE"], $tx["TYPE"], $tx["TRANS_REF"], $tx["RESPONSE_CODE"]
		, $tx["CURRENCY"], $tx["AMOUNT"], $tx["CONVERT_CURRENCY"], $tx["CONVERT_AMOUNT"], $tx["CONVERT_RATE"]);
		 $result = mysqli_query($this->database_conn, $query) ;	//or die(mysqli_error());
		*/
        //}
    }
    
    public function updateTransactions($tx)
    {
        if (!is_array($tx)) {
            return false;
        }
        if (!isset($tx['trans_time'])) {
            return false;
        }
        // update row
        if ($this->update_fxrate_mode) {
            $fxrate = $tx['convert_rate'] = $this->getFxRate($tx['merchant_id'], $tx['trans_time'], $tx['currency'], $tx["convert_currency"]);
            if (isset($tx["amount"])) {
                $amt = floatval($tx["amount"]);
                $tx['convert_amount'] = ($fxrate>0?($amt/$fxrate):0);
                    //$datas[$k]['CONVERT_CURRENCY'] = $settle_currency;
            }
            $usql = sprintf("UPDATE %s set `site_id`='%s', `rebill_id`='%s', `first_name`='%s',`last_name`='%s',`transaction_time`='%s',`email`='%s',`adjustment`=%f, `merchant_ref`='%s', convert_rate=%f, convert_amount=%f, `update_time`='%s' 
		 WHERE `state_time`='%s' AND `transaction_id`='%s' ;", self::TRANSACTION_TABLE, $tx['site_id'], $tx['rebill_id'], $this->database_conn->real_escape_string($tx['first_name']), $this->database_conn->real_escape_string($tx['last_name']), $tx['trans_time'], $tx['email'], $tx['adjustment'], $this->database_conn->real_escape_string($tx["trans_ref"]), $tx['convert_rate'], $tx["convert_amount"], date('Y-m-d H:i:s'), $tx['state_time_local'], $tx['transaction_id']);
        } else {
            $usql = sprintf("UPDATE %s set `site_id`='%s', `rebill_id`='%s', `first_name`='%s',`last_name`='%s',`transaction_time`='%s',`email`='%s',`adjustment`=%f, `merchant_ref`='%s', `update_time`='%s' 
		 WHERE `state_time`='%s' AND `transaction_id`='%s' ;", self::TRANSACTION_TABLE, $tx['site_id'], $tx['rebill_id'], $this->database_conn->real_escape_string($tx['first_name']), $this->database_conn->real_escape_string($tx['last_name']), $tx['trans_time'], $tx['email'], $tx['adjustment'], $this->database_conn->real_escape_string($tx["trans_ref"]), date('Y-m-d H:i:s'), $tx['state_time_local'], $tx['transaction_id']);
        }
        if ($this->debug) {
            print("updateTransactions: $usql\n");
        }
        $this->database_conn->query($usql);
    }

    public function getTransactionId($id)
    {
        if (empty($id)) {
            return false;
        }
        $checks = \DB::queryFirstRow("SELECT TRANSACTION_ID as id FROM %b WHERE replace(TRANSACTION_ID,'-','')=replace(%s,'-','') ;", self::TRANSACTION_TABLE, $id);
        if (isset($checks['id'])) {
            //$this->logger->debug('getTransactionId:', $checks);
            return $checks['id'];
        }
        return false;
    }

    //IP, Product , etc.
    public function updateTransactionDetails($tx)
    {
        if (!is_array($tx)) {
            return false;
        }
        if (!isset($tx['transaction_id'])) {
            return false;
        }
        // update row
        $updates = ['product', 'ip_address', 'internal_id', 'transaction_id'];
        foreach ($tx as $k => $v) {
            if (!in_array($k, $updates)) {
                unset($tx[$k]);
            }
        }
        $tx['update_time'] = date('Y-m-d H:i:s');
        $this->logger->debug('updateTransactionDetails', $tx);

        \DB::update(self::TRANSACTION_TABLE, $tx, "transaction_id=%s", $tx['transaction_id']);
        return \DB::affectedRows();
    }

    public function updateGPayRecord($tx, $forceUpdate = false)
    {
        /*
         * SQL: SELECT t.*,g.bank_name FROM `transaction_log` t left join gpay_transaction_log g on (t.internal_id= g.merchant_order_no)
where t.internal_id is not null
         */
        //merchant_order_no = internal_id
        if (!is_array($tx)) {
            return false;
        }
        if (empty($tx['merchant_order_no'])) {
            return false;
        }
        // update row
        $updates = ['order_no', 'merchant_no', 'merchant_order_no', 'type', 'status', 'results', 'bank_name', 'bank_code', 'amount', 'fee', 'transaction_time'];
        foreach ($tx as $k => $v) {
            if (!in_array($k, $updates)) {
                unset($tx[$k]);
            }
        }
        $tx['update_time'] = date('Y-m-d H:i:s');
        $this->logger->debug('updateGPayRecord', $tx);

        //check exist
        $checks = \DB::queryFirstRow("SELECT * FROM %b WHERE 1=1 AND merchant_order_no=%s ;", self::GPAY_TRANSACTION_TABLE, $tx['merchant_order_no']);
        if ($checks != null) {
            $this->logger->debug('Transaction exists!', $checks);
            //check if latest record
            if ($forceUpdate || strtotime($checks['transaction_time']) < strtotime($tx['transaction_time'])) {
                \DB::update(self::GPAY_TRANSACTION_TABLE, $tx, "1=1 AND merchant_order_no=%s", $tx['merchant_order_no']);
            }
        } else {
            \DB::insert(self::GPAY_TRANSACTION_TABLE, $tx);
        }
        $affected_rows =  \DB::affectedRows();

        // Clone into processor_transaction_log for binding tx.
        $d = [];
        foreach ($this->processor_table_mappings['GPAY'] as $dst_field => $src_field) {
            $d[$dst_field] = isset($tx[$src_field]) ? $tx[$src_field] : '';
        }

        $d['processor'] = 'GPAY';

        if ($checks != null) {
            if ($forceUpdate || strtotime($checks['transaction_time']) < strtotime($tx['transaction_time'])) {
                unset($d['pc_internal_id']);

                \DB::update(self::PROCESSOR_TRANSACTION_TABLE, $d, "pc_internal_id=%s ", $tx['merchant_order_no']);
            }
        } else {
            // Insert into processor_transaction_log
            \DB::insert(self::PROCESSOR_TRANSACTION_TABLE, $d);
        }

        if (!empty($d['bank_code'])) {
            // Update bank code into main transaction table
            \DB::update(self::TRANSACTION_TABLE, ['bank_code'=> $d['bank_code'] ], "(bank_code IS NULL OR bank_code = '') AND internal_id=%s ", $tx['merchant_order_no']);
        }

        return $affected_rows;
    }

    public function updateGhtRecord($tx, $forceUpdate = false)
    {
        if (!is_array($tx)) {
            return false;
        }
        if (empty($tx['transaction_id'])) {
            return false;
        }

        if (!($txid = $this->getTransactionId($tx['transaction_id']))) {
            return false;
        }

        $tx['transaction_id'] = $txid;
        // update row, product?
        if (!empty($tx['card_number'])) {
            \DB::update(self::TRANSACTION_TABLE, [
                'card_number'=>maskCardNumber($tx['card_number'])
            ], "transaction_id=%s", $tx['transaction_id']);
        }
        $updates = ['transaction_id','payment_no', 'currency', 'status', 'settle_status', 'convert_rate','refund_amount','id_number','remark','card_number','bank_name','bank_code', 'payment_code', 'amount', 'fee', 'transaction_time','payment_time','settle_time'];
        foreach ($tx as $k => $v) {
            if (!in_array($k, $updates)) {
                unset($tx[$k]);
            }
        }
        $tx['update_time'] = date('Y-m-d H:i:s');
        if (!empty($tx['bank_code'])) {
            $bcode = $this->getBankCode($tx['bank_code']);
            if (empty($bcode)) {
                $bcode = $this->getGatewayBankCode($tx['bank_code']);
            }
            $tx['bank_code'] = $bcode;

            // Update Bank Code in main transaction table.
            if (!empty($bcode)) {
                \DB::update(self::TRANSACTION_TABLE, [
                    'bank_code'=>$bcode
                ], "transaction_id=%s", $tx['transaction_id']);
            }
        }
        $this->logger->debug('updateGhtRecord', $tx);

        //check exist
        $checks = \DB::queryFirstRow("SELECT * FROM %b WHERE transaction_id=%s AND payment_no=%s ;", self::GHT_TRANSACTION_TABLE, $tx['transaction_id'], $tx['payment_no']);
        if ($checks != null) {
            $this->logger->debug('Transaction exists!', $checks);
            //check if latest record
            if ($forceUpdate || strtotime($checks['transaction_time']) < strtotime($tx['transaction_time'])) {
                \DB::update(self::GHT_TRANSACTION_TABLE, $tx, "transaction_id=%s AND payment_no=%s", $tx['transaction_id'], $tx['payment_no']);
            }
        } else {
            \DB::insert(self::GHT_TRANSACTION_TABLE, $tx);
        }
        $affected_rows = \DB::affectedRows();


        // Clone into processor_transaction_log for binding tx.
        $d = [];
        foreach ($this->processor_table_mappings['GHT'] as $dst_field => $src_field) {
            $d[$dst_field] = isset($tx[$src_field]) ? $tx[$src_field] : '';
        }

        $d['processor'] = 'GHT';

        if ($checks != null) {
            if ($forceUpdate || strtotime($checks['transaction_time']) < strtotime($tx['transaction_time'])) {
                unset($d['pc_transaction_id'], $d['payment_no']);

                \DB::update(self::PROCESSOR_TRANSACTION_TABLE, $d, "pc_transaction_id=%s AND payment_no=%s", $tx['transaction_id'], $tx['payment_no']);
            }
        } else {
            // Insert into processor_transaction_log
            \DB::insert(self::PROCESSOR_TRANSACTION_TABLE, $d);
        }

        return $affected_rows;
    }

    /**
     * Update PNR Transaction Record
     *
     * @param      <type>   $tx           The transaction record
     * @param      boolean  $forceUpdate  The force update
     *
     * @return     boolean  ( description_of_the_return_value )
     */
    public function updatePnrRecord($tx, $forceUpdate = false)
    {
        if (!is_array($tx)) {
            return false;
        }
        if (empty($tx['order_id'])) {
            return false;
        }

        if (!($txid = $this->getTransactionId($tx['order_id']))) {
            return false;
        }

        $tx['order_id'] = $txid;

        $fields = ['transaction_id', 'merchant_acct_id', 'order_amount', 'order_currency', 'order_time', 'deal_time', 'pay_result','deal_id','bank_deal_id','bank_id','pay_type','terminal_id'];

        $_tx = [];
        foreach ($fields as $k) {
            if (isset($tx[ $k ])) {
                $_tx[$k] = $tx[$k];
            }
        }

        $_tx['update_time'] = date('Y-m-d H:i:s');

        if (!empty($tx['bank_id']) && !is_numeric($tx['bank_id'])) {
            //bank_code is integer
            $bcode = $this->getGatewayBankCode($tx['bank_id'], 'PNR');
            $_tx['bank_code'] = $tx['bank_code'] = $bcode;
        }
        $this->logger->debug('updatePnrRecord', $_tx);


        $checks = \DB::queryFirstRow("SELECT * FROM %b WHERE order_id=%s ", self::PNR_TRANSACTION_TABLE, $tx['order_id']);
        if ($checks != null) {
            $this->logger->debug('Transaction exists!', $checks);
            //check if latest record
            if ($forceUpdate) {
                \DB::update(self::PNR_TRANSACTION_TABLE, $_tx, "order_id=%s", $tx['order_id']);
            }
        } else {
            $_tx['create_time'] = $_tx['update_time'];
            \DB::insert(self::PNR_TRANSACTION_TABLE, $_tx);
        }
        $affected_rows = \DB::affectedRows();


        // Clone into processor_transaction_log for binding tx.
        $d = [];
        foreach ($this->processor_table_mappings['PNR'] as $dst_field => $src_field) {
            $d[$dst_field] = isset($tx[$src_field]) ? $tx[$src_field] : null;
        }

        $d['processor'] = 'PNR';


        if ($checks != null) {
            if ($forceUpdate) {
                unset($d['pc_transaction_id']);

                \DB::update(self::PROCESSOR_TRANSACTION_TABLE, $d, "pc_transaction_id=%s", $tx['transaction_id']);
            }
        } else {
            // Insert into processor_transaction_log
            \DB::insert(self::PROCESSOR_TRANSACTION_TABLE, $d);
        }

        return $affected_rows;
    }

    public function getGPayLastTransactionTime()
    {
        $checks = \DB::queryFirstRow("SELECT max(transaction_time) as time FROM %b WHERE fee>0 ;", self::GPAY_TRANSACTION_TABLE);
        return $checks['time'];
    }

    public function getPayConnLastTransactionTime()
    {
        $checks = \DB::queryFirstRow("SELECT max(transaction_time) as time FROM %b WHERE internal_id is NOT null ;", self::TRANSACTION_TABLE);
        return $checks['time'];
    }

    public function updateDatabaseTransactionRate($tx)
    {
        if (!is_array($tx)) {
            return false;
        }
         // update row
        $tx = array_change_key_case($tx);
        if (empty($tx['transaction_id'])) {
            return false;
        }

        //if ($this->update_fxrate_mode)
        $fxrate = $tx['convert_rate'] = $this->getFxRate($tx['merchant_id'], $tx['transaction_time'], $tx['currency'], $tx["convert_currency"]);
        if ($fxrate==0) {
            return false;
        }

        if (isset($tx["amount"])) {
            $amt = floatval($tx["amount"]);
            $tx['convert_amount'] = ($fxrate>0?($amt/$fxrate):0);
            //$datas[$k]['CONVERT_CURRENCY'] = $settle_currency;
        }

        \DB::update(self::TRANSACTION_TABLE, array(
            'convert_rate' => $fxrate,
            'convert_amount' => $tx['convert_amount'],
            'update_time' => date('Y-m-d H:i:s'),
        ), "transaction_id=%s AND state_time=%s", $tx['transaction_id'], $tx['state_time']);
/*
            $usql = sprintf("UPDATE %s set `site_id`='%s', `rebill_id`='%s', `first_name`='%s',`last_name`='%s',`transaction_time`='%s',`email`='%s',`adjustment`=%f, `merchant_ref`='%s', convert_rate=%f, convert_amount=%f, `update_time`='%s' 
		 WHERE `state_time`='%s' AND `transaction_id`='%s' ;", self::TRANSACTION_TABLE, $tx['site_id'], $tx['rebill_id'],  $this->database_conn->real_escape_string($tx['first_name']),  $this->database_conn->real_escape_string($tx['last_name']), $tx['trans_time'], $tx['email'], $tx['adjustment']
                , $this->database_conn->real_escape_string($tx["trans_ref"]), $tx['convert_rate'], $tx["convert_amount"], date('Y-m-d H:i:s'), $tx['state_time_local'], $tx['transaction_id']);

        if ($this->debug)
            print("updateDatabaseTransactionRate: $usql\n");
        $this->database_conn->query($usql);
*/
    }

    public function updateMissingRate()
    {
        $currency = 'USD';
        //$currency = 'HKD';
        $results = \DB::query("SELECT * FROM %b WHERE CONVERT_CURRENCY=%s AND (CONVERT_RATE is null OR CONVERT_RATE=0) AND TRANSACTION_TIME>%t ORDER BY `STATE_TIME` DESC;", self::TRANSACTION_TABLE, $currency, self::CUTOFF_TIME);

        if (!is_array($results) || count($results)==0) {
            print("checkMissingRate: No record\n");
            return false;
        }

        foreach ($results as $tx) {
            if ($this->debug) {
                var_dump($tx);
            }
            $tx = array_change_key_case($tx);

            $fxrate = $this->getFxRate($tx['merchant_id'], $tx['transaction_time'], $tx['currency'], $tx["convert_currency"]);
            if ($fxrate==0) {
                continue;
            }

            $amt = floatval($tx["amount"])/$fxrate;
            // update row
            \DB::update(self::TRANSACTION_TABLE, array(
                'convert_rate' => $fxrate,
                'convert_amount' => $amt,
                'update_time' => date('Y-m-d H:i:s'),
            ), "transaction_id=%s AND id=%i", $tx['transaction_id'], $tx['id']);
        }
        /*
		$sql = sprintf("SELECT * FROM %s WHERE CONVERT_CURRENCY='%s' AND (CONVERT_RATE is null OR CONVERT_RATE=0) AND TRANSACTION_TIME>'%s' ORDER BY `STATE_TIME` DESC;", self::TRANSACTION_TABLE, $currency, self::CUTOFF_TIME);
		
		$result = $this->database_conn->query($sql);

		if ($result->num_rows) {
			while ($tx = $result->fetch_assoc()) {
				$tx = array_change_key_case($tx);
				//var_dump($tx);
				//$fxrate = $this->getFxRate($tx['merchant_id'], $tx['state_time'], $tx['currency'], $tx["convert_currency"]);
				$fxrate = $this->getFxRate($tx['merchant_id'], $tx['transaction_time'], $tx['currency'], $tx["convert_currency"]);
				if ($fxrate>0) {
					$amt = floatval($tx["amount"])/$fxrate;
					// update row
					$usql = sprintf("UPDATE %s set CONVERT_RATE=%f , CONVERT_AMOUNT=%f WHERE ID=%d AND TRANSACTION_ID='%s'; ", self::TRANSACTION_TABLE, $fxrate, $amt, $tx['id'], $tx['transaction_id']);
					if ($this->debug)
						print("checkMissingRate: $usql\n");
					$this->database_conn->query($usql);
				}
			}
		} else {
			print("checkMissingRate: No record\n");
		}
		$result->close();
		*/
    }
    
    public function saveToExcel($data, $filename, $ext = '.xlsx')
    {
        if (!is_array($data)) {
            return false;
        }
            
        $excel = new \PHPExcel();
        $excel->setActiveSheetIndex(0);

        $meta = array_keys(array_change_key_case($data[0]));
        PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_WcColumnValueBinder($meta));

        $excel->getActiveSheet()->fromArray($meta, null, 'A1');
        $excel->getActiveSheet()->fromArray($data, null, 'A2');
        //column auto width
        $lastCol = \PHPExcel_Cell::stringFromColumnIndex(count($meta)-1);
        foreach (range('A', $lastCol) as $colidx) {
            $excel->getActiveSheet()->getColumnDimension($colidx)->setAutoSize(true);
        }
        
    /*
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="your_name.xls"');
header('Cache-Control: max-age=0');
*/
        //$writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $basef1 = basename($filename);
        $basef2 = str_replace(['/',' '], '-', $basef1);
        //$this->logger->debug("saveToExcel: str_replace($basef1, $basef2, $filename)");

        $filename = str_replace($basef1, $basef2, $filename);
        $filename.= $ext;
        $writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save($filename);
        return $filename;
    }


    public function saveToExcel2($data, $filename, $ext = '.xlsx')
    {
        if (!is_array($data)) {
            return false;
        }

        $basef1 = basename($filename);
        $basef2 = str_replace(['/',' '], '-', $basef1);
        //$this->logger->debug("saveToExcel: str_replace($basef1, $basef2, $filename)");

        $filename = str_replace($basef1, $basef2, $filename);

        //$filename  = fromArrayToExcelFile(['Sheet1'=>$data], $filename, $ext);
        //$filename  = fromArrayToSpoutExcel(['fxrate'=>$data, 'test'=>[0=>['amount'=>1999.9]] ], $filename, $ext);
        //$filename  = fromArrayToSpoutExcel(['fxrate'=>$data, 'test'=>[0=>['amount'=>1999.9]] ], $filename, $ext);
        if ($ext == '.csv') {
            $filename = fromArrayToSpoutCsv($data, $filename, '.csv', $this->logger);
        } else {
            $filename = fromArrayToSpoutExcel(['Data'=>$data,], $filename, $ext, $this->logger);
        }

        return $filename;
    }

    //return json in array
    public function handleExcelFile($f, $vendor = 'payconn')
    {
        if (!is_readable($f)) {
            return ['code'=>-2, 'msg'=>'Excel file unreadable','data'=>null];
        }
        if (strrchr($f, '.')=='.csv') {
            return $this->handleCsvFile($f, $vendor);
        }
        //throw new Exception("File not readable. ($f)");
        try {
            $filetype = PHPExcel_IOFactory::identify($f);
            //$objReader = PHPExcel_IOFactory::createReader(XLS_FILE_TYPE);
            $objReader = PHPExcel_IOFactory::createReader($filetype);
            $objReader->setReadDataOnly(true);
            $excel = $objReader->load($f);
            switch ($vendor) {
                case 'payconn':
                    $this->payconn_file = $f;
                    break;
                case 'gpay':
                    $this->gpay_file = $f;
                    break;
            }
        } catch (Exception $e) {
            return ['code'=>-2, 'msg'=>'Excel file invalid','data'=>null];
        }

        //$this->logger->debug("getSheetCount:".$excel->getSheetCount());
        if ($this->debug) {
            printf("%d worksheet\n", $excel->getSheetCount());
        }
        $sheet = $excel->getActiveSheet();
        //update database
        switch ($vendor) {
            case 'payconn':
                $data = $sheet->toArray(null, false, false, false);
                $total = $this->handlePayConnData($data);
                break;
            case 'gpay':
                $total = $this->handleGPaySheet($sheet);
                break;
            case 'ght':
                $data = $sheet->toArray(null, false, false, false);
                $total = $this->handleGhtData($data);
                break;
        }

        unset($objReader);
        $this->logger->debug("handleExcelFile($f):".$total);

        if ($total>0) {
            return ['code' => 0, 'msg' => "$total records updated.", 'data' => "$total records"];
        } else {
            return ['code' => -4, 'msg' => 'No record on Excel', 'data' => null];
        }
    }

    public function handleCsvFile($f, $vendor = 'payconn')
    {
        $this->logger->debug("handleCsvFile($f)");

        if (!is_readable($f)) {
            return ['code'=>-2, 'msg'=>'CSV file unreadable','data'=>null];
        }
        //throw new Exception("File not readable. ($f)");
        try {
            //$reader = ReaderFactory::create(Type::XLSX); // for XLSX files
            $reader = ReaderFactory::create(Type::CSV); // for CSV files
            $reader->open($f);

            $data= array();
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    //var_dump($row);
                    $data[] = $row;
                    //$this->logger->debug("handleCsvFile($f) row:",[$row]);
                }
            }
            $reader->close();

            // save xlsx file, serveStaticFile
            $xlsfile = sprintf('tmp/%s.xlsx', basename($f));
            $xlspath = sprintf('%s/data/%s', ROOT, $xlsfile);

            if (count($data)>0) {
                $writer = WriterFactory::create(Type::XLSX);
                $writer->openToFile($xlspath)
                    //->setTempFolder($customTempFolderPath)
                    //->setShouldUseInlineStrings(true)
                    //->addRow($headerRow)
                    ->addRows($data)
                    ->close();
                $this->logger->debug("handleCsvFile($f) XLSX:", [$xlspath]);
            }

            switch ($vendor) {
                case 'payconn':
                    $this->payconn_file = $f;
                    break;
                case 'gpay':
                    $this->gpay_file = $f;
                    break;
            }
        } catch (Exception $e) {
            return ['code'=>-2, 'msg'=>'Excel file invalid','data'=>null];
        }
            //printf("%d worksheet\n",  $excel->getSheetCount());
        $this->logger->debug("handleCsvFile:".count($data));
        //$this->logger->debug("handleCsvFile:", $data);
        //$sheet = $excel->getActiveSheet();
        //update database
        switch ($vendor) {
            case 'payconn':
                $total = $this->handlePayConnData($data);
                break;
            case 'gpay':
                $total = $this->handleGPaySheet($sheet);
                break;
        }

        $this->logger->debug("handleCsvFile($f):".$total);

        if ($total>0) {
            if (is_readable($xlspath)) {
                return ['code' => 0, 'msg' => "$total records updated.", 'data' => "$total records", 'file'=> "$xlsfile"];
            }

            return ['code' => 0, 'msg' => "$total records updated.", 'data' => "$total records"];
        } else {
            return ['code' => -4, 'msg' => 'No record on Excel', 'data' => null];
        }
    }

    /*
Map excel meta key to internal set key
'amount'=> ['name'=>'Transaction Amount','required'=>true, 'tags'=>['Transaction Amount']],
*/
    public function mapExcelMetaKey($k, $payconn = 'payconn')
    {
        switch ($payconn) {
            case 'payconn':
                $mappings = $this->pc_table_mappings;
                break;
            case 'gpay':
                $mappings = $this->gpay_table_mappings;
                break;
            case 'ght':
                $mappings = $this->ght_table_mappings;
                break;
        }

        $k = strtolower(trim($k));
        foreach ($mappings as $dbkey => $maps) {
            $name = $maps['name'];
            $name = strtolower($name);
            if ($name==$k) {
                return $name;
            }
            if (isset($maps['tags']) && is_array($maps['tags'])) {
                foreach ($maps['tags'] as $tag) {
                    if (stripos($k, $tag)!==false) {
                        return $name;
                    }
                }
            }
        }
        return false;
    }

    /*
 * Map to DB meta & set default value
 */
    private function mapRecord($a, $payconn = 'payconn')
    {
        $record = array();
        switch ($payconn) {
            case 'payconn':
                $mappings = $this->pc_table_mappings;
                break;
            case 'gpay':
                $mappings = $this->gpay_table_mappings;
                break;
            case 'ght':
                $mappings = $this->ght_table_mappings;
                break;
        }

        foreach ($mappings as $key => $maps) {
            // 'amount'=> ['name'=>'Transaction Amount','required'=>true],
            $col = $maps['name'];
            $col = strtolower($col);

            if (isset($a[$col]) && !empty($a[$col])) {
                $record[$key] = $a[$col];
            }
        }
        //set default value
        /*
        if (!isset($record['currency']))
            $record['currency'] = self::DEFAULT_CURRENCY;
        else
            $record['currency'] = strtoupper($record['currency']);

        if (isset($record['id_number']))
            $record['id_number'] = strtoupper($record['id_number']);
        if (!isset($record['id_type']) && isset($record['id_number']))
            $record['id_type'] = self::DEFAULT_ID_TYPE;
        */
        //if ($this->debug)  var_dump($record);
        return $record;
    }

    function handlePayConnData($data)
    {
            // toArray($nullValue = null, $calculateFormulas = true, $formatData = true, $returnCellRef = false)
        //$data = $sheet->toArray(null,FALSE,FALSE,FALSE);

        if (!is_array($data)) {
                return null;
        }

        $excel_keys = null;
        $count = 0;
            //var_dump($data);
        foreach ($data as $row => $rdata) {
            // Type	State Time	Status	Transaction Time	Current Status	Response Code	Customer	Email
            if (!is_array($excel_keys) && ( array_search('Transaction Id', $rdata) !== false || array_search('TRANSACTION_ID', $rdata) !== false )) {
                $excel_keys = array_map('trim', $rdata, array_fill(0, count($rdata), " \t\n\r\0\x0B:,;."));
                $excel_keys = array_map('strtolower', $excel_keys);

                if (is_array($excel_keys)) {
                    foreach ($excel_keys as $c => $key) {
                        $ikey = $this->mapExcelMetaKey($key);
                        if ($ikey) {
                            $excel_keys[$c] = $ikey;
                        }
                    }
                }
                $this->logger->debug(var_export($excel_keys, true));
                continue;
            }
            if (!is_array($excel_keys)) {
                continue;
            }

            // skip empty row
            for ($i=0; $i< self::MIN_COLUMN_PER_ROW; $i++) {
                if (empty($rdata[$i])) {
                    continue 2;
                }
            }
            $tmp = array();
            //$this->logger->debug(var_export($rdata, true));
            foreach ($rdata as $col => $cell) {
                $key = $excel_keys[$col];
                if (empty($key)) {
                    continue;
                }

                $tmp[$key] = trim($cell);
            }

            $record = $this->mapRecord($tmp);
            $this->logger->debug(var_export($record, true));
            $count += $this->updateTransactionDetails($record);
        }

        //total of updated records
        return $count;
    }

    function handleGPaySheet($sheet)
    {
        // toArray($nullValue = null, $calculateFormulas = true, $formatData = true, $returnCellRef = false)
        $data = $sheet->toArray(null, false, false, false);

        if (!is_array($data)) {
            return null;
        }

        $excel_keys = null;
        $count = 0;
        //var_dump($data);
        foreach ($data as $row => $rdata) {
            // 订单日期(Order date)	订单号(Order No.)	商户订单号(Merchant order No.)	商户号(Merchant No.)	商户名称(Merchant name)	交易类型(trade type)	交易状态(trade status)	姓名(name)	span	银行名称(bank name)	交易金额(元)[transaction amount(RMB)]	交易手续费(元)[transaction fee(RMB)]	交易结果(trade results)
            if (!is_array($excel_keys) && array_search('订单日期(Order date)', $rdata) !== false) {
                //setup meta column
                $excel_keys = array_map('trim', $rdata, array_fill(0, count($rdata), " \t\n\r\0\x0B:,;."));
                $excel_keys = array_map('strtolower', $excel_keys);

                if (is_array($excel_keys)) {
                    foreach ($excel_keys as $c => $key) {
                        //$key = str_replace('(RMB)','',$key);
                        if (preg_match('([\w\s]+)', $key, $matches)) {
                            $this->logger->debug($key, $matches);
                            $key = $matches[0];
                        }
                        $ikey = $this->mapExcelMetaKey($key, 'gpay');
                        if ($ikey) {
                            $excel_keys[$c] = $ikey;
                        }
                    }
                }
                $this->logger->debug(var_export($excel_keys, true));
                continue;
            }
            if (!is_array($excel_keys)) {
                continue;
            }

            // skip empty row
            for ($i=0; $i< self::MIN_COLUMN_PER_ROW; $i++) {
                if (empty($rdata[$i])) {
                    continue 2;
                }
            }
            $tmp = array();
            //$this->logger->debug(var_export($rdata, true));
            foreach ($rdata as $col => $cell) {
                $key = $excel_keys[$col];
                if (empty($key)) {
                    continue;
                }

                $tmp[$key] = trim($cell);
            }

            $record = $this->mapRecord($tmp, 'gpay');
            if (!empty($record['bank_name'])) {
                $record['bank_code'] = $this->getBankCode($record['bank_name']);
            }
            $this->logger->debug(var_export($record, true));
            $count += $this->updateGPayRecord($record, $forceUpdate = true);
        }

        //total of updated records
        return $count;
    }

    function handlePnrText($text)
    {
        $lines = explode("\r", $text);

        $merchant_info = [];
        $transactions = [];

        $line = $lines[0];

        $d = explode('|', $line);
        if (count($d) < 9) {
            throw new Exception('Format of query info is invalid.');
        }

        if (!preg_match('#^\d{4}\-\d{2}-\d{2}$#', $d[1])) {
            throw new Exception('Format of query date is invalid.');
        }

        $query_info = [
            'currency' => $d[0], // CNY
            'date'=> $d[1], // YYYY-mm-dd
            //''=> $d[2],
            'total_amount'=>$d[3], // xx,xxx.xx
            'total_fee'=>$d[4], // xx,xxx.xx
        ];
        
        for ($i = 1; $i < count($lines); $i++) {
            $line = $lines[ $i ];
            $d = explode("|", $line);
            if (count($line) < 10) {
                throw new Exception('Format of line '.($i+1).' is invalid.');
            }
            $tx = [
                'order_no'=>$d['orderNo'],
            ];
        }
    }


    function handleGhtData($data)
    {
        if (!is_array($data)) {
            return null;
        }

        $excel_keys = null;
        $count = 0;
        //$this->logger->debug(var_export($data, true));

        foreach ($data as $row => $rdata) {
            //$this->logger->debug("rdata:", $rdata);
            /*
             * 支付单号	订单号	交易金额	清算汇率
             */
            if (!is_array($excel_keys) && (array_search('支付单号', $rdata) !== false || array_search('Order Number', $rdata) !== false)) {
                $excel_keys = array_map('trim', $rdata, array_fill(0, count($rdata), " \t\n\r\0\x0B:,;."));
                $excel_keys = array_map('strtolower', $excel_keys);

                if (is_array($excel_keys)) {
                    foreach ($excel_keys as $c => $key) {
                        $ikey = $this->mapExcelMetaKey($key, 'ght');
                        if ($ikey) {
                            $excel_keys[$c] = $ikey;
                        }
                    }
                }
                $this->logger->debug("excel_keys:", $excel_keys);
                continue;
            }
            if (!is_array($excel_keys)) {
                continue;
            }

            // skip empty row
            for ($i=0; $i< self::MIN_COLUMN_PER_ROW; $i++) {
                if (empty($rdata[$i])) {
                    $this->logger->debug("skip empty row: $row");
                    continue 2;
                }
            }
            $tmp = array();
            //$this->logger->debug(var_export($rdata, true));
            foreach ($rdata as $col => $cell) {
                $key = $excel_keys[$col];
                if (empty($key)) {
                    continue;
                }

                $tmp[$key] = trim($cell);
            }

            $record = $this->mapRecord($tmp, 'ght');
            $this->logger->debug('record', $record);
            $count += $this->updateGhtRecord($record);
        }

        //total of updated records
        return $count;
    }
    // from Remittance function validateBankName
    public function getBankCode($b)
    {
        $b = str_replace(' ', '', $b);
        $b = strtoupper($b);
        if (strpos($b, '中国银行')===false) {
            $b = str_replace('中国', '', $b);
        }
        $b = str_replace(['有限责任公司','股份有限公司','信用联社','农村商业','有限公司'], ['','','信用社','农商','',''], $b);
        $t = str_replace(['银行','快捷支付'], ['',''], $b);

        $res = \DB::query("SELECT code FROM %l WHERE name=%s OR short_name=%s OR eng_name like %s OR concat( ',',tag,',') like %ss ", self::DATABASE_TABLE_BANKS, $b, $b, $b, ",$t,");
        if (is_array($res) && count($res)==1) {
            return $res[0]['code'];
        }

        return false;
    }

    public function getGatewayBankCode($b, $gateway = 'GHT')
    {
        $b = str_replace(' ', '', $b);
        // BOCQBY
        $b = preg_replace('/QBY$/', '', $b);

        $res = \DB::query("SELECT bank_code FROM %l WHERE gateway LIKE %s AND code LIKE %s ", 'gateway_bank_code', $gateway, $b);
        if (is_array($res) && count($res)==1) {
            return $res[0]['bank_code'];
        }

        return false;
    }

    //fill bank code of all transactions
    public function updateAllBankCode()
    {
        $count = 0;
        $res = \DB::query("SELECT * FROM %l WHERE COALESCE(bank_name,'') !='' and COALESCE(bank_code,0) = 0; ", self::GPAY_TRANSACTION_TABLE);
        if (is_array($res)) {
            foreach ($res as $r) {
                $code = $this->getBankCode($r['bank_name']);
                if ($code) {
                    $r['bank_code']=$code;
                    $required = ['bank_code'=>1, 'order_no'=>1, 'merchant_order_no'=>1];
                    $r = array_intersect_key($r, $required);
                    $count += $this->updateGPayRecord($r, true);
                }
            }
        }
        return $count;
    }

    public function initProcessorAccount()
    {
        if (count($this->processor_account_data)>0) {
            return true;
        }
        $res = \DB::query("SELECT * FROM %b order by ordering desc;", self::PROCESSOR_AC_TABLE);
        if (is_array($res)) {
            foreach ($res as $r) {
                $id = $r['processor'].'-'.$r['account'];
                $this->processor_account_data[$id] = $r;
            }
        }
    }

    public function getProcessorAccountName($p, $ac)
    {
        if (empty($p)||empty($ac)) {
            return false;
        }
        $p = strtoupper($p);
        if (isset($this->processor_account_data["$p-$ac"])) {
            $name = $this->processor_account_data["$p-$ac"]['name'];
            list($fname, ) = explode(' ', $name, 2);
            return strtoupper($fname);
        }
        return false;
    }
}

class PHPExcel_Cell_WcColumnValueBinder extends PHPExcel_Cell_DefaultValueBinder implements PHPExcel_Cell_IValueBinder
{
    protected $metas = [];
    private $logger;
    private $stringCols = [];
    private $numericCols = [];
    private $floatCols = [];    //0.0000


    public function __construct(array $stringColumnList = [])
    {
        // Accept a list of meta columns
        $this->metas = $stringColumnList;
        foreach ($this->metas as $k => $v) {
            if (preg_match('/(_no|_number|account|_ref|no.|ref.|reference)$/i', $v) != false) {
                //e.g. account_no, merchant_ref
                $this->stringCols[] = PHPExcel_Cell::stringFromColumnIndex($k);
            } elseif (preg_match('/(\s+|^)(amount|balance|charge|cny)(\s+|$)/i', $v) != false) {
                $this->numericCols[] = PHPExcel_Cell::stringFromColumnIndex($k);
            } elseif (preg_match('/(\s?rate)$/i', $v) != false) {
                $this->floatCols[] = PHPExcel_Cell::stringFromColumnIndex($k);
            }
        }

        $this->logger = new Logger('wc_logger');
        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/PHPExcel_Cell_IValueBinder.log', Logger::DEBUG));
        //$this->logger->debug("numericCols", $this->numericCols);
    }

    public function bindValue(PHPExcel_Cell $cell, $value = null)
    {
        //printf("col:%s, val:%s\n", $cell->getColumn(), $value);
        //$this->logger->debug("Col:", [$cell->getColumn(), $value]);
        // If the cell is one of our columns to set as a string...
        if (count($this->stringCols) && in_array($cell->getColumn(), $this->stringCols)) {
            // ... then we cast it to a string and explicitly set it as a string
            $cell->setValueExplicit((string) $value, PHPExcel_Cell_DataType::TYPE_STRING);
            return true;
        }
        if (count($this->numericCols) && in_array($cell->getColumn(), $this->numericCols) && is_numeric($value)) {
            //$cell->getStyle()->getNumberFormat()->setFormatCode('0.00');
            $cell->getStyle()->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            $cell->setValueExplicit(sprintf('%.2f', "$value"), PHPExcel_Cell_DataType::TYPE_NUMERIC);
            return true;
        }
        if (count($this->floatCols) && in_array($cell->getColumn(), $this->floatCols) && is_numeric($value)) {
            $cell->getStyle()->getNumberFormat()->setFormatCode('0.0000');
            $cell->setValueExplicit(sprintf('%.4f', "$value"), PHPExcel_Cell_DataType::TYPE_NUMERIC);
            return true;
        }
        // Otherwise, use the default behaviour
        return parent::bindValue($cell, $value);
    }
}
