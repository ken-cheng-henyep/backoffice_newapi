<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

class RemittanceReportReader
{
    const DATE_FORMAT='Ymd';
    const XLS_FILE_TYPE = 'Excel2007';
    const FX_RATE_API_URL = 'http://fxrate.wecollect.com/service/getrate?symbol=%s&merchantid=%s&datetime=%s';
    const REMITTANCE_RATE_API_URL = 'http://fxrate.wecollect.com/service/getrate?symbol=%s&datetime=%s';

    const HYCM_MERCHANT_ID = 'ab124bac-a6a4-11e4-8537-0211eb00a4cc';
    const SKIP_GET_FX_RATE = '';//'HKD';
    const DEFAULT_REMITTANCE_RATE_SYMBOL = 'USDR02';
    /*
    const DATABASE_USER = 'wcuser';
    const DATABASE_PASSWORD = 'gtU7$gw5$tg';
    */
    const DATABASE_USER = 'mysqlu';
    const DATABASE_PASSWORD = '362gQtSA_QA7QroNS';
    const DATABASE_TABLE_BANKS = 'banks';
    const DATABASE_TABLE_PROVINCE = 'china_province';
    const DATABASE_TABLE_BATCH = 'remittance_batch';
    const DATABASE_TABLE_REMITTANCE = 'remittance_log';
    const DATABASE_TABLE_INSTANTREQ = 'instant_request';
    const DATABASE_TABLE_REPORT = 'remittance_report';
    const DATABASE_TABLE_PROCESSORS = 'processors';
    const DATABASE_TABLE_AUTHORIZATION = 'remittance_authorization';
    const DATABASE_TABLE_NOTIFICATION = 'notification';
    const DATABASE_TABLE_NOTIFICATIONSTATE = 'notification_state';
    const DATABASE_TABLE_NOTIFICATIONLOG = 'notification_log';
    const DATABASE_TABLE_API_LOG = 'remittance_api_log';
    const DATABASE_TABLE_FILTER = 'remittance_filter';
    const DATABASE_TABLE_FILTER_RULE = 'remittance_filter_rule';

    //const EXCEL_TEMP_PATH = ROOT;   //'/tmp/xls/'; //'/src/settlement_report/data/tmp/';
    const CHINAGPAY_EXCEL_TEMPLATE = '/src/settlement_report/webroot/xls/chinagpay-template.xls';
    //const CHINAGPAY_EXCEL_ORDERID_PATH = '/src/settlement_report/data/';
    const CHINAGPAY_EXCEL_ORDERID_START = 101;

    const PAYMENTASIA_EXCEL_TEMPLATE = '/src/settlement_report/webroot/xls/paymentasia-template.xlsx';
    //const MERCHANT_REPORT_EXCEL_TEMPLATE = '/src/settlement_report/webroot/xls/remittance_report_plan_a1.xlsx';
    const MERCHANT_REPORT_EXCEL_TEMPLATE = '/webroot/xls/remittance_report_plan_a_201704.xlsx';
    //const MERCHANT_REPORT_EXCEL_TEMPLATE2 = '/src/settlement_report/webroot/xls/remittance_report_plan_b.xlsx';
    const MERCHANT_REPORT_EXCEL_TEMPLATE2 = '/webroot/xls/remittance_report_plan_b_201704.xlsx';

    const GHT_EXCEL_TEMPLATE = '/src/settlement_report/webroot/xls/ght-template.xls';
    const GHT_MERCHANT_CODE = '000000000101244';
    const JOINPAY_REMITTANCE_TEMPLATE = '/data/template/joinpay_merchant_remittance000.xls';
    //Instant Remittance Search Result
    const INSTANT_REMITTANCE_SEARCHRESULT_TEMPLATE = '/data/template/instant_tx_search_report_template_b.xlsx';

    const SETTLEMENT_DATE_TITLE = 'Settlement Date:';
    const RECIPIENT_NAME_TITLE = 'Beneficiary Name';
    // Beneficiary Account No.
    const RECIPIENT_AC_TITLE = 'Beneficiary Account Number';
    const MIN_COLUMN_PER_ROW = 3;
    //Maximum number of rows read from Excel sheet
    const MAX_ROW_PER_SHEET = 1000;
    const ROUND_PRECISION = 2;  //for amount calculation
    const ROUND_PRECISION_OF_RATE = 4;  //for exchange rate
    const DEFAULT_CURRENCY = 'CNY';
    const DEFAULT_ID_TYPE = 0; //id card, 1	Residence Booklet, 2 Passport
    const DEFAULT_REMITTANCE_CHARGE_RATE = '0.5';
    const INTERNAL_NOTIFICATION_EMAIL = 'remittance@wecollect.com';
    const DEFAULT_LOCAL_REMITTANCE_TARGET = 3;   //ChinaGPay API
    const DEFAULT_LOCAL_REMITTANCE_FEE_CNY = 5;

    const BATCH_STATUS_CANCELLED = -11; // Cancelled by merchant
    const BATCH_STATUS_OPEN = -2;   // Open by merchant in Single Request mode
    const BATCH_STATUS_DECLINED = -10;
    const BATCH_STATUS_QUEUED = -5;
    const BATCH_STATUS_PROCESS = -1;
    const BATCH_STATUS_COMPLETED = 0;

    const BATCH_STATUS_SIGNING = -7;    //for API Single Req
    const BATCH_STATUS_AUTHORIZED = -99;    //for Authorization action only, before Queued
    //set by merchant
    const RM_STATUS_ACCEPTED = -5;  // Accepted remittance log
    const RM_STATUS_REJECTED = -9;
    const RM_STATUS_FAILED_AMENDED = -2;  //Failed (amended) after batch completed
    const RM_STATUS_DEFAULT = 0;    //db table default value
    const RM_STATUS_OK_AMENDED = 2;  //OK (amended) after batch completed
    // set by API/Processor
    const RM_STATUS_FAILED = -1;
    const RM_STATUS_OK = 1;
    //instant request
    const IR_STATUS_PENDING = 0;  //initial state
    const IR_STATUS_PROCESSING = 1;
    const IR_STATUS_OK = 10;
    const IR_STATUS_FAILED = -1;
    const IR_STATUS_REJECTED = -2;
    const IR_STATUS_BLOCKED = -3;
    // Service suspension period
    const SERVICE_CUTOFF_START = 2230;
    const SERVICE_CUTOFF_END = 2330;

    public $db_name;
    public $settlement_date;
    //from merchant config, settle_currency
    public $settlement_currency;
    public $remittances;
    public $validation_warns = NULL;
    public $validation_errors = NULL;
    public $excel_file;
    public $pdf_file;
    public $merchant_id;
    public $username;
    public $ip;
    public $currency;
    private $transaction_limit_cny = 1000000;
    private $validation_skips = NULL;

    private $excel;
    private $debug=FALSE;
    private $excel_keys = NULL;
    private $table_mappings = [
        // db field => ['name'=>'col name on excel','required'=>true, 'tags'=>['other col name on excel']],
//        'client_id'=> ['name'=>'Client ID/ Name','required'=>false, 'tags'=>['Client ID']],
        'beneficiary_name'=> ['name'=>'Beneficiary Name','required'=>true],
        'account'=> ['name'=>self::RECIPIENT_AC_TITLE,'required'=>true, 'tags'=>['Beneficiary Account']],
        'bank_name'=> ['name'=>'Bank Name','required'=>true],
        'bank_branch'=> ['name'=>'Bank Branch','required'=>false],
        'province'=> ['name'=>'Province','required'=>false],
        'city'=> ['name'=>'City','required'=>false],
        'amount'=> ['name'=>'Transaction Amount','required'=>true, 'tags'=>['Transaction Amount Received']],
        'id_number'=> ['name'=>'ID Card No','required'=>true],
        //not required
        'currency'=> ['name'=>'Currency'],
        'id_type'=> ['name'=>'ID Card Remarks', 'tags'=>['ID Card Type']],
        'merchant_ref'=> ['name'=>'Merchant Reference', 'max_length'=>64],
    ];
    private $instant_table_mappings = [
        // db field => ['name'=>'col name on excel','required'=>true, 'tags'=>['other col name on excel']],
        'name'=> ['name'=>'Name','required'=>true],
        'account'=> ['name'=>'account_no','required'=>true, 'tags'=>['Beneficiary Account']],
        'bank_name'=> ['name'=>'Bank Name','required'=>true],
        'bank_branch'=> ['name'=>'branch_name','required'=>false],
        'province'=> ['name'=>'Province','required'=>false],
        'city'=> ['name'=>'City','required'=>false],
        'currency'=> ['name'=>'Currency','required'=>true], // CNY / USD
        'amount'=> ['name'=>'Transaction Amount','required'=>true, 'tags'=>['Transaction Amount Received']],
        'id_number'=> ['name'=>'id_card','required'=>true],
        'test_trans'=> ['name'=>'test_trans','required'=>true],
        //not required
        'id_type'=> ['name'=>'id_card_type', 'tags'=>['ID Card Type']],
        'merchant_ref'=> ['name'=>'Merchant Reference', 'max_length'=>64],
    ];
    /*
0 (default) 	ID card	身份证
1	Residence Booklet	户口簿
2	Passport	护照
3	Officer ID	军官证
4	Soldier ID	士兵证
5	Mainland Travel Permit for Hong Kong and Macau Residents	港澳居民来往内地通行证
6	Mainland Travel Permit for Taiwan Residents	台湾同胞来往内地通行证
7	Temporary identity card	临时身份证
8	Residence Permit for Foreigners	外国人居留证
9	Police ID	警官证
X	Other paper	其他证件
     */
    public static $status_mappings = [self::BATCH_STATUS_QUEUED=>'Queued', self::BATCH_STATUS_PROCESS=>'Processing', self::BATCH_STATUS_COMPLETED=>'Completed', self::BATCH_STATUS_DECLINED=>'Declined',
        self::BATCH_STATUS_OPEN=>'Open', self::BATCH_STATUS_CANCELLED=>'Cancelled',
        self::BATCH_STATUS_SIGNING=>'Signing', self::BATCH_STATUS_AUTHORIZED=>'Authorized',
    ];
    public static $logstatus_mappings = [
        self::RM_STATUS_FAILED => 'Failed',
        self::RM_STATUS_DEFAULT => 'Pending', //'N/A',
        self::RM_STATUS_OK => 'OK',
        //used by Backoffice Admin
        self::RM_STATUS_OK_AMENDED => 'OK (amended)',
        self::RM_STATUS_FAILED_AMENDED => 'Failed (amended)', //used by backoffice
        self::RM_STATUS_REJECTED => 'Rejected',
        self::RM_STATUS_ACCEPTED => 'Authorized', //'Accepted',
    ];
    //instant request
    public static $ir_status_mappings = [
        self::IR_STATUS_PENDING => 'Pending',  //initial state
        self::IR_STATUS_PROCESSING => 'Processing',
        self::IR_STATUS_OK => 'OK',
        self::IR_STATUS_FAILED => 'Failed',
        self::IR_STATUS_REJECTED => 'Rejected',
        self::IR_STATUS_BLOCKED => 'Blocked',
    ];
    public static $target_mappings = [1=>'Payment Asia Excel', 2=>'ChinaGPay Excel', 3=>'ChinaGPay API', 4=>'Gnete Excel', 5=>'Gnete API',
        6=>'GHT Excel', 7=>'GHT API', 8=>'Test API',
        10=>'Payment Asia Excel (Local)',
        11=>'JoinPay Excel',
        13=>'Avoda API',
    ];
    // Processor Return Message Translation
    public static $processor_return_en_messages = [
        '户名错误$'=>'Account name incorrect',
        '姓名有误$'=>'Account name incorrect',
        '状态不正常$'=>'Account status abnormal',
        '账号错误$'=>'Account number incorrect',
        '系统不存在\S+的卡BIN'=>'Account BIN abnormal',
        '^系统正在对数据处理'=>'Processing',
        '^交易进行中'=>'Processing',
        '卡号与发卡行不匹配'=>'Account number incorrect',
        '银行卡无效或状态有误'=>'Account status abnormal',
    ];

    private $EXCEL_TEMP_PATH;
    private $EXCEL_REPORT_PATH = '/data/report/';
    private $CHINAGPAY_EXCEL_ORDERID_PATH;
    const TEST_API_TARGET = 8; // target for test_trans

    private $validCurrencys = ['USD','CNY','HKD'];
    private $validInstantRemitCurrencys = ['USD','CNY','HKD'];
    private $fxrate_caches = NULL;
    private $file_db;
    private $id_pool;
    //checksum of each batch row
    private $row_checksums = [] ;
    // Filled excel rows number in excel
    private $filled_excel_rows = [] ;

    public $logger, $timenow;

    private $last_col=10;
    private $json_code = 0;
    private $json_msg = 'OK';

    function __construct($db='', $debug=false) {
        $this->EXCEL_TEMP_PATH = ROOT .'/tmp/xls/';
        //$this->EXCEL_REPORT_PATH = ROOT .'/data/report/';
        $this->CHINAGPAY_EXCEL_ORDERID_PATH = ROOT .'/data/';
        $this->debug=$debug;


        $db_host = 'localhost';
        if(!empty($_ENV['DB_HOST']))
            $db_host = $_ENV['DB_HOST'];

        if(!empty($_SERVER['DB_HOST']))
            $db_host = $_SERVER['DB_HOST'];


        if (!empty($db)) {
            $this->db_name = $db;
            DB::$host = $db_host;
            DB::$user = self::DATABASE_USER;
            DB::$password = self::DATABASE_PASSWORD;
            DB::$dbName = $this->db_name;
            DB::$encoding = 'utf8';
            DB::$error_handler = false; // since we're catching errors, don't need error handler
            DB::$throw_exception_on_error = true;
            // DB::debugMode(); // echo out each SQL command being run, and the runtime
        }

        $this->timenow = date('Y-m-d H:i:s');
        $this->logger = new Logger('wc_logger');
        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/RemittanceReportReader.log', Logger::DEBUG));

        $this->file_db = new Flintstone('Remittance', ['dir' => $this->CHINAGPAY_EXCEL_ORDERID_PATH, 'cache'=>false]);
        set_time_limit(180);    //3 mins for generating excel report
    }

    public function newUuid() {
        // return random UUID
        //use Ramsey\Uuid\Uuid;
        //use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

        try {
            $uuid4 = Uuid::uuid4();
            return $uuid4->toString();
        } catch (UnsatisfiedDependencyException $e) {
            $this->logger->debug("newUuid exception: ". $e->getMessage());
            return false;
        }
    }

    public function setMerchant($m) {
        if (!empty($m))
            $this->merchant_id = trim($m);
    }

    public function handleExcelFile($mid, $f, $pdfile='') {
        $this->logger->debug("handleExcelFile ($mid, $f, $pdfile)");
        
        if (!is_readable($f))
            return ['code'=>-2, 'msg'=>'Excel file unreadable','data'=>NULL];
            //throw new Exception("File not readable. ($f)");
        try {
            $filetype = PHPExcel_IOFactory::identify($f);
            //$objReader = PHPExcel_IOFactory::createReader(XLS_FILE_TYPE);
            $objReader = PHPExcel_IOFactory::createReader($filetype);
            /**  Load $inputFileName to a PHPExcel Object  **/
            //$objReader->setReadDataOnly(TRUE);

            $this->excel = $objReader->load($f);
            $this->excel_file = $f;
            $this->merchant_id = $mid;
        } catch (Exception $e) {
            $this->logger->debug("handleExcelFile Exception:".$e);
            //return ['code'=>2, 'msg'=>'Excel file unreadable','data'=>NULL];
            return ['code'=>-2, 'msg'=>'Excel file invalid','data'=>NULL];
        }

        if (!empty($pdfile)) {
            if(! $this->isValidPDF($pdfile))
                return ['code'=>-3, 'msg'=>'Signature PDF file invalid','data'=>NULL];
            $this->pdf_file = $pdfile;
        }

        if ($this->debug)
            printf("%d worksheet\n",  $this->excel->getSheetCount());
        $sheet = $this->excel->getActiveSheet();
        $this->logger->debug("getActiveSheet OK");

        $this->handleRemittanceSheet($sheet);

        if (!is_array($this->validation_errors)) {
            //no valid excel row
            if (!is_array($this->remittances) || count($this->remittances)< 1) {
                $this->logger->debug("No Remittance record to insert");
                return ['code'=>-2, 'msg'=>'Excel file empty','data'=>NULL];
            }

            //save DB
            try {
                $batch_id = $this->insertRemittances();
            } catch(MeekroDBException $e) {
                return ['code' => -4, 'msg' => sprintf('Database Error:%s, SQL:%s', $e->getMessage(), $e->getQuery()), 'data' => NULL];
            }

            if (empty($batch_id))
                return ['code' => -3, 'msg' => 'Error on adding Remittance to database', 'data' => NULL];

            //check filters for whole batch
            $filter_errs = $this->validateBatchFilters($batch_id);
            if (is_array($filter_errs)) {
                return ['code' => -4, 'msg' => 'Excel validation failed', 'data' => ['validation_errors' => $filter_errs]];
            }

            return ['code' => 0, 'msg' => 'Remittance validation ok', 'data' => NULL, 'batch_id' => $batch_id];
        } else {
            //return ['code' => 1, 'msg' => 'Remittance validation failed', 'data' => ['validation_errors' => $this->validation_errors]];
            return ['code' => -4, 'msg' => 'Excel validation failed', 'data' => ['validation_errors' => $this->validation_errors]];
        }
    }

    public function handleRemittanceSheet(&$sheet) {
        $maxRow = $sheet->getHighestRow();

        $this->logger->debug("handleRemittanceSheet:",['maxRow'=>$maxRow, 'maxCol'=>$sheet->getHighestColumn()]);
        // toArray($nullValue = null, $calculateFormulas = true, $formatData = true, $returnCellRef = false)
        //$data = $sheet->toArray(null,FALSE,FALSE,FALSE);
        //$this->logger->debug("handleRemittanceSheet:".var_export($data,true));
        $maxRow = min($maxRow, self::MAX_ROW_PER_SHEET);
        $this->logger->debug("Fixed:",['maxRow'=>$maxRow]);

        //select up to column Z
        //rangeToArray(string $pRange, mixed $nullValue, boolean $calculateFormulas, boolean $formatData, boolean $returnCellRef) : array
        $data = $sheet->rangeToArray("A1:Z$maxRow", null,FALSE,FALSE,FALSE);

        //$this->logger->debug("handleRemittanceSheet:".var_export($data,true));
        $this->logger->debug("handleRemittanceSheet:",['count'=> count($data)]);

        if (!is_array($data))
            return NULL;
        //var_dump($data);
        foreach ($data as $row=>$rdata) {
            //col H
            //$this->logger->debug("settlement_date: $row,".$rdata[7]);
            //if (stripos($this->trimTitle($rdata[7]), self::SETTLEMENT_DATE_TITLE)!==false && !empty($rdata[8])) {
            if (!isset($this->settlement_date))
            if (($settle_col = array_search(self::SETTLEMENT_DATE_TITLE, $rdata))!==false && !empty($rdata[$settle_col+1])) {
                $sdate = $this->getExcelDate($rdata[$settle_col+1]);
                $this->logger->debug("settlement_date:".$rdata[$settle_col+1], [$sdate,strtotime($sdate)]);
                if ($sdate!=false && ($ts=strtotime($sdate))!=false)
                    $this->settlement_date = date('Y-m-d 00:00:00',$ts);
                else
                    $this->settlement_date = $this->timenow;
            }

            //if (!is_array($this->excel_keys) && stripos($rdata[0],self::RECIPIENT_NAME_TITLE)!==false) {
            if (!is_array($this->excel_keys) && array_search(self::RECIPIENT_NAME_TITLE, $rdata)!==false) {
                $this->excel_keys = array_map('trim',$rdata, array_fill(0, count($rdata), " \t\n\r\0\x0B:,;."));
                $this->excel_keys = array_map('strtolower', $this->excel_keys);

                if (is_array($this->excel_keys))
                foreach ($this->excel_keys as $c=>$key) {
                    $ikey  = $this->mapExcelMetaKey($key);
                    if ($ikey)
                        $this->excel_keys[$c] = $ikey;
                }
                $this->logger->debug(var_export($this->excel_keys,true));
                continue;
            }
            if (!is_array($this->excel_keys))
                continue;

            //$this->logger->debug(var_export($this->excel_keys,true));
            // skip empty row
            $filled_col = 0;
            for($i=0; $i<6; $i++) {   //check A-Z
                if (!empty($rdata[$i]))
                    $filled_col++;
            }
            if ($filled_col < self::MIN_COLUMN_PER_ROW)
                continue;
            /*
            for($i=0; $i< self::MIN_COLUMN_PER_ROW; $i++) {
                if (empty($rdata[$i]))
                    continue 2;
            }
            */
            $tmp = array();
            foreach ($rdata as $col=>$cell){
                $key = $this->excel_keys[$col];
                if (empty($key))
                    continue;

                $tmp[$key] = trim($cell);
            }
            if ($this->debug)  var_dump($tmp);
            //row number starting from 1
            $this->filled_excel_rows[] = $row+1;
            //check rec
            $valids = $this->validateRecord($tmp, $this->validation_skips);
            if ($valids['code']==0) {
                $this->remittances[] = $tmp;
            } else {
                $this->validation_errors[] = ['row'=>$row+1, 'error_code'=>$valids['code'], 'error_msg'=>$valids['msg']];
            }

            if ($this->debug)  {
                var_dump($valids);
                var_dump($tmp);
            }
        } //foreach

        if ($this->debug) {
            var_dump($this->settlement_date);
            var_dump($this->remittances);
        }
    }

    public function setExcelSkippedFields($a) {
        if (!is_array($a))
            return false;
        $this->validation_skips = $a;

        $this->logger->debug("setExcelSkippedFields:", $a);
    }
    /*
Map excel meta key to internal set key
    'amount'=> ['name'=>'Transaction Amount','required'=>true, 'tags'=>['Transaction Amount']],
*/
    public function mapExcelMetaKey($k) {
        $k = strtolower(trim($k));
        foreach ($this->table_mappings as $dbkey=>$maps) {
            $name = $maps['name'];
            $name = strtolower($name);
            if ($name==$k)
                return $name;
            if (isset($maps['tags']) && is_array($maps['tags'])) {
                foreach ($maps['tags'] as $tag)
                    if (stripos($k, $tag)!==false)
                        return $name;
            }
        }

        return FALSE;
    }

    //public function getStatus($s) {
    static public function getStatus($s) {
        //$this->log("getStatus($s)", 'debug');
        //$this->logger->debug("getStatus($s)");

        if (isset(self::$status_mappings[$s]))
            return self::$status_mappings[$s];
        return 'Unknown';
    }

    static public function getStatusVal($s) {
        $s = strtolower($s);
        foreach (self::$status_mappings as $k=>$v)
            if ($s==strtolower($v))
                return $k;
        return false;
    }

    public function getLogStatus($s) {
        if (isset(self::$logstatus_mappings[$s]))
            return self::$logstatus_mappings[$s];
        return 'N/A';
    }

    static public function getInsReqStatus($s) {
        if (isset(self::$ir_status_mappings[$s]))
            return self::$ir_status_mappings[$s];
        return 'N/A';
    }

    static public function getInsReqStatusVal($s) {
        $s = strtolower($s);
        foreach (self::$ir_status_mappings as $k=>$v)
            if ($s==strtolower($v))
                return $k;
        return false;
    }

    static public function getTargetName($t) {
        if (isset(self::$target_mappings[$t]))
            return self::$target_mappings[$t];
        return 'N/A';
    }

    static public function getTimeNow() {
        return date('Y-m-d H:i:s');
    }

    static public function isValidStateChange($now, $new) {
        /*
        if ($now==$new)
            return true;
        */
        switch ($now) {
            case self::BATCH_STATUS_OPEN:
                //Queued for pre-authorized only
                if (in_array($new, [self::BATCH_STATUS_OPEN, self::BATCH_STATUS_CANCELLED, self::BATCH_STATUS_SIGNING, self::BATCH_STATUS_QUEUED]))
                    return true;
                return false;
            case self::BATCH_STATUS_CANCELLED:
                return false;
            case self::BATCH_STATUS_COMPLETED:
                return false;
            case self::BATCH_STATUS_PROCESS:
                if (in_array($new, array(self::BATCH_STATUS_COMPLETED, self::BATCH_STATUS_DECLINED)))
                    return true;
                return false;
            case self::BATCH_STATUS_QUEUED:
                if (in_array($new, array(self::BATCH_STATUS_PROCESS, self::BATCH_STATUS_DECLINED, self::BATCH_STATUS_CANCELLED)))
                    return true;
                return false;
            case self::BATCH_STATUS_DECLINED:
                return false;
            case self::BATCH_STATUS_SIGNING:
                if (in_array($new, [self::BATCH_STATUS_QUEUED, self::BATCH_STATUS_CANCELLED, self::BATCH_STATUS_AUTHORIZED]))
                    return true;
                return false;
        }
        return false;
    }

    static public function getProcessorReturnMessageEnglish($chi) {
        foreach (self::$processor_return_en_messages as $pattern=>$msg) {
            if (preg_match("/$pattern/i", $chi))
                return $msg;
        }
        //default
        return '';
    }

    //check & update batch status (int)
    public function setBatchStatus($id, $status) {
        $batchs = $this->getBatchDetails($id);
        if (!is_array($batchs))
            return false;

        $cstatus = $batchs[0]['status'];
        if (! self::isValidStateChange($cstatus, $status))
            return false;

        $this->logger->debug("setBatchStatus($id, $status) now:{$cstatus}");
        $updates = ['status'=>$status, 'update_time'=> self::getTimeNow()];

        switch ($status) {
            case self::BATCH_STATUS_COMPLETED :
                $updates['complete_time'] =  self::getTimeNow();
                break;
            case self::BATCH_STATUS_PROCESS :
                $updates['approve_time'] =  self::getTimeNow();
                break;
            case self::BATCH_STATUS_DECLINED :
                break;
        }
        DB::update(self::DATABASE_TABLE_BATCH, $updates, "id=%s", $id);
        $this->addNotificationLogs($this->merchant_id, $id);
        //update batch count & total
        $this->updateBatch($id, $rate_update=FALSE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=true);
    }

    //update fee on rm_log table for whole batch
    public function setBatchFee($bid, $toCurrency='USD') {
        //convert_currency
        $this->logger->debug("setBatchFee($bid, $toCurrency)");

        if (empty($bid))
            return false;
        //$res = DB::query("SELECT *, status as tx_status FROM %b WHERE batch_id=%s", self::DATABASE_TABLE_REMITTANCE, $bid);
        $res = $this->getBatchDetails($bid);
        if (!is_array($res))
            return false;

        $this->logger->debug("DB",$res[0]);

        $toCurrency = strtoupper(trim($toCurrency));
        //use currency from database (same on excel sheet)
        //$db_currency = (isset($res[0]['currency'])?$res[0]['currency']:$toCurrency);
        $db_currency = (isset($res[0]['convert_currency'])?$res[0]['convert_currency']:$toCurrency);
        if ($db_currency==self::DEFAULT_CURRENCY)
            $db_currency = $res[0]['currency'];
            //$db_currency = 'USD';

        $toCurrency = $db_currency;

        $mrate = $this->getRmRate($this->merchant_id, $time = '', 'CNY', $toCurrency);
        if (!$mrate)
            return false;
        $this->logger->debug("mrate", [$mrate]);

        foreach ($res as $r) {
            $this->logger->debug("r", $r);

            if (! $this->isValidRemittance($r)) {
                $this->logger->debug("NOT isValidRemittance");
                continue;
            }
            $updates = array();
            $invert = ($r['currency']!='CNY');

            $amt_cny = $amt = $r['amount'];

            $rate = $mrate;
            if ($invert) {
                $rate = (1/$mrate);
                $toCurrency = 'CNY';
                $amt_cny = $amt_cny/$rate;
                $this->logger->debug('Invert rate', compact('rate', 'toCurrency', 'amt_cny'));
            }
            //round before saving to DB
            /*
            wcRoundNumber($rate, true);
            wcRoundNumber($amt_cny);
            */

            $updates['convert_rate'] = $rate;
            $updates['convert_currency'] = $toCurrency;
            $updates['convert_amount'] = ($amt / $rate);
            //wcRoundNumber($updates['convert_amount']);

            $fees = $this->getInstantRequestFee();
            $updates['fee_cny'] = $fees['fee'];
            //$convert_fee = ($updates['fee_cny'] / $rate);
            //fee in USD
            $convert_fee = ($invert?($updates['fee_cny']*$rate):($updates['fee_cny'] / $rate));
            //wcRoundNumber($convert_fee);

            if ($fees['client_paid']) {
                //client bear the fee
                $updates['gross_amount_cny'] = $amt_cny - $updates['fee_cny'];
                $updates['paid_amount'] = $amt;
            } else {
                $updates['gross_amount_cny'] = $amt_cny;
                $updates['paid_amount'] = $amt + ($invert?$convert_fee:$updates['fee_cny']);
            }
            //wcRoundNumber($updates['gross_amount_cny']);
            //wcRoundNumber($updates['paid_amount']);
            //$updates['convert_paid_amount'] = ($updates['paid_amount'] / $rate);
            if ($invert) {
                //CNY
                $updates['convert_paid_amount'] = $updates['gross_amount_cny']+$updates['fee_cny'];
            } else {
                $updates['convert_paid_amount'] = ($updates['paid_amount'] / $rate);
            }
            //wcRoundNumber($updates['convert_paid_amount']);
            //check if no amount to send (when fee >= request amount)
            if ($updates['gross_amount_cny'] <= 0) {
                $this->logger->debug("gross_amount_cny is zero, cannot cover fee", [$updates, 'batch_id'=>$bid]);
                //return false;
                continue;
            }

            try {
                DB::update(self::DATABASE_TABLE_REMITTANCE, $updates, "id=%s AND batch_id=%s", $r['id'], $bid);
            } catch (MeekroDBException $e) {
                $this->logger->debug("Error: ", [$e->getMessage()]);
                $this->logger->debug("SQL: ", [$e->getQuery()]);
                return false;
            }
        }   //foreach
    }

    public function validateRecord(&$a, $skipped = null) {
        if (!is_array($a))
            return ['code'=>99, 'msg'=>'No data'];
        //check missing
        $miss = $warnings = array();
        foreach ($this->table_mappings as $key=>$maps) {
            // 'amount'=> ['name'=>'Transaction Amount','required'=>true],
            if (!isset($maps['required']) || !$maps['required'])
                continue;
            $col = $maps['name'];
            $col = strtolower($col);
            //override required field
            if (is_array($skipped) && in_array($col,$skipped))
                continue;
            if (!isset($a[$col]) || empty($a[$col]))
                $miss[]=$col;
        }
        if (count($miss)>0)
            return ['code'=>100, 'msg'=>sprintf('Field "%s" cannot be empty', implode(',',$miss))];

        $this->logger->debug("excel row:", $a);
        //map to table record
        $record = $this->mapRecord($a);
        $this->logger->debug("validateRecord:", $record);
        //check account
        $record['account'] = preg_replace('/\D/', '', $record['account']);  //remove non-digit
        if (preg_match('/^\d+$/', $record['account'])==false)
            return ['code'=>101, 'msg'=>'Beneficiary Account No. invalid'];
        if (preg_match('/^\d{15,19}$/', $record['account'])==false)
            return ['code'=>107, 'msg'=>'Beneficiary Account No. invalid length'];

        //check Bank Name
        if (($bank_code=$this->validateBankName($record['bank_name']))==false) {
            //return ['code' => 102, 'msg' => 'Bank Name invalid'];
            $warnings[] = ['code' => 300, 'msg' => 'Bank Name may be invalid'];
        } else {
            $record['bank_code'] = $bank_code;
        }
        //fix branch name, e.g. 中国工商银行蚌埠张公山支行 to 蚌埠张公山支行
        if (!empty($record['bank_branch'])) {
            $record['bank_branch'] = preg_replace('/^'.$record['bank_name'].'?([\PZ\PC]*)$/u', '$1', $record['bank_branch']);
        }
        //check Province
        if (!empty($record['province']) && ($province_code=$this->validateProvince($record['province']))==false)
            return ['code'=>103, 'msg'=>'Province invalid'];
        if (isset($province_code))
            $record['province_code'] = $province_code;

        //non English name
        $record['beneficiary_name'] = trim($record['beneficiary_name']);
        if (preg_match('/^[A-Za-z\s]+$/', $record['beneficiary_name'])==false)
            $record['beneficiary_name'] = str_replace(' ','',$record['beneficiary_name']);
        //remove space
        if (isset($record['id_number']))
            $record['id_number'] = preg_replace('/[^0-9A-Za-z]/','', $record['id_number']);
        //check ID
        $record['id_type'] = $this->validateIdType($record['id_type']);
        if ($record['id_type']===FALSE) {
            return ['code' => 204, 'msg' => 'Invalid ID Card Type'];
        }
        //$this->logger->debug("validateIdType($type) = ".$record['id_type']);

        if (isset($record['id_type']) && $record['id_type'] == self::DEFAULT_ID_TYPE && !empty($record['id_number']) ) {
            $idchecks = $this->validateIdCard($record['id_number']);
            if ($idchecks['code']!=0)
                return $idchecks;
        }
        $idnum = (isset($record['id_number'])?$record['id_number']:'');
        if (!empty($idnum)) {
            if (!isset($this->id_pool[$idnum]))
                $this->id_pool[$idnum] = $record['beneficiary_name'];
            elseif ($this->id_pool[$idnum] != $record['beneficiary_name'])
                return ['code'=>108, 'msg'=>'Same ID Card No. used for different Beneficiary Name'];
        }
        //check currency
        $record['currency'] = trim(strtoupper($record['currency']));
        $record['currency'] = str_replace('RMB', 'CNY', $record['currency']);
        if (!in_array($record['currency'], $this->validCurrencys) || !$this->validateWallet($record['currency']) || !$this->validateSettleCurrency($record['currency']))
            return ['code'=>104, 'msg'=>'Currency invalid'];
        if (!isset($this->currency))
            $this->currency = $record['currency'];
        elseif ($this->currency != $record['currency'])
            return ['code'=>109, 'msg'=>'All transactions must be specified in the same currency'];
        //check amount
        $record['amount'] = floatval($record['amount']);
        if ($record['amount'] == false)
            return ['code'=>105, 'msg'=>'Transaction amount invalid'];
        if ($record['amount'] <= 0)
            return ['code'=>106, 'msg'=>'Transaction amount must be larger than zero'];
        if ( ($check=$this->checkAmountLimit($record['amount'], $record['currency']))!==true)
            return $check;

        if (! $this->validateBatchDuplicate($record))
            return ['code'=>111, 'msg'=>'Duplicated transaction'];
        //if ($this->debug)  var_dump($record);
        if (count($warnings)) {
            $record['validation'] = json_encode($warnings);
        }
        $a = $record;
        return ['code'=>0, 'msg'=>'OK', 'warnings'=>$warnings ];
    }

    /*
     * Validate Instant Remittance Request
     */
    public function validateInstantReq(&$a, $skipped = null) {
        if (!is_array($a))
            return ['code'=>99, 'msg'=>'No data'];
        //check missing
        $miss = $warnings = array();
        foreach ($this->instant_table_mappings as $key=>$maps) {
            // 'amount'=> ['name'=>'Transaction Amount','required'=>true],
            if (!isset($maps['required']) || !$maps['required'])
                continue;
            $col = $maps['name'];
            $col = strtolower($col);
            //req exists same key as db
            //if (isset($a[$key]) && !empty($a[$key]))
            if (isset($a[$key]) && $a[$key]!='')    //0 != empty
                continue;
            //override required field
            if (is_array($skipped) && in_array($col,$skipped))
                continue;
            if (!isset($a[$col]) || empty($a[$col]))
                $miss[]=$col;
        }
        if (count($miss)>0)
            return ['code'=>100, 'msg'=>sprintf('Field "%s" cannot be empty', implode(',',$miss))];

        $this->logger->debug("excel row:", $a);
        //map to table record
        $record = $this->mapRecord($a, $this->instant_table_mappings);
        $this->logger->debug("validateRecord:", $record);
        //check account
        $record['account'] = preg_replace('/\D/', '', $record['account']);  //remove non-digit
        if (preg_match('/^\d+$/', $record['account'])==false)
            return ['code'=>101, 'msg'=>'Beneficiary Account No. invalid'];
        if (preg_match('/^\d{15,19}$/', $record['account'])==false)
            return ['code'=>107, 'msg'=>'Beneficiary Account No. invalid length'];

        //check Bank Name
        if (($bank_code=$this->validateBankName($record['bank_name']))==false) {
            return ['code' => 102, 'msg' => 'Bank Name invalid'];
            //$warnings[] = ['code' => 300, 'msg' => 'Bank Name may be invalid'];
        } else {
            $record['bank_code'] = $bank_code;
        }
        //check if bank_code of GHT / GPAY available
        $banks = DB::queryFirstRow("SELECT * FROM %b where code=%d ;", self::DATABASE_TABLE_BANKS, $bank_code);
        if (is_null($banks) || (empty($banks['gpay_code']) && empty($banks['cup_code']))) {
            $this->logger->debug("No bank code for API", $banks);
            return ['code' => 102, 'msg' => 'Bank Name invalid'];
        }

        //fix branch name, e.g. 中国工商银行蚌埠张公山支行 to 蚌埠张公山支行
        if (!empty($record['bank_branch'])) {
            $record['bank_branch'] = preg_replace('/^'.$record['bank_name'].'?([\PZ\PC]*)$/u', '$1', $record['bank_branch']);
        }
        //check Province
        if (!empty($record['province']) && ($province_code=$this->validateProvince($record['province']))==false)
            return ['code'=>103, 'msg'=>'Province invalid'];
        if (isset($province_code))
            $record['province_code'] = $province_code;

        //non English name
        $record['name'] = trim($record['name']);
        if (preg_match('/^[A-Za-z\s]+$/', $record['name'])==false)
            $record['name'] = str_replace(' ','',$record['name']);
        //remove space
        if (isset($record['id_number']))
            $record['id_number'] = preg_replace('/[^0-9A-Za-z]/','', $record['id_number']);
        //check ID
        $record['id_type'] = $this->validateIdType($record['id_type']);
        if ($record['id_type']===FALSE) {
            return ['code' => 112, 'msg' => 'Invalid ID Card Type'];
            //return ['code' => 204, 'msg' => 'Invalid ID Card Type'];
        }
        //$this->logger->debug("validateIdType($type) = ".$record['id_type']);

        if (isset($record['id_type']) && $record['id_type'] == self::DEFAULT_ID_TYPE && !empty($record['id_number']) ) {
            $idchecks = $this->validateIdCard($record['id_number']);
            if ($idchecks['code']!=0)
                return $idchecks;
        }
        $idnum = (isset($record['id_number'])?$record['id_number']:'');
        if (!empty($idnum)) {
            if (!isset($this->id_pool[$idnum]))
                $this->id_pool[$idnum] = $record['name'];
            elseif ($this->id_pool[$idnum] != $record['name'])
                return ['code'=>108, 'msg'=>'Same ID Card No. used for different Name'];
        }
        //check currency
        $record['currency'] = trim(strtoupper($record['currency']));
        $record['currency'] = str_replace('RMB', 'CNY', $record['currency']);
        //if (!in_array($record['currency'], $this->validCurrencys))
        //check settle currency
        if (!in_array($record['currency'], $this->validInstantRemitCurrencys) || ! $this->validateWallet($record['currency']) || ! $this->validateSettleCurrency($record['currency']) )
            return ['code'=>104, 'msg'=>'Currency invalid'];

        if (!isset($this->currency))
            $this->currency = $record['currency'];
        elseif ($this->currency != $record['currency'])
            return ['code'=>109, 'msg'=>'All transactions must be specified in the same currency'];
        //check amount
        /*
        $record['amount'] = floatval($record['amount']);
        if ($record['amount'] == false)
            return ['code'=>105, 'msg'=>'Transaction amount invalid'];
        //truncate to x.xx, but NOT for negative number
        $record['amount'] = floor($record['amount'] * 100) / 100;
*/
        //check valid amount format, 9999.12345
        if (! preg_match('/^[0-9]+(\.[0-9]+)?$/', $record['amount']))
            return ['code'=>105, 'msg'=>'Transaction amount invalid'];
        // trim to 2 decimals, treat as string only
        if (preg_match('/^[0-9]+(\.[0-9]{1,2})?/', $record['amount'], $matches)) {
            $record['amount'] = $matches[0];
            $this->logger->debug("converted amount:", [$record['amount']]);
        }

        //not CNY, convert currency
        if ($record['currency'] != self::DEFAULT_CURRENCY) {
            $rmrate = $this->getRmRate($this->merchant_id, $time='', $record['currency'], self::DEFAULT_CURRENCY);
            $this->logger->debug("exchange rate for {$record['currency']}", [$rmrate]);

            if (! $rmrate) {
                return ['code'=>104, 'msg'=>'Currency invalid'];
            }
            $record['convert_currency'] = $record['currency'];
            $record['convert_amount'] = $record['amount'];
            $record['currency'] = self::DEFAULT_CURRENCY;
            $record['amount'] = $record['amount']/$rmrate;
            //wcRoundNumber($record['amount']);
        }

        if ($record['amount'] < 1)
            return ['code'=>106, 'msg'=>'Transaction amount must be larger than or equal to 1'];
        if ( ($check=$this->checkAmountLimit($record['amount'], $record['currency']))!==true)
            return $check;

        if (! $this->validateDuplicatedInstantRequest($record))
            return ['code'=>111, 'msg'=>'Duplicated transaction'];
        if (! in_array($record['test_trans'], [0,1]) )
            return ['code'=>114, 'msg'=>'Parameter test_trans can only be 0 or 1'];
            //return ['code'=>111, 'msg'=>'Parameter test_trans can only be 0 or 1'];
        //Parameter account_type is either empty, 0 or 1

        //if ($this->debug)  var_dump($record);
        if (count($warnings)) {
            $record['validation'] = json_encode($warnings);
        }
        $a = $record;
        return ['code'=>0, 'msg'=>'OK', 'warnings'=>$warnings ];
    }
    /*
     * Map to DB meta & set default value
     */
    private function mapRecord($a, $mappings = null) {
        $record = array();

        //fix excel column name (remove newline & extra space)
        foreach ($a as $k=>$v) {
            $meta = preg_replace(['/\s\s+/', '/\n/'], [' ', ''], $k);
            if (!isset($a[$meta]))
                $a[$meta] = $v;
        }
        $this->logger->debug("mapRecord", $a, $mappings);

        if (!is_array($mappings))
            $mappings = $this->table_mappings;
        //foreach ($this->table_mappings as $key=>$maps) {
        foreach ($mappings as $key=>$maps) {
            // 'amount'=> ['name'=>'Transaction Amount','required'=>true],
            $col = $maps['name'];
            $col = strtolower($col);

            if (isset($a[$key]) && $a[$key]!='') {
                $record[$key] = $a[$key];
            } elseif (isset($a[$col]) && $a[$col]!='') {
                $record[$key] = $a[$col];
            }

            if (isset($record[$key]) && isset($maps['max_length']) && $maps['max_length']>0)
                $record[$key] = substr($record[$key], 0, $maps['max_length']);
        }
        //set default value
        if (!isset($record['currency']))
            $record['currency'] = self::DEFAULT_CURRENCY;
        else
            $record['currency'] = strtoupper($record['currency']);

        if (isset($record['id_number']))
            $record['id_number'] = strtoupper($record['id_number']);
        if (!isset($record['id_type']) && isset($record['id_number']))
            $record['id_type'] = self::DEFAULT_ID_TYPE;

        $record = array_map('trim',$record);
        $record = array_map('unicode_trim',$record);
        //if ($this->debug)  var_dump($record);

        return $record;
    }

    public function getBank($code)
    {
        $res = DB::queryFirstRow("SELECT * FROM %b where code=%d ;", self::DATABASE_TABLE_BANKS, $code);
        if (!is_array($res))
            return false;
        return $res;
    }

    /*
    return bank code from database if bank name is valid
    */
    public function validateBankName($b) {
        $b = str_replace(' ','',trim($b));
        $b = strtoupper($b);
        //$b = str_replace(['有限责任公司','股份有限公司','信用联社','农村商业','有限公司','银行'],['','','信用社','农商','',''],$b);
        $b = str_replace(['有限责任公司', '股份公司', '股份有限公司','信用联社','农村商业','有限公司','公司','銀行','中國'],['', '','','信用社','农商','','','银行','中国'],$b);
        if (strpos($b,'中国银行')===false)
            $b = str_replace('中国','',$b);

        $kw = str_replace(['银行'],[''], $b);

        $res = DB::query("SELECT code FROM %l WHERE name=%s OR short_name=%s OR eng_name like %s OR concat( ',',tag,',') like %ss OR concat( ',',tag,',') like %ss ", self::DATABASE_TABLE_BANKS, $b, $b, $b, ",$b,", ",$kw,");
        $this->logger->debug("validateBankName($b)", [$res]);

        if (is_array($res) && count($res)==1)
            return $res[0]['code'];

        //$this->logger->debug("validateBankName($b) FAILED");
        return FALSE;
    }

    public function validateProvince($p) {
        $p = str_replace(' ','',$p);
        $p = str_replace('中国','',$p);
        $keyword = str_replace(['省','市','自治区','特别行政区'],'',$p);

        $res = DB::query("SELECT code FROM %l WHERE name=%s OR concat(',',tag,',') like %ss OR concat(',',tag,',') like %ss", self::DATABASE_TABLE_PROVINCE, $p, ",$p,", ",$keyword,");
        if (is_array($res) && count($res)==1)
            return $res[0]['code'];

        $this->logger->debug("validateProvince($p)= Invalid");
        return FALSE;
    }

    public function validateProvinceCode($p) {
        $res = DB::query("SELECT code FROM %l WHERE code=%i ", self::DATABASE_TABLE_PROVINCE, $p);
        if (is_array($res))
            return TRUE;

        return FALSE;
    }

    public function validateIdType($type) {
        $this->logger->debug("validateIdType($type)");
        /*
         * 0 (default) 	ID card
         * X	Other paper, val=99
         */
        if (empty(trim($type)))
            return self::DEFAULT_ID_TYPE;
        $type = strtoupper($type);
        $types = ['0','1','2','3','4','5','6','7','8','9','X'];

        if (!in_array($type, $types))
            return FALSE;
        if ($type=='X')
            return 99;
        return $type;
    }

    public function validateIdCard($id) {
        $id = str_replace(' ','',$id);

        if (strlen($id)==15) {
            if ($this->rid15($id))
                return ['code'=>0, 'msg'=>'OK'];
            return ['code' => 200, 'msg' => 'Invalid ID Card Number'];
        }
        //length
        if (preg_match('/^\d{17}[0-9|X]$/', $id)==false) {
            $this->logger->debug("validateIdCard($id): Invalid ID Card length");
            return ['code' => 200, 'msg' => 'Invalid ID Card Number'];
        }
            //return ['code'=>200, 'msg'=>'Invalid ID length'];
        //province code
        $province_code = substr($id,0,2);
        if (! $this->validateProvinceCode($province_code)) {
            $this->logger->debug("validateIdCard($id): Invalid province code in ID");
            return ['code' => 201, 'msg' => 'Invalid ID Card Number'];
        }
            //return ['code'=>201, 'msg'=>'Invalid province code in ID'];
        //valid birthday
        $bday = substr($id,6,8);
        if (! $this->isValidBirthday($bday)) {
            $this->logger->debug("validateIdCard($id): Invalid birthday in ID");
            return ['code'=>202, 'msg'=>'Invalid ID Card Number'];
        }
            //return ['code'=>202, 'msg'=>'Invalid birthday in ID'];
        //checksum
        if (! $this->validateIdCardChecksum($id)) {
            $this->logger->debug("validateIdCard($id): Invalid checksum in ID");
            return ['code' => 203, 'msg' => 'Invalid ID Card Number'];
        }
            //return ['code'=>203, 'msg'=>'Invalid checksum in ID'];

        return ['code'=>0, 'msg'=>'OK'];
    }

    //date: 19881104
    public function isValidBirthday($d, $min='1 year', $max='150 year') {
        if (strlen($d)!=8)
            return FALSE;

        $time = strtotime($d);
        if (!$time)
            return FALSE;
        if ($time>strtotime("-$min") || $time<strtotime("-$max"))
            return FALSE;

        return checkdate(substr($d,4,2) , substr($d,-2) , substr($d,0,4));
    }

    public function validateIdCardChecksum($id) {
        if (strlen($id)!=18)
            return FALSE;
        $m = [7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2];
        $remainders = [1,0,'X',9,8,7,6,5,4,3,2];
        $sum = 0;
        for($i=0;$i<17;$i++)
            $sum += intval($id[$i])*$m[$i];
        $r = $sum % 11;
        if ($this->debug)  print("validateIdCardChecksum($id): {$remainders[$r]}\n");
        return ($remainders[$r]==substr($id,-1));
    }

    // 15位身份证号
    // 2013年1月1日起将停止使用
    // http://www.gov.cn/flfg/2011-10/29/content_1981408.htm
    public function rid15($id) {
        if (strlen($id)!=15)
            return false;

        $pattern = '/[1-9]\d{5}(\d{2})(\d{2})(\d{2})\d{3}/';
        preg_match($pattern, $id, $matches);
        if (!$matches)
            return false;
        //var_dump($matches);
        $y = '19' . $matches[1];
        $m = $matches[2];
        $d = $matches[3];
        /*
            date = new Date(y, m-1, d);
            return (date.getFullYear()===y && date.getMonth()===m-1 && date.getDate()===d);
        */
        return $this->isValidBirthday("$y$m$d");
    }

    /*
     * Ensure currency is consistent within same batch
     */
    public function validateBatchCurrency($id, $currency='CNY'){
        if (empty($id)|| empty($currency))
            return FALSE;
        DB::query("SELECT * FROM %b WHERE batch_id=%s AND currency!=%s ;", self::DATABASE_TABLE_REMITTANCE, $id, $currency);
        if (DB::count()>0)
            return FALSE;

        return TRUE;
    }

    /*
     * No duplicate transaction within a batch
     */
    public function validateBatchDuplicate($rs, $period="-24 hours") {
        $merchantid = $this->merchant_id;

        $this->logger->debug("validateBatchDuplicate: mid=$merchantid", $rs);
        //no data, no duplicated
        if (!is_array($rs))
            return true;
        ksort($rs);
        array_walk($rs, 'trim');
        $this->logger->debug("after trim:", $rs);

        $hash = md5(implode(',', $rs));
        if (count($this->row_checksums)>0 && in_array($hash, $this->row_checksums))
            return FALSE;   //NG

        if (!empty($merchantid)) {
            //check database table
            $where = new WhereClause('and'); // create a WHERE statement of pieces joined by ANDs
            //$where->add('batch_id in (select id from %b where merchant_id= %s )', self::DATABASE_TABLE_BATCH, $merchantid);
            $where->add('batch_id IN (SELECT id from %b where merchant_id=%s AND status NOT IN %li)', self::DATABASE_TABLE_BATCH, $merchantid, [self::BATCH_STATUS_CANCELLED, self::BATCH_STATUS_DECLINED]);
            $where->add('create_time > %s', date('Y-m-d H:i:s',strtotime($period)) );
            foreach ($rs as $k=>$v) {
                $where->add("$k=%s", $v);
            }

            $res = DB::query("SELECT * FROM %b WHERE %l ;", self::DATABASE_TABLE_REMITTANCE, $where);
            if (DB::count()>0) {
                $this->logger->debug("duplicated:", $res[0]);
                return FALSE;
            }
        }
        $this->row_checksums[] = $hash;
        //OK
        return TRUE;
    }

    /*
     * Check request currency
     */
    public function validateSettleCurrency($c) {
        //check DB
        $settle = 'USD';
        $mercs = $this->getMerchantDetails($this->merchant_id);
        if (isset($mercs['settle_currency']) && !empty($mercs['settle_currency']))
            $settle = trim($mercs['settle_currency']);
        $this->settlement_currency = $settle;

        if ($c==self::DEFAULT_CURRENCY)
            return TRUE;
        return ($settle==$c);
    }
    // check if currency support wallet
    public function validateWallet($c) {
        if ($c==self::DEFAULT_CURRENCY)
            return TRUE;
        if (empty($c))
            return FALSE;

        $wallet = new MerchantWallet($this->merchant_id);
        $wallet_id = $wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $wallet_sym = $wallet->getWalletCurrency();
        $this->logger->debug("validateWallet($c), Wallet ID:$wallet_id, Currency: $wallet_sym");

        if ($wallet_sym==self::DEFAULT_CURRENCY)
            return TRUE;
        return ($c==$wallet_sym);
    }

    public function insertRemittances() {
        $this->logger->debug("insertRemittances: ".var_export($this->remittances,true));
        if (!is_array($this->remittances) || count($this->remittances)<1) {
            $this->logger->debug("No Remittance record to insert");
            return FALSE;
        }

        // get amount total, total_cny OR total_usd
        //$totals = ['CNY'=>0, 'USD'=>0];
        $totals = array_fill_keys($this->validCurrencys, 0);
        foreach ($this->remittances as $remittance) {
            $totals[$remittance['currency']]+= $remittance['amount'];
        }

        $batchs = array();
        $batchs['id'] = $bid = $this->getBatchId();
        $batchs['merchant_id'] = $this->merchant_id;
        $batchs['file1'] = $this->excel_file;
        if (is_readable($batchs['file1']))
            $batchs['file1_md5'] = md5_file($batchs['file1']);
        $batchs['file2'] = $this->pdf_file;

        $batchs['count'] = count($this->remittances);
        //record batch currency on sheet
        if (isset($this->remittances[0])) {
            $currency = $batchs['currency'] = $this->remittances[0]['currency'];
            //$currency2 = $batchs['convert_currency'] = $this->remittances[0]['convert_currency'];
        }

        $batchs['status'] = self::BATCH_STATUS_QUEUED;  //Queued
        if (!empty($this->username))
            $batchs['username'] = $this->username;
        if (!empty($this->ip))
            $batchs['ip_addr'] = $this->ip;
        if (!empty($this->settlement_date))
            $batchs['settle_time'] = $this->settlement_date;
        if (isset($totals['USD']) && $totals['USD']>0)
            $batchs['total_usd'] = $totals['USD'];
        //non US$
        elseif (isset($currency) && $currency!='CNY')
            $batchs['total_usd'] = $totals[$currency];

        if (isset($totals['CNY']) && $totals['CNY']>0)
            $batchs['total_cny'] = $totals['CNY'];
         //todo: allow override
        /*
        if ($this->isBatchExist($batchs['file1_md5']))
            return FALSE;
        */

        DB::insert(self::DATABASE_TABLE_BATCH, $batchs);
        foreach ($this->remittances as $remittance){
            $remittance['batch_id'] = $bid;
            DB::insert(self::DATABASE_TABLE_REMITTANCE, $remittance);
        }

        return $bid;
    }

    // Add single record
    public function insertSingleRemittance($bid, $data) {
        $this->logger->debug("insertSingleRemittance $bid: ".var_export($data,true));
        if (empty($bid) || !is_array($data))
            return FALSE;

        $batchs = array();
        $batchs['id'] = $bid ;//= $this->getOpenBatchId($mid);
        //$batchs['merchant_id'] = $mid;  //$this->merchant_id;
//        $batchs['count'] = count($this->remittances);

        if (!empty($this->username))
            $batchs['username'] = $this->username;
        if (!empty($this->ip))
            $batchs['ip_addr'] = $this->ip;
        if (!empty($this->settlement_date))
            $batchs['settle_time'] = $this->settlement_date;
        /*
        if (isset($totals['USD']) && $totals['USD']>0)
            $batchs['total_usd'] = $totals['USD'];
        if (isset($totals['CNY']) && $totals['CNY']>0)
            $batchs['total_cny'] = $totals['CNY'];
        */

        $data['batch_id'] = $bid;
        //$data['status'] = 1;	//default OK
        $data['status'] = 0;	// no status = null
        // Add amount total, total_cny OR total_usd
        $ttl_field = "total_".strtolower($data['currency']);
        $batchs['count'] = DB::sqleval("COALESCE(count,0)+1");
        $batchs[$ttl_field] = DB::sqleval("COALESCE($ttl_field,0) + {$data['amount']}");
        DB::update(self::DATABASE_TABLE_BATCH, $batchs, "id=%s", $bid);
        DB::insert(self::DATABASE_TABLE_REMITTANCE, $data);

        return $bid;
    }

    //insert to database
    public function insertInstantRequest($data) {
        $this->logger->debug("insertInstantRequest: ",$data);
        if ( !is_array($data))
            return FALSE;

        $data['id'] = $id = $this->newUuid();
        $data['merchant_id'] = $this->merchant_id;
        $data['type'] = 1;  //Remittance
        //target
/*
        if (!empty($this->username))
            $batchs['username'] = $this->username;
        if (!empty($this->ip))
            $batchs['ip_addr'] = $this->ip;
        if (!empty($this->settlement_date))
            $batchs['settle_time'] = $this->settlement_date;

        if ($data['test_trans']=='1') {
            //skip insert
            $this->logger->debug("skip insert for test_trans");
            return $id;
        }
*/
        //wcRoundNumber($data['amount']);
        try {
            DB::insert(self::DATABASE_TABLE_INSTANTREQ, $data);
        } catch (MeekroDBException $e) {
            $id = false;
            $this->logger->debug("Error: ",[$e->getMessage()]);
            $this->logger->debug("SQL: ",[$e->getQuery()]);
        }

        if (isset($data['status']))
            $this->addNotificationLogs($this->merchant_id, $id, $notify_type = 2);

        return $id;
    }

    //return batch amount paid by merchant, false if currency not supported
    public function getBatchPaidAmount($bid, $status=true, $currency='CNY') {
        //$fees = $this->getInstantRequestFee();
        // return ['fee'=>$local_fee, 'client_paid'=>$client_paid];
        $amount = 0;
        $batchs = $this->getBatchDetails($bid);
        if (is_array($batchs)) {
            foreach ($batchs as $logs) {
                //status
                if ($status) {
                    //failed tx
                    if (in_array($logs['tx_status'], [self::RM_STATUS_REJECTED, self::RM_STATUS_FAILED_AMENDED, self::RM_STATUS_FAILED]))
                        continue;
                }
                //$amount += ($logs['currency'] == $currency ? $logs['paid_amount'] : $logs['convert_paid_amount']);
                switch ($currency) {
                    case $logs['currency']:
                        $amount += $logs['paid_amount'];
                        break;
                    case $logs['convert_currency']:
                        $amount += $logs['convert_paid_amount'];
                        break;
                    default:
                        return false;
                }
            }
            return $amount;
        } else {
            return false;
        }
    }

    // return paid amount in CNY of remittance_log regardless status
    public function getBatchLogPaidAmountCny($bid, $logid, $currency='CNY') {
        if (empty($bid) || empty($logid))
            return false;
        $currency = strtoupper($currency);

        $sql = "SELECT * FROM %b WHERE batch_id =%s and id=%d ;";
        $r = DB::queryFirstRow($sql, self::DATABASE_TABLE_REMITTANCE, $bid, $logid);
        if (!is_array($r))
            return false;

        return ($r['currency']==$currency?$r['paid_amount']:$r['convert_paid_amount']);
    }

    public function getInstantRequest($id)
    {
        $this->logger->debug("getInstantRequest: ", [$this->merchant_id,$id]);
        if (empty($id))
            return FALSE;

        //$r = DB::queryFirstRow("SELECT * FROM %b WHERE merchant_id=%s AND id=%s", self::DATABASE_TABLE_INSTANTREQ, $this->merchant_id, $id);
        if (empty($this->merchant_id)) {
            $sql = "SELECT l.*, cup_code as ght_code, gpay_code FROM %b l, %b b WHERE 1=1 and l.id = %s and l.bank_code = b.code ;";
            $r = DB::queryFirstRow($sql, self::DATABASE_TABLE_INSTANTREQ, self::DATABASE_TABLE_BANKS, $id);
        } else {
            $sql = "SELECT l.*, cup_code as ght_code, gpay_code FROM %b l, %b b WHERE l.merchant_id = %s and l.id = %s and l.bank_code = b.code ;";
            $r = DB::queryFirstRow($sql, self::DATABASE_TABLE_INSTANTREQ, self::DATABASE_TABLE_BANKS, $this->merchant_id, $id);
        }

        if (!is_array($r))
            return false;
        //status, gross_amount_cny
        if (!isset($r['status_name']))
            $r['status_name'] = self::getInsReqStatus($r['status']);
        if (!isset($r['beneficiary_name']))
            $r['beneficiary_name'] = $r['name'];

        //[TODO] gpay_code, ght_code, merchant_name ...
        return $r;
    }

    public function getInstantRequestApiLog($id)
    {
        $this->logger->debug("getInstantRequestApiLog: ", [$this->merchant_id, $id]);
        $res = DB::query("select * from %b where req_id=%s order by create_time DESC;", self::DATABASE_TABLE_API_LOG, $id);
        return $res;
    }

    //return fee of Wecollect
    public function getInstantRequestFee($cny=0, $rate=0) {
        $local_fee = $this->getLocalProcessorFee(self::DEFAULT_LOCAL_REMITTANCE_TARGET, $this->merchant_id);
        if ($local_fee===false) {
            //default fee
            $this->logger->debug("use DEFAULT_LOCAL_REMITTANCE_FEE_CNY", [$this->merchant_id]);
            $local_fee = self::DEFAULT_LOCAL_REMITTANCE_FEE_CNY;
        }
        $merc = $this->getMerchantDetails($this->merchant_id);
        $client_paid = (isset($merc['remittance_fee_type']) && $merc['remittance_fee_type']==2); //client bear the fee instead of merchant

        return ['fee'=>$local_fee, 'client_paid'=>$client_paid];
    }

    /*
     * update fee on table & return paid_amount from merchant
     * reset = true, clear paid_amount & fee for failed transaction
     */
    public function setInstantRequestFee($txid, $toCurrency='USD', $feeCurrency='CNY', $reset=false) {
/*
    //update fee on table & return paid_amount from merchant
    public function setInstantRequestFee($txid, $toCurrency='USD', $feeCurrency='CNY') {
*/
        //convert_currency
        $this->logger->debug("setInstantRequestFee($txid, $toCurrency, $feeCurrency)");

        if (empty($txid))
            return false;
        $r = DB::queryFirstRow("SELECT * FROM %b WHERE id=%s", self::DATABASE_TABLE_INSTANTREQ, $txid);
        if (!is_array($r))
            return false;

        $updates = array();

        if ($reset) {
            $updates['convert_rate'] = $updates['convert_currency'] = $updates['convert_amount'] = $updates['fee_cny'] = $updates['convert_fee'] = $updates['gross_amount_cny']
                = $updates['paid_amount'] = $updates['convert_paid_amount'] = null;
        } else {
            //not reset
            $toCurrency = strtoupper(trim($toCurrency));
            if (empty($toCurrency) || $toCurrency == 'CNY') {
                //USD for reference currency
                //$toCurrency = 'USD';
                //$toCurrency = $this->getConvertCurrency($r['currency'], $feeCurrency);
                //Convert to merchant settle currency
                $toCurrency = $this->getConvertCurrency($r['currency'], $this->settlement_currency);
                $this->logger->debug("Convert to: $toCurrency", ['settlement_currency'=>$this->settlement_currency]);
            }

            $amt = $r['amount'];
            $updates['convert_rate'] = $rate = $this->getRmRate($this->merchant_id, $time = '', 'CNY', $toCurrency);
            if (!$rate)
                return false;
            $updates['convert_currency'] = $toCurrency;
            $updates['convert_amount'] = ($amt / $rate);
            //wcRoundNumber($updates['convert_amount']);

            $fees = $this->getInstantRequestFee();
            $updates['fee_cny'] = $fees['fee'];
            $updates['convert_fee'] = ($updates['fee_cny'] / $rate);
            //wcRoundNumber($updates['convert_fee']);

            if ($fees['client_paid']) {
                //client bear the fee
                $updates['gross_amount_cny'] = $amt - $updates['fee_cny'];
                $updates['paid_amount'] = $amt;
            } else {
                $updates['gross_amount_cny'] = $amt;
                $updates['paid_amount'] = $amt + $updates['fee_cny'];
            }
            $updates['convert_paid_amount'] = ($updates['paid_amount'] / $rate);

            /*
            wcRoundNumber($updates['gross_amount_cny']);
            wcRoundNumber($updates['paid_amount']);
            wcRoundNumber($updates['convert_paid_amount']);
            */
            //check if no amount to send (when fee >= request amount)
            if ($updates['gross_amount_cny'] <= 0) {
                $this->logger->debug("gross_amount_cny is zero, cannot cover fee", [$updates]);
                return false;
            }
        }   //not reset end

        try {
            DB::update(self::DATABASE_TABLE_INSTANTREQ, $updates, "id=%s", $txid);
            //CNY
            //return ($feeCurrency=='CNY'?$updates['paid_amount']:$updates['convert_paid_amount']);
            //fee deduction support only CNY/USD or input currency
            if ($feeCurrency=='CNY')
                return $updates['paid_amount'];
            if ($feeCurrency==$updates['convert_currency'])
                return $updates['convert_paid_amount'];
            //fee not available
            return false;
        } catch (MeekroDBException $e) {
            $this->logger->debug("Error: ",[$e->getMessage()]);
            $this->logger->debug("SQL: ",[$e->getQuery()]);
            return false;
        }
    }
    //TODO: check valid status
    public function setInstantRequestStatus($id, $status, $others=null) {
        if (empty($id))
            return false;
        if (!in_array($status, [self::IR_STATUS_PROCESSING, self::IR_STATUS_OK, self::IR_STATUS_FAILED, self::IR_STATUS_REJECTED, self::IR_STATUS_BLOCKED]))
            return false;

        if ($status < self::IR_STATUS_PENDING) {
            //failed transaction , reset fee to 0
            $this->setInstantRequestFee($id, $toCurrency='USD', 'CNY', true);
        }

        $updates=['status'=> $status, 'update_time' => $this->getTimeNow()];
        if (is_array($others))
            $updates = array_merge($updates, $others);
        DB::update(self::DATABASE_TABLE_INSTANTREQ, $updates, "id = %s", $id);
        $this->logger->debug("setInstantRequestStatus($id, $status) DONE");
        // send callback to merchant
        $this->addNotificationLogs($this->merchant_id, $id, $notify_type = 2);

        return true;
    }

    public function adminUpdateInstantRequestStatus($id, $status, $update_balance=true) {
        $reqs = $this->getInstantRequest($id);
        if (!is_array($reqs))
            return false;

        $this->logger->debug("adminUpdateInstantRequestStatus($id, $status)",  $reqs);
        $cstatus = $reqs['status'];
        //check if status valid
        if (! in_array($cstatus, [self::IR_STATUS_PROCESSING, self::IR_STATUS_OK, self::IR_STATUS_FAILED]) || $cstatus==$status || !in_array($status, [self::IR_STATUS_OK, self::IR_STATUS_FAILED]))
            return false;
        //check test_trans
        if ($reqs['test_trans']=='1')
            $update_balance = false;

        //if ($this->setInstantRequestStatus($id, $status) && $update_balance) {
        if ($update_balance) {
            $wallet = new MerchantWallet($reqs['merchant_id']);
            $wallet_id = $wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
            $wallet_sym = $wallet->getWalletCurrency();
            $this->logger->debug("Wallet ID:$wallet_id, Currency: $wallet_sym");

            //$paid_amount = $reqs['paid_amount'];
            //$paid_amount = ($wallet_sym=='CNY'?$reqs['paid_amount']:$reqs['convert_paid_amount']);
            $paid_amount = null;
            if ($wallet_sym==$reqs['currency'])
                $paid_amount = $reqs['paid_amount'];
            elseif ($wallet_sym==$reqs['convert_currency'])
                $paid_amount = $reqs['convert_paid_amount'];

            /*
            if ($paid_amount==0)
                return true;
*/
            if (!isset($paid_amount) || $paid_amount==0)
                return false;
            //[TODO] $wallet->setUser
            if (isset($paid_amount) && $paid_amount!=0) {
                $dsc = 'Update status';
                $wallet_status = false;
                //Current status
                switch ($cstatus) {
                    case self::IR_STATUS_PROCESSING:
                        if ($status == self::IR_STATUS_FAILED) {
                            //refund
                            //$wallet_status = $wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_ADMIN_UPDATE, $dsc, $id);
                            $wallet_status = $wallet->revokeTransaction(MerchantWallet::TYPE_INSTANT_REMITTANCE, $id);
                        } elseif ($status == self::IR_STATUS_OK) {
                            //OK now, deduct
                            $wallet_status = $wallet->addTransaction("-$paid_amount", MerchantWallet::TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT, $dsc, $id);
                        }
                        break;
                    case self::IR_STATUS_OK:
                        //Failed now, refund
                        //$wallet_status = $wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_ADMIN_UPDATE, $dsc, $id);
                        if ($wallet->revokeTransaction(MerchantWallet::TYPE_INSTANT_REMITTANCE, $id)) {
                            $this->logger->debug("revokeTransaction OK", [$id]);
                        } else {
                            //No transaction before
                        }
                        break;
                    case self::IR_STATUS_FAILED:
                        //OK now, deduct
                        //$wallet_status = $wallet->addTransaction("-$paid_amount", MerchantWallet::TYPE_ADMIN_UPDATE, $dsc, $id);
                        $wallet_status = $wallet->addTransaction("-$paid_amount", MerchantWallet::TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT, $dsc, $id);
                        break;
                    default:
                        //$wallet_status = false;
                        break;
                }
                $this->logger->debug("wallet status:", ['status' => $wallet_status, 'paid_amount' => $paid_amount]);
            }
        }
        $this->setInstantRequestStatus($id, $status);

        return true;
    }

    // check duplicated instant request
    public function validateDuplicatedInstantRequest($rs, $period="-24 hours") {
        $merchantid = $this->merchant_id;

        $this->logger->debug("validateDuplicatedInstantRequest: mid=$merchantid", $rs);
        //no data, no duplicated
        if (!is_array($rs))
            return true;
        //remove dynamic fields
        unset($rs['convert_rate'], $rs['convert_amount'], $rs['convert_fee'], $rs['convert_paid_amount'], $rs['status']);
        ksort($rs);
        array_walk($rs, 'trim');
        $this->logger->debug("after trim:", $rs);

        if (!empty($merchantid)) {
            //check database table
            $where = new WhereClause('and'); // create a WHERE statement of pieces joined by ANDs
            $where->add('merchant_id = %s ', $merchantid);
            $where->add('create_time > %s', date('Y-m-d H:i:s',strtotime($period)) );
            // check only passed / pending request, & avoid redo failed
            $where->add('(status >= %d OR status = %d)', self::IR_STATUS_PENDING, self::IR_STATUS_FAILED);
            foreach ($rs as $k=>$v) {
                $where->add("$k=%s", $v);
            }

            $res = DB::query("SELECT * FROM %b WHERE %l ;", self::DATABASE_TABLE_INSTANTREQ, $where);
            if (DB::count()>0) {
                $this->logger->debug("duplicated:", $res[0]);
                return FALSE;
            }
        }
        //OK
        return TRUE;
    }

    //4.3	Velocity and Fraud Filter Checking
    public function validateInstantRequestFilters($txid){
        $this->logger->debug("validateInstantRequestFilters($txid)");

        if (!$txid)
            return false;
        $r = DB::queryFirstRow("SELECT * FROM %b WHERE id=%s", self::DATABASE_TABLE_INSTANTREQ, $txid);
        if (!is_array($r))
            return false;

        $flags = array();
        $filters = DB::query("SELECT * FROM %b WHERE status=%d and (COALESCE(merchant_id,'')='' OR merchant_id=%s) ;", self::DATABASE_TABLE_FILTER, 1, $this->merchant_id);
        foreach ($filters as $filter) {
            $this->logger->debug("validateInstantRequestFilters:", $filter);
            //rule_type, action, isblacklist
            if ($filter['isblacklist']>0) {
                $hit = $this->checkBlacklistFilter($r, $filter);
            } else {
                // Velocity filter (Block or Flag)
                $hit = $this->checkVelocityFilter($r, $filter);
            }
            //filter matches
            if ($hit) {
                $checks = ['code'=>$filter['code'], 'msg'=>$filter['dsc'], 'id'=>$filter['id']];
                if ($filter['action'] != 'flag')    //block
                    return $checks;
                $flags[] = $checks;
            }
        }
        //save flag to db
        if (count($flags)) {
            DB::update(self::DATABASE_TABLE_INSTANTREQ, ['filter_flag'=>1, 'filter_remarks'=>json_encode($flags)], "id = %s", $txid);
        }
        //OK
        return true;
    }

    public function validateBatchFilters($bid) {
        $ferrors = null;
        $logs = DB::query("SELECT * FROM %b WHERE batch_id=%s ORDER BY id;", self::DATABASE_TABLE_REMITTANCE, $bid);
        if (!is_array($logs))
            return true;

        foreach ($logs as $row=>$log) {
            $id = $log['id'];
            $checks = $this->validateBatchLogFilters($this->merchant_id, $id);
            if (is_array($checks)) {
                $xrow = (isset($this->filled_excel_rows[$row])?$this->filled_excel_rows[$row]:$row+1);
                //['code'=>$filter['code'], 'msg'=>$filter['dsc']];
                // ['row'=>$row+1, 'error_code'=>$valids['code'], 'error_msg'=>$valids['msg']];
                //$ferrors[] = ['id'=>$id, 'row'=>$row+1, 'error_code'=>$checks['code'], 'error_msg'=>$checks['msg']];
                $ferrors[] = ['row'=>$xrow, 'error_code'=>$checks['code'], 'error_msg'=>$checks['msg']];
                //update status
                $this->setBatchLogStatus($bid, $id, self::RM_STATUS_FAILED);
            }
        }

        //update batch total
        $this->updateBatch($bid, $rate_update=FALSE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=TRUE);
        //if all remittance failed
        if (count($logs)==count($ferrors)) {
            $this->setBatchStatus($bid, self::BATCH_STATUS_DECLINED);
            return $ferrors;
        }

        //Batch OK
        return true;
    }

    public function validateBatchLogFilters($merchant_id, $logid){
        $this->logger->debug("validateBatchLogFilters($logid)");

        if (empty($logid))
            return false;
        $r = DB::queryFirstRow("SELECT * FROM %b WHERE id=%s", self::DATABASE_TABLE_REMITTANCE, $logid);
        if (!is_array($r))
            return false;

        //convert fields
        if ($r['currency']!='CNY') {
            $rate = $r['convert_rate'] = $this->getRmRate($this->merchant_id, '', 'CNY', $r['currency']);
            $currency = $r['convert_currency'];
            $amount = $r['convert_amount'];
            $r['convert_currency'] = $r['currency'];
            $r['convert_amount'] = $r['amount'];
            if (!empty($currency)) {
                $r['currency'] = $currency;
                $r['amount'] = $amount;
            } else {
                $r['currency'] = 'CNY';
                $r['amount'] = $r['amount']*$rate;
            }
        }
        if (!isset($r['name']))
            $r['name'] = $r['beneficiary_name'];

        $flags = array();
        //find all enabled filters
        $filters = DB::query("SELECT * FROM %b WHERE status=%d and (COALESCE(merchant_id,'')='' OR merchant_id=%s) ;", self::DATABASE_TABLE_FILTER, 1, $merchant_id);
        foreach ($filters as $filter) {
            $this->logger->debug("validateBatchLogFilters:", $filter);
            //rule_type, action, isblacklist
            if ($filter['isblacklist']>0) {
                $hit = $this->checkBlacklistFilter($r, $filter);
            } else {
                // Velocity filter (Block or Flag)
                $hit = $this->checkBatchVelocityFilter($r, $filter);
            }
            $flag = ($filter['action']!='flag'?0:1);
            //filter matches
            if ($hit) {
                $checks = ['code'=>$filter['code'], 'msg'=>$filter['dsc'], 'flag'=>$flag, 'id'=>$filter['id']];
                //if ($filter['action'] != 'flag')
                if (!$flag){
                    DB::update(self::DATABASE_TABLE_REMITTANCE, ['validation'=>json_encode($checks)], "id = %s", $logid);
                    //block
                    return $checks;
                }
                $flags[] = $checks;
            }
        }
        //save flag to db
        if (count($flags)) {
            DB::update(self::DATABASE_TABLE_REMITTANCE, ['validation'=>json_encode($flags)], "id = %s", $logid);
        }
        //OK passed
        return true;
    }

    /*
     * Add new Blacklist Filter to database
     */
    public function addBlacklistFilter($field, $value, $mid='') {
        $codes = ['401'=>'account', '402'=>'id_number'];
        $descs = [
            '401'=>['name'=>'Account number', 'dsc'=>'Beneficiary account number is blacklisted'],
            '402'=>['name'=>'ID card number', 'dsc'=>'Beneficiary ID card number is blacklisted'],
        ];

        if (empty($field) || empty($value))
            return false;
        $field = strtolower($field);
        $value = strtoupper($value);    //ID card

        if (!in_array($field, $codes))
            return false;

        if ($this->existBlacklistFilter($field, $value, $mid))
            throw new RemittanceException('Duplicated Filter');

        $code = array_search($field, $codes);
        $filters = ['code'=>$code, 'name'=>$descs[$code]['name'], 'dsc'=>$descs[$code]['dsc'], 'rule_type'=>'or', 'action'=>'block', 'isblacklist'=>1, 'status'=>1];
        $filters['id'] = 0; // auto incrementing column
        //TODO check merchant id
        if (!empty($mid))
            $filters['merchant_id'] = trim($mid);

        DB::insert(self::DATABASE_TABLE_FILTER, $filters);

        $dbid = DB::insertId();
        $rules = ['filter_id'=>$dbid, 'column'=>$field, 'val'=>$value, 'condition'=>'='];
        DB::insert(self::DATABASE_TABLE_FILTER_RULE, $rules);

        $this->logger->debug("addBlacklistFilter($field, $value, $mid)", $filters, $rules);
        return true;
    }

    public function getBlacklistFilters() {
        //SELECT f.*, r.column, r.val FROM `remittance_filter` f join `remittance_filter_rule` r on (f.id=r.filter_id) where code in ('401', '402')
        $sql = "SELECT f.*, r.column, r.val, COALESCE (m.name, 'All') as merchant_name FROM `remittance_filter` f join `remittance_filter_rule` r on (f.id=r.filter_id) left join merchants m on (m.id=f.merchant_id) 
                where code in %ls " ;
        $res =  DB::query($sql, ['401', '402']);
        return $res;
    }

    public function existBlacklistFilter($field, $value, $mid='') {
        $codes = ['401'=>'account', '402'=>'id_number'];

        if (empty($field) || empty($value))
            return false;
        $field = strtolower($field);
        $value = strtoupper($value);    //ID card

        if (!in_array($field, $codes))
            return false;

        $code = array_search($field, $codes);

        $where = new WhereClause('and');
        $where->add('code=%s', $code);
        $where->add('r.column=%s', $field);
        $where->add('r.val=%s', $value);
        if (!empty($mid))
            $where->add('merchant_id=%s', $mid);
        else
            $where->add("COALESCE(merchant_id,'')=''");

        $sql = "SELECT f.* FROM %b f join %b r on (f.id=r.filter_id) where %l ;" ;

        $res = DB::query($sql, self::DATABASE_TABLE_FILTER, self::DATABASE_TABLE_FILTER_RULE, $where);
        return (count($res)>0);
    }

    public function isExistFilter($fields, $mid='') {
        if (! is_array($fields))
            return false;

        /*
         * SQL: SELECT * FROM `remittance_filter` f left join remittance_filter_rule r on ( filter_id=f.id) where f.id=60 
         */
        $where = new WhereClause('and');
        $rule_cnt = 0;
        if (isset($fields['remittance_filter_rule'])) {
            $rule_cnt = count($fields['remittance_filter_rule']);
            $orclause = $where->addClause('or');

            foreach ($fields['remittance_filter_rule'] as $k=>$rules) {
                $subclause = $orclause->addClause('and');
                foreach ($rules as $f=>$v) {
                    if (is_null($v))
                        $subclause->add("r.$f IS NULL");
                    else
                        $subclause->add("r.$f=%s", $v);
                }
                $subclause = null;
            }
            unset($fields['remittance_filter_rule']);
        }
        $this->logger->debug("rule_cnt:", [$mid, $rule_cnt]);

        if (isset($fields['merchant_id']))
            $mid = $fields['merchant_id'];

        //remove useless fields
        $checks = ['code', 'count_limit', 'count_limit_type', 'amount_limit', 'amount_limit_type', 'period'];
        $fields = array_intersect_key($fields, array_fill_keys($checks, 1));
        foreach ($fields as $k=>$v) {
            if (is_null($v))
                $where->add("$k IS NULL");
            else
                $where->add("$k=%s", $v);
        }

        $this->logger->debug("isExistFilter, fields:", $fields);

        if (!empty($mid))
            $where->add('merchant_id=%s', $mid);
        else
            $where->add("COALESCE(merchant_id,'')=''");

        $sql = "SELECT f.* FROM %b f left join %b r on (f.id=r.filter_id) where %l ;" ;
        $res = DB::query($sql, self::DATABASE_TABLE_FILTER, self::DATABASE_TABLE_FILTER_RULE, $where);
        /*
        $sql = "SELECT f.* FROM %b f left join %b r on (f.id=r.filter_id) where 1=1 ;" ;
        $res = DB::query($sql, self::DATABASE_TABLE_FILTER, self::DATABASE_TABLE_FILTER_RULE);
*/
        $count = count($res);
        $this->logger->debug("isExistFilter, where:", [$where]);
        $this->logger->debug("isExistFilter, result:", [$res, $count]);
        //found result maybe more than rules if duplicates already exists
        if ($rule_cnt>1)
            return ($count>=$rule_cnt);
            //return (count($res)==$rule_cnt);
        return ($count>0);
    }

    static public function getBlacklistFilterName($t)
    {
        $names = ['account'=>'Account No.', 'id_number'=>'ID Card No.', 'name'=>'Name', 'bank_name'=>'Bank Name'];
        $t = trim(strtolower($t));

        if (isset($names[$t]))
            return $names[$t];
        return 'N/A';
    }

    /*
     * From period in seconds integer value to X Day/Hour text
     */
    static public function fromSecondsToPeriodText($i) {
        $day1 = 86400; //24 hours
        $hour1 = 3600;

        $i = intval($i);
        if ($i<=0)
            return false;
        if ($i>=$day1)
            return sprintf("%d Day", round($i/$day1));
        if ($i>=$hour1)
            return sprintf("%d Hour", round($i/$hour1));
        return false;
    }

    /*
     * return true if filter matches
     */
    private function checkBlacklistFilter($rs, $f) {
        $fid = $f['id'];
        if ($f['rule_type']=='or') {
            $oper = '||';
            $match = false;
        } else {    //and
            $oper = '&&';
            $match = true;
        }
        $rules = DB::query("SELECT * FROM %b WHERE filter_id=%d ;", self::DATABASE_TABLE_FILTER_RULE, $fid);
        if (count($rules)==0)
            return false;

        foreach ($rules as $rule) {
            $this->logger->debug("checkBlacklistFilter rule:", $rule);

            $col = trim($rule['column']);
            $cond = trim($rule['condition']);
            if ($cond=='=')
                $cond='==';
            $rval = $rule['val'];
            if (!isset($rs[$col]))
                continue;
            $val = $rs[$col];
            //assign value for eval
            $match = ($match?1:0);
            $this->logger->debug("\$match = ($match $oper (\"$val\" $cond \"$rval\"));");

            eval("\$match = ($match $oper (\"$val\" $cond \"$rval\"));");
            $this->logger->debug("match rule:", ['return'=>$match, 'oper'=>$oper, 'cond'=>$cond]);
        }

        return $match;
    }

    private function checkVelocityFilter($rs, $f) {
        $this->logger->debug("checkVelocityFilter:", $f);

        $fid = $f['id'];
        if ($f['rule_type']=='or') {
            $oper = 'or';
        } else {    //and
            $oper = 'and';
        }
        $rules = DB::query("SELECT * FROM %b WHERE filter_id=%d ;", self::DATABASE_TABLE_FILTER_RULE, $fid);
        /*
        if (count($rules)==0)
            return false;
*/
        //prepare query
        //$where = new WhereClause($oper);
        $where = new WhereClause('and');
        $where->add('merchant_id = %s ', $this->merchant_id);
        $where->add('test_trans = 0 ');

        if (!empty($f['period']))
            $where->add('create_time > %s', date('Y-m-d H:i:s',strtotime("-{$f['period']} second")) );

        // Sum / Count limit filter can have no rule
        if (count($rules)>0) {
            $subclause = $where->addClause('and');
            foreach ($rules as $rule) {
                $this->logger->debug("checkVelocityFilter rule:", $rule);
                $sclause = $subclause->addClause($oper);
                $col = trim($rule['column']);
                $cond = trim($rule['condition']);
                $rval = $rule['val'];
                if (!isset($rs[$col]))
                    continue;

                $val = $rs[$col];
                if ($rval == '')
                    $sclause->add("$col $cond %s", $val);
                    //$where->add("$col $cond %s", $val);
                else
                    $sclause->add("$col $cond %s", $rval);
                    //$where->add("$col $cond %s", $rval);
                $sclause = null;
            }
        }

            $this->logger->debug("where:", [$where]);
            //IR_STATUS_PENDING = 0
            //check if current tx matches
            $tx = DB::queryFirstRow("SELECT id FROM %b WHERE status >= 0 AND ( %l ) AND id = %s;", self::DATABASE_TABLE_INSTANTREQ, $where, $rs['id']);
            if (count($tx) == 0)
                return false;
            $this->logger->debug("Current TX matches:", $tx);

        // Check Sum / Count limit filter
        if (isset($f['count_limit_type']) || isset($f['amount_limit_type'])) {
            $res = DB::queryFirstRow("SELECT count(*) as count, SUM(amount) as sum FROM %b WHERE status >= 0 AND ( %l ) ;", self::DATABASE_TABLE_INSTANTREQ, $where);
            if (is_array($res)) {
                $match = false;
                $this->logger->debug("query:", $res);
                //if ($f['count_limit']!='' && $res['count']>$f['count_limit'])
                if ($f['count_limit'] != '') {
                    $cond = (empty($f['count_limit_type']) ? '>' : $f['count_limit_type']);
                    eval("\$match = ({$res['count']} $cond {$f['count_limit']});");
                    if ($match) {
                        $this->logger->debug("Hit Rate Limit Filter: {$f['count_limit']}");
                        return true;
                    }
                }
                //if ($f['amount_limit']!='' && $res['sum']>$f['amount_limit'])
                if ($f['amount_limit'] != '') {
                    $cond = (empty($f['amount_limit_type']) ? '>' : $f['amount_limit_type']);
                    eval("\$match = ({$res['sum']} $cond {$f['amount_limit']});");
                    if ($match) {
                        $this->logger->debug("Hit Sum Limit Filter: {$f['amount_limit']}");
                        return true;
                    }
                }
            }
        }
        // Check Sum / Count limit filter END

        return false;
    }

    private function checkBatchVelocityFilter($rs, $f) {
        $this->logger->debug("checkBatchVelocityFilter:", $f);
        $this->logger->debug("rm:", $rs);

        $fid = $f['id'];
        if ($f['rule_type']=='or') {
            $oper = 'or';
        } else {    //and
            $oper = 'and';
        }
        $rules = DB::query("SELECT * FROM %b WHERE filter_id=%d ;", self::DATABASE_TABLE_FILTER_RULE, $fid);
        /*
        if (count($rules)==0)
            return false;
*/
        //prepare query
        /*
        $where = new WhereClause($oper);
        $where->add('batch_id IN (SELECT id from %b where merchant_id=%s )', self::DATABASE_TABLE_BATCH, $this->merchant_id);
        */
        $where = new WhereClause('and');
        //excluded Cancelled batchs
        $where->add('batch_id IN (SELECT id from %b where merchant_id=%s AND status NOT IN %li)', self::DATABASE_TABLE_BATCH, $this->merchant_id, [self::BATCH_STATUS_CANCELLED, self::BATCH_STATUS_DECLINED]);

        if (!empty($f['period']))
            $where->add('create_time > %s', date('Y-m-d H:i:s',strtotime("-{$f['period']} second")) );

        // Sum / Count limit filter can have no rule
        if (count($rules)>0) {
            $subclause = $where->addClause('and');
            foreach ($rules as $rule) {
                $this->logger->debug("checkVelocityFilter rule:", $rule);
                $sclause = $subclause->addClause($oper);

                $col = trim($rule['column']);
                //table remittance_log
                if ($col=='name')
                    $col = 'beneficiary_name';
                $cond = trim($rule['condition']);
                $rval = $rule['val'];
                if (!isset($rs[$col]))
                    continue;

                $val = $rs[$col];
                if ($rval == '')
                    $sclause->add("$col $cond %s", $val);
                    //$where->add("$col $cond %s", $val);
                else
                    $sclause->add("$col $cond %s", $rval);
                    //$where->add("$col $cond %s", $rval);
                $sclause = null;
            }
        }

        $this->logger->debug("where:", [$where]);
        //IR_STATUS_PENDING = 0
        //check if current tx matches
        $tx = DB::queryFirstRow("SELECT id FROM %b WHERE status >= 0 AND ( %l ) AND id = %s;", self::DATABASE_TABLE_REMITTANCE, $where, $rs['id']);
        if (count($tx) == 0)
            return false;
        $this->logger->debug("Current TX matches:", $tx);

        // Check Sum / Count limit filter
        if (isset($f['count_limit_type']) || isset($f['amount_limit_type'])) {
            $res = DB::queryFirstRow("SELECT count(*) as count, SUM(amount) as sum FROM %b WHERE status >= 0 AND ( %l ) ;", self::DATABASE_TABLE_REMITTANCE, $where);
            if (is_array($res)) {
                $match = false;
                $this->logger->debug("query:", $res);
                //if ($f['count_limit']!='' && $res['count']>$f['count_limit'])
                if ($f['count_limit'] != '') {
                    $cond = (empty($f['count_limit_type']) ? '>' : $f['count_limit_type']);
                    eval("\$match = ({$res['count']} $cond {$f['count_limit']});");
                    if ($match) {
                        $this->logger->debug("Hit Rate Limit Filter: {$f['count_limit']}");
                        return true;
                    }
                }
                //if ($f['amount_limit']!='' && $res['sum']>$f['amount_limit'])
                if ($f['amount_limit'] != '') {
                    $cond = (empty($f['amount_limit_type']) ? '>' : $f['amount_limit_type']);
                    eval("\$match = ({$res['sum']} $cond {$f['amount_limit']});");
                    if ($match) {
                        $this->logger->debug("Hit Sum Limit Filter: {$f['amount_limit']}");
                        return true;
                    }
                }
            }
        }
        // Check Sum / Count limit filter END

        return false;
    }

    public function isBatchExist($md5) {
        if (empty($md5))
            return FALSE;

        $res = DB::query("SELECT * FROM %b WHERE file1_md5=%s", self::DATABASE_TABLE_BATCH, $md5);
        if (is_array($res) && count($res))
            return $res[0]['id'];
        return FALSE;
    }

    //failed=-1, rejected=-9, Failed (amended)=-2
    public function isValidRemittance($r) {
        if (isset($r['tx_status']) && in_array($r['tx_status'],[-1,-2,-9]))
            return FALSE;
        //$this->logger->debug("{$r['id']}: {$r['tx_status']}->{$r['tx_status_name']}");
        if (isset($r['tx_status_name']) && stripos($r['tx_status_name'],'fail')!==false)
            return FALSE;
        return true;
    }
    //check if set ok=1 / failed=-1
    public function isSetRemittance($r) {
        if (isset($r['tx_status']) && in_array($r['tx_status'],[-1,1]))
            return TRUE;
        return FALSE;
    }

    public function isProcessorTargetApi($t) {
        $name = self::getTargetName($t);
        return (strpos($name, 'API')!==false);
    }

    /*
     * return bank code for Gpay/GHT API if available in DB
     */
    public function getProcessorApiBankCode($code, $p) {
        if (empty($code) ||  empty($p))
            return false;
        $p = strtolower($p);
        //gpay_code, cup_code
        $res = DB::queryFirstRow("SELECT * FROM %b where code=%d ;", self::DATABASE_TABLE_BANKS, $code);
        if (is_null($res))
            return false;

        $this->logger->debug("getProcessorApiBankCode($code, $p)", $res);
        $key = (stripos($p, 'ght')!==false?'cup_code':'gpay_code');
        if (isset($res[$key]) && !empty($res[$key]))
            return $res[$key];
        return false;
    }

    /*
     * Decode API response & return array
     */
    public function getProcessorApiResponse($p, $response)
    {
        if (empty($response) || empty($p))
            return false;
        switch(strtolower($p)) {
            case 'ght':
                //$response = iconv('GBK', 'UTF-8', $response);
                $response = str_replace(' encoding="GBK"','',$response);
                $xml = @simplexml_load_string($response);

                //$this->logger->debug("getProcessorApiResponse", $xml);
                //$this->logger->debug("getProcessorApiResponse", (array)$xml);
                if ($xml) {
                    $array = @json_decode(@json_encode($xml), true);
                    //$array = (array)$xml->BODY;
                    $this->logger->debug("getProcessorApiResponse", $array);
                    //return $xml;
                    if (isset($array['BODY']['RET_DETAILS']['RET_DETAIL']))
                        return $array['BODY']['RET_DETAILS']['RET_DETAIL'];
                    if (isset($array['INFO']['ERR_MSG']))
                        return $array['INFO'];
                }
                break;
            case 'gpay':
                parse_str($response, $array);
                return $array;
            default:
                return null;
        }
        return false;
    }

    /*
     * Return processor latest message for Instant Req
     */
    public function getProcessorApiResponseMessage($id)
    {
        if (empty($id))
            return null;

        $logs = $this->getInstantRequestApiLog($id);
        if (! count($logs))
            return null;

        $this->logger->debug("getProcessorApiResponseMessage($id)", $logs);
        $log = $logs[0];
        // new table column
        if (!empty($log['return_msg']))
            return $log['return_msg'];

        $response  = (empty($log['callback'])?$log['response']:$log['callback']);
        $data = $this->getProcessorApiResponse($log['processor'], $response);

        if (isset($data['decodeMsg']))
            return $data['decodeMsg'];
        if (isset($data['ERR_MSG']))
            return $data['ERR_MSG'];

        return null;
    }

    public function getMerchantDetails($id)
    {
        if (empty($id))
            return FALSE;

        $res = DB::queryFirstRow("SELECT * FROM %b WHERE id=%s ;",
            'merchants', $id);

        if (!is_array($res) || !count($res))
            return FALSE;

        $this->logger->debug("getMerchantDetails($id)");
        return $res;
    }

    public function getBatchDetails($id, $allRec=true) {
        if (empty($id))
            return FALSE;
/*
        $res = DB::query("
SELECT l.*,b.*,m.name as merchant_name, l.id as id, l.status as tx_status, m.remittance_symbol, m.remittance_fee, m.remittance_fee_type, m.remittance_min_fee,
            a.email as auth_email, a.authorized as auth_time, a.ip_addr as auth_ip
            FROM %b l, %b b LEFT JOIN merchants m ON (b.merchant_id=m.id)
            LEFT JOIN (select * from %b ra where ra.batch_id=%s AND ra.active=2 order by authorized desc limit 1 ) a on (a.batch_id=b.id)
            WHERE b.id=l.batch_id AND b.id = %s ;",
            self::DATABASE_TABLE_REMITTANCE, self::DATABASE_TABLE_BATCH, self::DATABASE_TABLE_AUTHORIZATION, $id, $id);
*/

//todo: get latest API rec.

        $res = DB::query("
SELECT l.*, batch.*, l.id as id, l.status as tx_status, batch.status as status, api.id as api_log_intid from (
            SELECT m.name as merchant_name, m.remittance_symbol, m.remittance_fee, m.remittance_fee_type, m.remittance_min_fee, m.settle_currency, 
            a.email as auth_email, a.authorized as auth_time, a.ip_addr as auth_ip, b.*
            FROM %b b 
		    LEFT JOIN merchants m ON (b.merchant_id=m.id)
            LEFT JOIN (select * from %b ra where ra.batch_id=%s AND ra.active=2 order by authorized desc limit 1 ) a on (a.batch_id=b.id)
            WHERE b.id = %s 
			) as batch
			, %b l 
			LEFT JOIN (SELECT max(id) as id, batch_id, log_id FROM `remittance_api_log` group by batch_id,log_id) api on (api.log_id = l.id)
			where batch.id= l.batch_id ;"
            , self::DATABASE_TABLE_BATCH, self::DATABASE_TABLE_AUTHORIZATION, $id, $id, self::DATABASE_TABLE_REMITTANCE);

        if (!is_array($res) || !count($res))
            return FALSE;

        //$this->logger->debug("getBatchDetails($id)");
/*
        foreach ($res as $k=>$r) {
            $res[$k]['tx_status_name'] = $this->getLogStatus($r['tx_status']);
        }
        //approved or not
        if (!empty($res[0]['total_usd'])) {
            $res[0]['status_name'] = $this->getStatus($res[0]['status']);
            //$this->logger->debug($res[0]);
            return $res;
        }
*/
        //remove rejected rec
        foreach ($res as $k=>$r) {
            $res[$k]['status_name'] = self::getStatus($r['status']);
            $res[$k]['tx_status_name'] = $r['tx_status_name'] = $this->getLogStatus($r['tx_status']);

            if (!$this->isValidRemittance($r)) {
                //$res[$k]['status_name'] = self::getStatus($r['status']);
                if (!$allRec  && $this->isCommitedStatus($res[$k]['status_name']))
                        unset($res[$k]);
            }
        }
        //keep $res[0] for summary
        if (!isset($res[0])) {
            $keys = array_keys($res);
            $fkey = $keys[0];
            $res[0] = $res[$fkey];
            unset($res[$fkey]);
        }
        //$this->logger->debug(var_export($res,true));

        $this->merchant_id = $res[0]['merchant_id'];
        /*
        $totals=array();
        $round_ttls = array();  //sum of rounded amount
        $totals['USD'] = $totals['CNY'] = 0;
        //$totals['fee_cny'] =
        $round_ttls['USD'] = $round_ttls['CNY'] = 0;
        */
        $totals = array_fill_keys($this->validCurrencys, 0);
        $totals['paid_amount_cny'] = 0;
        $round_ttls = array_fill_keys($this->validCurrencys, 0);

        $log_cnt = $count = 0;
        $reverse = false;
        $fxrate = 0;
        foreach ($res as $k=>$r) {
            //$this->logger->debug($r);
            //$res[$k]['status_name'] = self::getStatus($r['status']);
            //$res[$k]['tx_status_name'] = $this->getLogStatus($r['tx_status']);
            //failed
            if ($this->isSetRemittance($r))
                $log_cnt++;

            if (!$this->isValidRemittance($r)) {
                /*
                if (!$allRec  && $this->isCommitedStatus($res[$k]['status_name']))
                    unset($res[$k]);
                */
                //skip count
                continue;
            }

            if (isset($r['amount'])) {
                $amount = $res[$k]['amount'] = floatval($r['amount']);

                if (!isset($totals[$r['currency']]))
                    $totals[$r['currency']] = 0;
                if (!isset($round_ttls[$r['currency']]))
                    $round_ttls[$r['currency']] = 0;
                $totals[$r['currency']] += $amount;
                $round_ttls[$r['currency']] += round($amount,2);
            }
            $convert_currency = $this->getConvertCurrency($r['currency'], $r['settle_currency']);
            //$totals[$r['currency']] += $amount;
            $count++;
            //reverse
            $reverse = false;
            //if ($convert_currency!='USD')
            if ($r['currency']!='CNY')
                $reverse = true;

            //non CNY currency
            $currency2 = ($reverse?$r['currency']:$r['convert_currency']);

            //no rate for declined batch
            if ($r['status']==self::BATCH_STATUS_DECLINED) {
                $fxrate = 0;
                $res[$k]["convert_currency"] = $convert_currency;
                $res[$k]["convert_amount"] = 'N/A';
                $res[$k]["convert_rate"] = 'N/A';
            //} elseif (isset($res[0]['total_convert_rate']) && $res[0]['total_convert_rate']>0) {
            } elseif (($fxrate = $this->getBatchRate($res[0]))>0) {
            //DB rate
                //$fxrate = $this->getBatchRate($res[0]);
                if ($reverse)
                    $fxrate = (1/$fxrate);
                $ttl_rate = $res[0]['total_convert_rate'];
            } else {
                //Live rate
                //$fxrate = $this->getFxRate($r['merchant_id'], '', $r['currency'], $convert_currency);
                $fxrate = $this->getRmRate($r['merchant_id'], '', $r['currency'], $convert_currency);
            }
            if (empty($fxrate) || $fxrate <= 0)
                continue;

            $convert_amt = $amount / $fxrate;
            // calc total
            $totals[$convert_currency] += $convert_amt;
            $round_ttls[$convert_currency] += round($convert_amt,2);
            //$totals['fee_cny'] += $r['fee_cny'];
            $totals['paid_amount_cny'] += ($reverse?$r['convert_paid_amount']:$r['paid_amount']);

            $res[$k]["convert_currency"] = $convert_currency;
            $res[$k]["convert_amount"] = $convert_amt;
            $res[$k]["convert_rate"] = $fxrate;
            //display rate > 1
            $res[$k]["convert_rate_display"] = ($reverse?(1/$fxrate):$fxrate);

        }   //foreach

        if ($count>0) {
            $res[0]['non_cny'] = $currency2;
            //$res[0]['total_usd'] = $totals['USD'];
            $res[0]['total_usd'] = $totals[$currency2];
            $res[0]['total_cny'] = $totals['CNY'];
            //$res[0]['round_total_usd'] = $round_ttls['USD'];
            $res[0]['round_total_usd'] = $round_ttls[$currency2];
            $res[0]['round_total_cny'] = $round_ttls['CNY'];
        } else {
            //default info for display
            $res[0]['non_cny'] = 'USD';

        }

        $res[0]['final_convert_rate'] = $fxrate;
        //display rate > 1
        if ($fxrate>0)
            $res[0]['final_convert_rate_display'] = ($reverse?(1/$fxrate):$fxrate);
        //check div by zero
        if (empty($res[0]['total_convert_rate']) && $totals[$currency2]>0)
            $res[0]['total_convert_rate'] = ($totals['CNY']/$totals[$currency2]);
        /*
        if ($totals['USD']!=0)
            $res[0]['total_convert_rate'] = $ttl_rate;
        */
        $res[0]['count'] = $count;
        //check if all logs set ok,failed
        $res[0]['all_log_set'] = ($log_cnt==count($res));
        if (isset($res[0]['target'])) {
            $res[0]['target_name'] = self::getTargetName($res[0]['target']);
        }
        // extra rate field
        /*
        if (empty($res[0]["quote_convert_rate"]))
            $res[0]["quote_convert_rate"] = $res[0]['total_convert_rate'];
          */
        if ($this->debug)  var_dump($res);
        //$this->logger->debug("getBatchDetails($id)", $res[0]);

        return $res;
    }

    /*
     * return currency & convert_currency of a batch
     */
    public function getBatchCurrencys($id) {
        if (empty($id))
            return false;

        $res = DB::queryFirstRow("SELECT currency , convert_currency FROM %b WHERE batch_id = %s AND (currency IS NOT NULL OR convert_currency IS NOT NULL) ORDER BY status DESC;",
            self::DATABASE_TABLE_REMITTANCE, $id);
        return $res;
    }

    //public function setBatchRate($id, $rate_update=TRUE) {
    /*
     * @updateBatchTotalOnly, update batch total & count only, not individual log
     */
    public function updateBatch($id, $rate_update=TRUE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=false) {
        if (empty($id))
            return FALSE;

        $res = DB::query("SELECT l.*,b.*,m.name as merchant_name, l.id as id, l.status as tx_status, m.remittance_fee, m.remittance_fee_type, m.remittance_min_fee  
            FROM %b l, %b b LEFT JOIN merchants m ON (b.merchant_id=m.id) WHERE b.id=l.batch_id AND b.id = %s ;",
            self::DATABASE_TABLE_REMITTANCE, self::DATABASE_TABLE_BATCH, $id);

        if (!is_array($res) || !count($res))
            return FALSE;

        $local_charge = $this->getLocalProcessorFee($res[0]['target'], $res[0]['merchant_id']);
        // ['rate'=>$charge_rate, 'min'=>$min_charge, 'local'=>$local_charge, 'fee_type'=>$r['remittance_fee_type']];
        $configs = $this->getMerchantFeeConfig($res[0]['merchant_id'], $res[0]['target']);
        //approved or not
        //if (!empty($res[0]['total_usd'])) return $res;
        $this->logger->debug("updateBatch($id)", compact('local_charge', 'configs'));
        if (!$local_charge)
            $local_charge = $configs['local'];
        $settle_currency = (isset($configs['settle_currency'])?$configs['settle_currency']:null);

        $totals = $logs = $fees = array();
        //$totals['USD'] = $totals['CNY'] = 0;
        $totals = array_fill_keys($this->validCurrencys, 0);
        //$fees['USD'] = $fees['CNY'] = 0;
        $fees = array_fill_keys($this->validCurrencys, 0);

        $count = 0;
        $dbrate = $this->getBatchRate($res[0]);
        //[TODO] revoke balance when rate update
        
        foreach ($res as $k=>$r) {
            if (!$this->isValidRemittance($r))
                continue;

            $amount = $r['amount'];
            $currency = $r['currency'];
            $convert_currency = $this->getConvertCurrency($currency, $settle_currency);
            //reverse
            $reverse = false;
            //if ($convert_currency!='USD')
            if ($currency!='CNY')
                $reverse = true;
            /*
            if ($res[0]['total_convert_rate']>0 && !$rate_update) {
                $ttl_us_rate = $fxrate = $res[0]['total_convert_rate'];
            */
            if ($dbrate >0 && !$rate_update) {
                $ttl_us_rate = $fxrate = $dbrate;
                if ($reverse)
                    $fxrate = (1/$fxrate);
            } else {
                //$fxrate = $this->getFxRate($r['merchant_id'], '', $r['currency'], $convert_currency);
                $ttl_us_rate = $fxrate = $this->getRmRate($r['merchant_id'], '', $r['currency'], $convert_currency);
                if ($reverse)
                    $ttl_us_rate = (1/$ttl_us_rate);
            }
            //use finalised rate
            if ($quote_rate>0 || $complete_rate>0) {
                if ($complete_rate>0)
                    $fxrate = $complete_rate;
                elseif ($quote_rate>0)
                    $fxrate = $quote_rate;
                if ($reverse)
                    $fxrate = (1/$fxrate);
            }
            // fxrate is the effective rate for exchange
            if ($fxrate <= 0)
                continue;

            $convert_amt = $amount / $fxrate;
            // calc total
            $totals[$r['currency']] += $amount;
            if ($r['currency']!=$convert_currency)
                $totals[$convert_currency] += $convert_amt;
            $count++;
            // fee total
            if ($local_charge) {
                $fee_cny = $local_charge;
                $fees['CNY'] += $fee_cny;
                //$fees['USD'] += ($local_charge/$fxrate);
                $fees['USD'] += ($reverse?($local_charge*$fxrate):($local_charge/$fxrate));
            } else {
                $usd_charge = $this->getUsdChargeFee($r, ($reverse?$amount:$convert_amt));
                $fees['USD'] += $usd_charge;
                $fee_cny = $usd_charge*$fxrate;
                $fees['CNY'] += $fee_cny;
            }
            $tmp = ['convert_currency'=>$convert_currency, 'convert_amount'=>$convert_amt, 'convert_rate'=>$fxrate, 'id'=> $r['id'], 'batch_id'=> $r['batch_id'], 'update_time'=>date('Y-m-d H:i:s')
                    ,'fee_cny'=> $fee_cny];
            //update fee
            if ($r['remittance_fee_type']==2) {
                //client bear the fee
                $tmp['gross_amount_cny'] = ($reverse?$convert_amt:$amount) - $fee_cny;
            } else {
                $tmp['gross_amount_cny'] = ($reverse?$convert_amt:$amount);
            }
            if ($reverse) {
                $tmp['convert_paid_amount'] = $tmp['gross_amount_cny'] + $fee_cny;
                // fxrate < 1
                $tmp['paid_amount'] = $tmp['convert_paid_amount'] * $fxrate;
            } else {
                $tmp['paid_amount'] = $tmp['gross_amount_cny'] + $fee_cny;
                $tmp['convert_paid_amount'] = $tmp['paid_amount'] / $fxrate;
            }

            $logs[] = $tmp;
        }

        $this->unsetSavedMerchantReport($id);

        if ($totals['USD']>0)
            $total_usd = $totals['USD'];
        elseif (isset($totals[$currency]) || isset($totals[$convert_currency]))
            $total_usd = ($reverse?$totals[$currency]:$totals[$convert_currency]);

        //round before saving to DB
        /*
        wcRoundNumber($total_usd);
        wcRoundNumber($totals['CNY']);
        wcRoundNumber($fees['USD']);
        wcRoundNumber($fees['CNY']);
        */

        $dba = ['total_usd'=>$total_usd, 'total_cny'=>$totals['CNY'], 'convert_currency'=>$convert_currency,
            'fee_usd'=>$fees['USD'], 'fee_cny'=>$fees['CNY'], 'count'=>$count, 'update_time'=>date('Y-m-d H:i:s')];
        if ($updateBatchTotalOnly) {
            DB::update(self::DATABASE_TABLE_BATCH, $dba, "id=%s", $id);
            $this->logger->debug("updateBatch($id)", $dba);

            return true;
        }
        //if ($totals['USD']!=0)
        //for remittance processed by API, count can be 0 (all failed)
        if ($quote_rate>0 || $complete_rate>0)
        {
            // no overwrite
            if (!isset($res[0]['total_convert_rate']) && $ttl_us_rate>0)
                $dba['total_convert_rate'] = $ttl_us_rate;  //($totals['CNY'] / $totals['USD']);
            //$dba['count'] = $count;
            if ($quote_rate>0)
                $dba['quote_convert_rate'] = $quote_rate;
            if ($complete_rate>0)
                $dba['complete_convert_rate'] = $complete_rate;

            DB::update(self::DATABASE_TABLE_BATCH, $dba, "id=%s", $id);
            //[TODO]
            //update balance for rate change
            //update each log
            if (count($logs)>0)
                foreach ($logs as $log) {
                    DB::update(self::DATABASE_TABLE_REMITTANCE, $log, "id=%s AND batch_id=%s", $log['id'], $log['batch_id']);
                }
            return true;
        }
        //if ($this->debug)  var_dump($res);
        return FALSE;
    }

    // update status of all remittance_log in same batch
    public function updateBatchLogStatus($bid, $status) {
        if (empty($bid))
            return false;

        DB::update(self::DATABASE_TABLE_REMITTANCE, ['status'=> $status, 'update_time' => $this->timenow], "batch_id=%s", $bid);
    }

    // update status of single remittance_log of a batch
    public function setBatchLogStatus($bid, $id, $status) {
        if (empty($bid) || empty($id))
            return false;

        DB::update(self::DATABASE_TABLE_REMITTANCE, ['status'=> $status, 'update_time' => $this->timenow], "batch_id=%s AND id = %d", $bid, $id);
    }

    public function getMerchantFeeConfig($mid, $target) {
        $r = DB::queryFirstRow("SELECT * FROM merchants m where m.id=%s and enabled = 1; ", $mid);

        if (!is_array($r))
            return false;

        $charge_rate = (is_null($r['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$r['remittance_fee']);
        // service minimum charge in USD
        $min_charge = (is_null($r['remittance_min_fee'])?0:floatval($r['remittance_min_fee']));
        // default target
        $target = (empty($target)?self::DEFAULT_LOCAL_REMITTANCE_TARGET:$target);
        $local_charge = $this->getLocalProcessorFee($target, $mid);
        //override local charge if local_remittance_enabled
        if ($r['local_remittance_enabled']=='1' && $r['remittance_min_fee']>0) {
            $local_charge = $r['remittance_min_fee'];
        }

        $res = ['rate'=>$charge_rate, 'min'=>$min_charge, 'local'=>$local_charge, 'fee_type'=>$r['remittance_fee_type'], 'settle_currency'=>$r['settle_currency']];
        $this->logger->debug("getMerchantFeeConfig($mid, $target)", $res);

        return $res;
    }

    public function updateBatchLogFee($mid, $bid) {
        $batchs = $this->getBatchDetails($bid);
        if (! $batchs)
            return FALSE;
/*
        $configs = $this->getMerchantFeeConfig($mid, $batchs[0]['target']);
        //update gross_amount_cny, fee_cny
        foreach ($batchs as $batch) {
            $cny = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);
        }
*/
    }

    public function getBatchLog($bid, $id) {
        if (empty($bid) || empty($id))
            return false;
        $sql = "SELECT l.*, cup_code as ght_code, gpay_code FROM %b l, %b b WHERE batch_id = %s and id = %d and l.bank_code = b.code ;";
        $row = DB::queryFirstRow($sql, self::DATABASE_TABLE_REMITTANCE, self::DATABASE_TABLE_BANKS, $bid, $id);
        return $row;
    }

    // check if all log of a remittance batch have been processed by Processor API
    public function isAllBatchLogProcessed($bid) {
        if (empty($bid))
            return false;
        $sql = "SELECT * FROM %b WHERE batch_id = %s and status in (%d, %d) ;";

        $row = DB::query($sql, self::DATABASE_TABLE_REMITTANCE, $bid, self::RM_STATUS_ACCEPTED, self::RM_STATUS_DEFAULT);
        return !(count($row)>0);
    }

    public function exportExcel($bid, $t) {
        /*
1	Payment Asia Excel
2	ChinaGPay Excel
3	ChinaGPay API	<tbd>
4	Gnete Excel	<tbd>
5	Gnete API	<tbd>
6	GHT Excel	<tbd>
7	GHT API	<tbd>
10	Payment Asia Excel (Local)
*/
        $this->logger->debug("exportExcel($bid, $t)");

        switch($t) {
            case 1:
            case 10:
                return $this->exportPaymentAsiaExcel($bid);
            case 2:
                return $this->exportGPayExcel($bid);
            case 4:
                return false;
            case 6:
                return $this->exportGhtExcel($bid);
            case 11:
                return $this->exportJoinPayExcel($bid);
            default:
                return false;
        }
    }

    public function exportPaymentAsiaExcel($bid)    {
        $this->logger->debug("exportPaymentAsiaExcel($bid)");

        //$batchs = $this->getBatchDetails($bid);
        $batchs = $this->getBatchDetails($bid, false);
        if (!is_array($batchs))
            return FALSE;

        $excel = PHPExcel_IOFactory::load(self::PAYMENTASIA_EXCEL_TEMPLATE);
        $sheet = $excel->getActiveSheet();
        $sheet->setTitle(date('M Y'));
        //Settlement Date
        $sheet->setCellValue("H2", date('j M Y'));

        $baseRow = 6;
        $count = count($batchs);
        $sheet->insertNewRowBefore($baseRow+1, $count);
        foreach ($batchs as $r => $batch) {
            /*
             * Beneficiary Name	Beneficiary Account Number	Bank Name	Bank Branch	Province	City	Transaction Amount	Transaction Charge
             */
            //write row
            $row = $baseRow + $r;
            $amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("C$row", $batch['bank_name'])
                ->setCellValue("D$row", $batch['bank_branch'])
                ->setCellValue("E$row", $batch['province'])
                ->setCellValue("F$row", $batch['city'])
                ->setCellValue("G$row", number_format($amt,2,'.','')); //no thousand comma
        }
        //total sum
        $ext = strtolower(trim(strrchr(self::PAYMENTASIA_EXCEL_TEMPLATE, '.'),'.'));
        //$file = sprintf("%s/batch_%s_%s.%s",$this->EXCEL_TEMP_PATH, $bid, time(), $ext);
        $file = sprintf("%s/%s_batch_%s_%s.%s",$this->EXCEL_TEMP_PATH, date('YmdHi'),$bid, time(), $ext);
        if ($ext=='xls')
            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        else
            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save($file);

        $this->logger->debug("exportPaymentAsiaExcel: $file");
        return $file;
    }

    public function exportGPayExcel($bid) {
        $this->logger->debug("exportGPayExcel($bid)");

        //$batchs = $this->getBatchDetails($bid);
        $batchs = $this->getBatchDetails($bid, false);
        if (!is_array($batchs))
            return FALSE;

        $excel = PHPExcel_IOFactory::load(self::CHINAGPAY_EXCEL_TEMPLATE);
        $baseRow = 2;
        foreach ($batchs as $r=>$batch) {
                /* 订单号*	对公（00)/对私（01）*	开户银行所在省*	开户银行所在城市*	开户银行*	账户名称*	账户号码*	金额（元）手机号	备注
                */
                //write row
                $row = $baseRow + $r;
                $amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);
                //in case beneficiary bear the fee
                if (isset($batch['gross_amount_cny']))
                    $amt = $batch['gross_amount_cny'];

                $excel->getActiveSheet()->setCellValue("A$row", $this->getGPayOrderId())
                                        //->setCellValue("B$row", '01')
                                        ->setCellValueExplicit("B$row", '01', PHPExcel_Cell_DataType::TYPE_STRING)
                                        ->setCellValue("C$row", $batch['province'])
                                        ->setCellValue("D$row", $batch['city'])
                                        ->setCellValue("E$row", $batch['bank_name'])
                                        ->setCellValue("F$row", $batch['beneficiary_name'])
                                        //->setCellValue("G$row", ''.$batch['account'])
                                        ->setCellValueExplicit("G$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                                        ->setCellValue("H$row", number_format($amt,2,'.','')); //no thousand comma
        }

        //$file = sprintf("%s/batch_%s_%s.%s",$this->EXCEL_TEMP_PATH, $bid, time(), 'xls');
        $file = sprintf("%s/%s_batch_%s_%s.%s",$this->EXCEL_TEMP_PATH, date('YmdHi'),$bid, time(), 'xls');
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $writer->save($file);

        return $file;
    }

    public function exportGhtExcel($bid) {
        $this->logger->debug("exportGhtExcel($bid)");

        //$batchs = $this->getBatchDetails($bid);
        $batchs = $this->getBatchDetails($bid, false);
        if (!is_array($batchs))
            return FALSE;

        $excel = PHPExcel_IOFactory::load(self::GHT_EXCEL_TEMPLATE);
        $ght_id = $this->getGhtBatchId();
        $baseRow = 3;
        //summary
        $excel->getActiveSheet()->setCellValue("A$baseRow", date('Y-m-d'))
            ->setCellValueExplicit("B$baseRow", self::GHT_MERCHANT_CODE, PHPExcel_Cell_DataType::TYPE_STRING)
            ->setCellValue("C$baseRow", $ght_id)
            ->setCellValue("E$baseRow", count($batchs));
            //->setCellValue("F$baseRow", number_format($batchs[0]['total_cny'],2,'.',''));
            //->setCellValue("F$baseRow", number_format($batchs[0]['round_total_cny'],2,'.',''));

        $txBaseRow = 6;
        $sum = 0;
        foreach ($batchs as $r=>$batch) {
            /*
            明细序号	开户行名称	账号	账户名	账户属性	金额（单位：元）	电子联行号	开户行所在省	开户行所在市	商户流水号	备注
            */
            //write row
            $row = $txBaseRow + $r;
            $amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);
            //in case beneficiary bear the fee
            if (isset($batch['gross_amount_cny']))
                $amt = $batch['gross_amount_cny'];
            $sum += $amt;

            $excel->getActiveSheet()
                //->setCellValue("A$row", $r+1)
                ->setCellValueExplicit("A$row", $r+1, PHPExcel_Cell_DataType::TYPE_STRING)
                //->setCellValue("B$row", $batch['bank_name'].$batch['bank_branch'])
                ->setCellValue("B$row", $batch['bank_name'])
                ->setCellValueExplicit("C$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("D$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("E$row", '1', PHPExcel_Cell_DataType::TYPE_STRING)   //1 私人
                ->setCellValue("F$row", number_format($amt,2,'.',''))
                //电子联行号
                ->setCellValue("H$row", str_replace(['省','自治区'],'',$batch['province']))
                ->setCellValue("I$row", preg_replace('/市$/','', $batch['city']));
        }

        $excel->getActiveSheet()->setCellValue("F$baseRow", number_format($sum,2,'.',''));

        $file = sprintf("%s/%s_F01%s_%s.%s",$this->EXCEL_TEMP_PATH, self::GHT_MERCHANT_CODE, date('Ymd'), $ght_id, 'xls');
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $writer->save($file);

        return $file;
    }

    public function exportJoinPayExcel($bid)    {
        $this->logger->debug("exportJoinPayExcel($bid)");

        $batchs = $this->getBatchDetails($bid);
        if (!is_array($batchs))
            return FALSE;

        $excel = PHPExcel_IOFactory::load(ROOT.self::JOINPAY_REMITTANCE_TEMPLATE);
        $sheet = $excel->getActiveSheet();
        //$sheet->setTitle(date('M Y'));
        $baseRow = 2;
        $count = count($batchs);
        $sheet->insertNewRowBefore($baseRow+1, $count);
        foreach ($batchs as $r => $batch) {
            /*
             * 收款人姓名	收款银行帐号	转帐金额	转账说明	收款银行所在城市	账户类型	收款银行名称
             */
            //write row
            $row = $baseRow + $r;
            $amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("C$row", number_format($amt,2,'.','')) //no thousand comma
                ->setCellValue("D$row", '报销')
                ->setCellValue("E$row", $batch['city'])
                ->setCellValue("F$row", '2')    // 对公：填1，对私：填2
                ->setCellValue("G$row", $batch['bank_name']);
        }
        //total sum
        $ext = strtolower(trim(strrchr(self::JOINPAY_REMITTANCE_TEMPLATE, '.'),'.'));
        $file = sprintf("%s/merchant_transfer%s.%s",$this->EXCEL_TEMP_PATH, $this->getJoinPayRemittanceId(), $ext);
        if ($ext=='xls')
            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        else
            $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save($file);

        $this->logger->debug("exportJoinPayExcel: $file");
        return $file;
    }

    public function getSavedMerchantReport($bid, $status, $type='', $readonly=false) {
        $this->logger->debug("getSavedMerchantReport($bid, $status)");

        if (empty($bid) )
            return false;

        $report = DB::queryFirstRow("SELECT * FROM %b where batch_id=%s and batch_status=%d AND readonly=%d AND %l order by update_time desc;", self::DATABASE_TABLE_REPORT, $bid, $status, ($readonly?1:0)
            ,!empty($type)?"type='$type'":"1=1");
        $this->logger->debug("getSavedMerchantReport($bid, $status)", [$report]);

        if (is_null($report))
            return false;
        $fpath = ROOT.$report['path'];
        if (!is_readable($fpath))
            return false;

        return $fpath;  //$report['path'];
    }

    //path is the full path of excel report file
    public function setSavedMerchantReport($bid, $status, $path, $readonly=false ) {
        $this->logger->debug("setSavedMerchantReport($bid, $status, $path)");
        // BATCH_STATUS_COMPLETED
        //$fpath = ROOT.$path;

        if (empty($bid) || !is_readable($path))
            return false;
        $path = str_replace(ROOT, '', $path);
        DB::insert(self::DATABASE_TABLE_REPORT, ['batch_id'=>$bid, 'batch_status'=>$status, 'path'=>$path, 'readonly'=>$readonly, 'type'=>strtolower(substr(strrchr($path, "."), 1)) ]);
    }

    //remove saved report
    public function unsetSavedMerchantReport($bid) {
        $this->logger->debug("unsetSavedMerchantReport($bid)");

        if (empty($bid))
            return false;

        DB::delete(self::DATABASE_TABLE_REPORT, "batch_id=%s", $bid);
    }

    public function dep_exportMerchantReport($bid, $final=true)    {
        $this->logger->debug("exportMerchantReport($bid)");

        $batchs = $this->getBatchDetails($bid);
        if (!is_array($batchs))
            return FALSE;

        $type = $batchs[0]['remittance_fee_type'];
        $status = $batchs[0]['status'];

        $file = $this->getSavedMerchantReport($bid, $status);
        if ($file)
            return $file;

        if ($type==2)
            $excel = $this->writeExcelMerchantReport2($batchs, $final);
        else
            $excel = $this->writeExcelMerchantReport1($batchs, $final);

        $fext = trim(strrchr(self::MERCHANT_REPORT_EXCEL_TEMPLATE,'.'),'.');
        //$file = sprintf("%s/report%s_%s_%s.%s",$this->EXCEL_TEMP_PATH, $type, $bid, time(), $fext);
        $file = sprintf("%s/report%s_%s_%s.%s",$this->EXCEL_REPORT_PATH, $type, $bid, time(), $fext);
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $writer->save($file);

        $this->setSavedMerchantReport($bid, $status, $file);
        return $file;
    }

    public function exportMerchantReport($bid, $final=true, $pdf=false, $password='')    {
        $this->logger->debug("exportMerchantReport($bid)");

        $batchs = $this->getBatchDetails($bid);
        if (!is_array($batchs))
            return FALSE;

        $type = $batchs[0]['remittance_fee_type'];
        $status = $batchs[0]['status'];
        $readonly = (!empty($password));

        $file = $this->getSavedMerchantReport($bid, $status, ($pdf?'pdf':''), $readonly);
        if ($file)
            return $file;

        if ($type==2)
            $excel = $this->writeExcelMerchantReport2_2017($batchs, $final);
        else
            $excel = $this->writeExcelMerchantReport1_2017($batchs, $final);

        try {
            if ($pdf) {
                $rendererName = PHPExcel_Settings::PDF_RENDERER_TCPDF;
                $rendererLibraryPath = ROOT . '/vendor/tecnickcom/tcpdf/'; //ini_get('include_path').
                /*$rendererName = PHPExcel_Settings::PDF_RENDERER_DOMPDF;
                $rendererLibraryPath = ROOT.'/vendor/dompdf/dompdf/';
                */
                PHPExcel_Settings::setPdfRenderer($rendererName, $rendererLibraryPath);
                $fext = 'pdf';
                //$file = sprintf("%s/report%s_%s_%s.%s", $this->EXCEL_TEMP_PATH, $type, $bid, time(), $fext);
                $file = sprintf("%s/report%s_%s_%s.%s", $this->EXCEL_REPORT_PATH, $type, $bid, time(), $fext);
                $writer = PHPExcel_IOFactory::createWriter($excel, 'PDF');
            } else {
                // Set password for readonly activesheet
                if (!empty($password)) {
                    $excel->getSecurity()->setLockWindows(true);
                    $excel->getSecurity()->setLockStructure(true);
                    $excel->getSecurity()->setWorkbookPassword($password);
                    // Set password for readonly data
                    $excel->getActiveSheet()->getProtection()->setSheet(true);
                    $excel->getActiveSheet()->getProtection()->setPassword($password);
                }

                $fext = trim(strrchr(self::MERCHANT_REPORT_EXCEL_TEMPLATE, '.'), '.');
                //$file = sprintf("%s/report%s_%s_%s.%s", $this->EXCEL_TEMP_PATH, $type, $bid, time(), $fext);
                $file = sprintf("%s/report%s_%s_%s.%s", $this->EXCEL_REPORT_PATH, $type, $bid, time(), $fext);
                $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
            }
            $fpath = ROOT . $file;
            $this->logger->debug("exportMerchantReport:$fpath");
            $writer->save($fpath);
        } catch (Exception $e) {
            $this->logger->debug("Exception:$e");
            return false;
        }

        $this->setSavedMerchantReport($bid, $status, $fpath, $readonly);
        return $fpath;
    }

    public function writeExcelMerchantReport1($batchs, $final=true)  {
        // charge = 0.5%
        $charge_rate = (is_null($batchs[0]['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$batchs[0]['remittance_fee']);
        $charge_rate.='%';
        // service minimum charge in USD
        $min_charge = (is_null($batchs[0]['remittance_min_fee'])?0:floatval($batchs[0]['remittance_min_fee']));
        $local_charge = $this->getLocalProcessorFee($batchs[0]['target'], $this->merchant_id);

        $rate = $this->getBatchRate($batchs[0]);

        $tpl = self::MERCHANT_REPORT_EXCEL_TEMPLATE;
        $excel = PHPExcel_IOFactory::load($tpl);
        //$fext = trim(strrchr($tpl,'.'),'.');

        $sheet = $excel->getActiveSheet();
        //$sheet->setTitle(date('M Y'));
        //Settlement Date
        $settletime = (!empty($batchs[0]['complete_time'])?strtotime($batchs[0]['complete_time']):time());
        $sheet->setCellValue("J4", date('j F Y', $settletime));
        //rate
        //$sheet->setCellValue("K4", 'Exchange Rate:');
        $rate_cell_idx = 'L4';
        $sheet->setCellValue($rate_cell_idx, $rate);


        //Assume all records with same currency
        /*
        $currency = 'CNY';
        $currency2 = ($batchs[0]['currency']==$currency?$batchs[0]['convert_currency']:$batchs[0]['currency']);
*/
        $currency = $batchs[0]['currency']; // CNY/USD
        //non-CNY
        $currency2 = ($batchs[0]['currency']=='CNY'?$batchs[0]['convert_currency']:$batchs[0]['currency']);
        $baseRow = 8;
        $totalRow = 10;
        $blankRowNum = $totalRow - $baseRow;

        $count = count($batchs);
        $idx = 0;
        //update meta row
        $metaRow = $baseRow-1 ;
        $sheet->setCellValue("J$metaRow", "Transaction Amount Client Received")
            //setCellValue("K$metaRow", "Transaction Amount Client Received ({$batchs[0]['convert_currency']})")
                //->setCellValue("L$metaRow", 'Gross Amount for Remittance')
                ->setCellValue("L$metaRow", "Service Charge ($charge_rate)")
                ->setCellValue("M$metaRow", "Amount paid by Merchant")
                ->setCellValue("N$metaRow", "Amount paid by Merchant ($currency2)");
        if ($local_charge)
            $sheet->setCellValue("L$metaRow", "Service Charge (@$local_charge)");

        foreach ($batchs as $r => $batch) {
            /*
             * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
             * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
             */
            if (!$this->isValidRemittance($batch))
                continue;
            //write row
            $row = $baseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
            //$us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);
            $us_amt = ($batch['currency']!='CNY'?$batch['amount']:$batch['convert_amount']);
            $cn_amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("C$row", $batch['bank_name'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("D$row", $batch['bank_branch'])
                ->setCellValue("E$row", $batch['province'])
                ->setCellValue("F$row", $batch['city'])
                ->setCellValueExplicit("G$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("H$row", $currency)
                ->setCellValue("I$row", number_format($amt,2,'.','')) //no thousand comma
                //Merchant bears the fee
                //->setCellValue("K$row", number_format($us_amt, 2, '.', ''))//no thousand comma
                //->setCellValue("J$row", "=I$row")
                //->setCellValue("K$row", "=I$row")
                ->setCellValue("J$row", number_format($cn_amt,2,'.','')) //CNY
                ->setCellValue("K$row", "=J$row")
                //->setCellValue("L$row", "=MAX(I$row*$charge_rate, $min_charge)")
                ->setCellValue("L$row", "=MAX(J$row*$charge_rate, $min_charge*$rate_cell_idx)")
                ->setCellValue("M$row", "=K$row+L$row")
                //->setCellValue("N$row", "=M$row/".$rate) ;
                ->setCellValue("N$row", "=M$row/".$rate_cell_idx) ; //USD
                //->setCellValue("P$row", "=O$row/".$batch['convert_rate']);
            if ($local_charge)
                $sheet->setCellValue("L$row", "$local_charge");

            $idx++;
        }
        $nCols = 7; //set the number of columns
        //skip col A
        foreach (range(1, $nCols-1) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        //total sum
        $totalRow += $idx;
        $sheet->setCellValue("L$totalRow", sprintf("=SUM(L%d:L%d)", $baseRow, $totalRow-1))
            //->setCellValue("L$totalRow", "=K$totalRow*$charge_rate")
                //->setCellValue("N$totalRow", "=M$totalRow/".$rate);
                ->setCellValue("N$totalRow", "=M$totalRow/".$rate_cell_idx);
            //->setCellValue("O$totalRow", "=J$totalRow");
        // intermediate report
        if (!$final) {
            $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => 'FF0000'),
                    'size'  => 12,
                    'name'  => 'Verdana'
                ));

            $sheet->getStyle("A1")->applyFromArray($styleArray);
            $sheet->setCellValue("A1", "INTERNAL USE ONLY");
        }

        //remove blank row
        if ($count>0)
            $sheet->removeRow($row+1, $blankRowNum-1);

        return $excel;
    }

    public function writeExcelMerchantReport1_2017($batchs, $final=true)  {
        // charge = 0.5%
        $charge_rate = (is_null($batchs[0]['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$batchs[0]['remittance_fee']);
        $charge_rate.='%';
        // service minimum charge in USD
        $min_charge = (is_null($batchs[0]['remittance_min_fee'])?0:floatval($batchs[0]['remittance_min_fee']));
        $local_charge = $this->getLocalProcessorFee($batchs[0]['target'], $this->merchant_id);

        $rate = $this->getBatchRate($batchs[0]);

        $tpl = ROOT.self::MERCHANT_REPORT_EXCEL_TEMPLATE;
        $excel = PHPExcel_IOFactory::load($tpl);
        //$fext = trim(strrchr($tpl,'.'),'.');

        $sheet = $excel->getActiveSheet();
        //$sheet->setTitle(date('M Y'));
        //Settlement Date
        $settletime = (!empty($batchs[0]['complete_time'])?strtotime($batchs[0]['complete_time']):time());
        $sheet->setCellValue("J4", date('j F Y', $settletime));
        //rate
        //$sheet->setCellValue("K4", 'Exchange Rate:');
        $rate_cell_idx = 'L4';
        $sheet->setCellValue($rate_cell_idx, $rate);
        //batch details
        $sheet->setCellValue("B6", $batchs[0]['batch_id']);
        $sheet->setCellValue("B7", $batchs[0]['merchant_name']);

        //Assume all records with same currency
        $currency = $batchs[0]['currency']; // CNY/USD
        //non-CNY
        $currency2 = ($batchs[0]['currency']=='CNY'?$batchs[0]['convert_currency']:$batchs[0]['currency']);
        $baseRow = 10;
        $totalRow = 12;
        $failBaseRow = 19;
        $blankRowNum = $totalRow - $baseRow;

        $count = count($batchs);
        $idx = 0;
        $fails = array();
        //update meta row
        $metaRow = $baseRow-1 ;
        $sheet->setCellValue("J$metaRow", "Transaction Amount Client Received")
            //setCellValue("K$metaRow", "Transaction Amount Client Received ({$batchs[0]['convert_currency']})")
            //->setCellValue("L$metaRow", 'Gross Amount for Remittance')
            ->setCellValue("L$metaRow", "Service Charge ($charge_rate)")
            ->setCellValue("M$metaRow", "Amount paid by Merchant")
            ->setCellValue("N$metaRow", "Amount paid by Merchant ($currency2)");
        if ($local_charge)
            $sheet->setCellValue("L$metaRow", "Service Charge (@$local_charge)");

        foreach ($batchs as $r => $batch) {
            /*
             * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
             * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
             * Merchant Reference 	ID Card Type
             */
            if (!$this->isValidRemittance($batch)) {
                $fails[] = $batch;
                continue;
            }
            //write row
            $row = $baseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
            //$us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);
            $us_amt = ($batch['currency']!='CNY'?$batch['amount']:$batch['convert_amount']);
            $cn_amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("C$row", $batch['bank_name'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("D$row", $batch['bank_branch'])
                ->setCellValue("E$row", $batch['province'])
                ->setCellValue("F$row", $batch['city'])
                ->setCellValueExplicit("G$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("H$row", $currency)
                ->setCellValue("I$row", number_format($amt,2,'.','')) //no thousand comma
                //Merchant bears the fee
                //->setCellValue("K$row", number_format($us_amt, 2, '.', ''))//no thousand comma
                //->setCellValue("J$row", "=I$row")
                //->setCellValue("K$row", "=I$row")
                ->setCellValue("J$row", number_format($cn_amt,2,'.','')) //CNY
                ->setCellValue("K$row", "=J$row")
                //->setCellValue("L$row", "=MAX(I$row*$charge_rate, $min_charge)")
                ->setCellValue("L$row", "=MAX(J$row*$charge_rate, $min_charge*$rate_cell_idx)")
                ->setCellValue("M$row", "=K$row+L$row")
                //->setCellValue("N$row", "=M$row/".$rate) ;
                ->setCellValueExplicit("O$row", $batch['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("N$row", "=M$row/".$rate_cell_idx) ; //USD
            //->setCellValue("P$row", "=O$row/".$batch['convert_rate']);
            if (!empty($batch['id_type']))
                $sheet->setCellValueExplicit("P$row", $batch['id_type'], PHPExcel_Cell_DataType::TYPE_STRING);
            if ($local_charge)
                $sheet->setCellValue("L$row", "$local_charge");

            $idx++;
        }
        $nCols = 7; //set the number of columns
        //skip col A
        foreach (range(1, $nCols-1) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        //total sum
        $totalRow += $idx;
        $sheet->setCellValue("L$totalRow", sprintf("=SUM(L%d:L%d)", $baseRow, $totalRow-1))
            //->setCellValue("L$totalRow", "=K$totalRow*$charge_rate")
            //->setCellValue("N$totalRow", "=M$totalRow/".$rate);
            ->setCellValue("N$totalRow", "=M$totalRow/".$rate_cell_idx);
        //->setCellValue("O$totalRow", "=J$totalRow");

        //FAILED Transaction
        if (count($fails))
            foreach ($fails as $r => $batch) {
            /*
             * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
             * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
             * Merchant Reference 	ID Card Type
             */
            //write row
            $row = $failBaseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
            //$us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);
            $us_amt = ($batch['currency']!='CNY'?$batch['amount']:$batch['convert_amount']);
            $cn_amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("C$row", $batch['bank_name'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("D$row", $batch['bank_branch'])
                ->setCellValue("E$row", $batch['province'])
                ->setCellValue("F$row", $batch['city'])
                ->setCellValueExplicit("G$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("H$row", $currency)
                ->setCellValue("I$row", number_format($amt,2,'.','')) //no thousand comma
                //Merchant bears the fee
                //->setCellValue("K$row", number_format($us_amt, 2, '.', ''))//no thousand comma
                //->setCellValue("J$row", "=I$row")
                //->setCellValue("K$row", "=I$row")
/*
                ->setCellValue("J$row", number_format($cn_amt,2,'.','')) //CNY
                ->setCellValue("K$row", "=J$row")
                //->setCellValue("L$row", "=MAX(J$row*$charge_rate, $min_charge*$rate_cell_idx)")
                ->setCellValue("L$row", 0)
                ->setCellValue("M$row", 0)
                ->setCellValue("N$row", 0)
*/
                //->setCellValue("M$row", "=K$row+L$row")
                ->setCellValueExplicit("J$row", $batch['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING);
                //->setCellValue("N$row", "=M$row/".$rate_cell_idx) ; //USD
                /*
            if ($local_charge)
                $sheet->setCellValue("L$row", "$local_charge");
                */
                if (!empty($batch['id_type']))
                    $sheet->setCellValueExplicit("K$row", $batch['id_type'], PHPExcel_Cell_DataType::TYPE_STRING);

            $idx++;
        }
        // intermediate report
        if (!$final) {
            $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => 'FF0000'),
                    'size'  => 12,
                    'name'  => 'Verdana'
                ));

            $sheet->getStyle("A1")->applyFromArray($styleArray);
            $sheet->setCellValue("A1", "INTERNAL USE ONLY");
        }

        //remove blank row
        if ($count>0)
            $sheet->removeRow($row+1, $blankRowNum-1);

        return $excel;
    }

    public function writeExcelMerchantReport2($batchs, $final=true)    {
        $charge_rate = (is_null($batchs[0]['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$batchs[0]['remittance_fee']);
        $charge_rate.='%';
        // service minimum charge
        $min_charge = (is_null($batchs[0]['remittance_min_fee'])?0:floatval($batchs[0]['remittance_min_fee']));
        $local_charge = $this->getLocalProcessorFee($batchs[0]['target'], $this->merchant_id);

        $rate = $this->getBatchRate($batchs[0]);
        $tpl = self::MERCHANT_REPORT_EXCEL_TEMPLATE2;
        $excel = PHPExcel_IOFactory::load($tpl);
        //$fext = trim(strrchr($tpl,'.'),'.');

        $sheet = $excel->getActiveSheet();
        //$sheet->setTitle(date('M Y'));
        //Settlement Date
        $settletime = (!empty($batchs[0]['complete_time'])?strtotime($batchs[0]['complete_time']):time());
        $sheet->setCellValue("J4", date('j F Y', $settletime));

        //Assume all records with same currency
        $currency = 'CNY';
        $currency2 = ($batchs[0]['currency']==$currency?$batchs[0]['convert_currency']:$batchs[0]['currency']);
        $baseRow = 8;
        $totalRow = 11;
        $count = count($batchs);
        $idx = 0;
        //update meta row
        $metaRow = $baseRow-1 ;
        $sheet->setCellValue("K$metaRow", "Transaction Amount Client Received")
            //->setCellValue("L$metaRow", 'Gross Amount for Remittance')
            ->setCellValue("M$metaRow", "Service Charge ($charge_rate)")
            ->setCellValue("O$metaRow", "Amount paid by Merchant")
            ->setCellValue("P$metaRow", "Amount paid by Merchant ($currency2)");
        if ($local_charge)
            $sheet->setCellValue("M$metaRow", "Service Charge (@$local_charge)");

        foreach ($batchs as $r => $batch) {
            /*
             * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
             * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
             */
            if (!$this->isValidRemittance($batch))
                continue;
            //write row
            $row = $baseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
            $us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);

            $sheet->setCellValue("B$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("C$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("D$row", $batch['bank_name'])
                ->setCellValue("E$row", $batch['bank_branch'])
                ->setCellValue("F$row", $batch['province'])
                ->setCellValue("G$row", $batch['city'])
                ->setCellValueExplicit("H$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("I$row", $currency)
                ->setCellValue("J$row", number_format($amt,2,'.','')) //no thousand comma
            //Merchant’s client bears the fee
                ->setCellValue("K$row", "=J$row-M$row")
                ->setCellValue("L$row", "=J$row-M$row")
                //->setCellValue("M$row", "=J$row*" . $charge_rate)
                ->setCellValue("M$row", "=MAX(J$row*$charge_rate, $min_charge)")
                ->setCellValue("O$row", "=J$row")
                ->setCellValue("P$row", number_format($us_amt, 2, '.', '')); //no thousand

            if ($local_charge)  //Service Charge
                $sheet->setCellValue("M$row", "$local_charge");

            $idx++;
        }
        //total sum
        $totalRow += $idx;
        //Merchant’s client bears the fee
            //$sheet->setCellValue("K$metaRow", 'Transaction Amount Client Received')
            //->setCellValue("L$metaRow", 'Gross Amount for Remittance')
        $sheet->setCellValue("M$totalRow", sprintf("=SUM(M%d:M%d)", $baseRow, $totalRow-1))
                //->setCellValue("M$totalRow", "=J$totalRow*$charge_rate")
                ->setCellValue("O$totalRow", "=J$totalRow");

        $nCols = 7; //set the number of columns
        //skip col A
        foreach (range(1, $nCols-1) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        // intermediate report
        if (!$final) {
            $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => 'FF0000'),
                    'size'  => 12,
                    'name'  => 'Verdana'
                ));

            $sheet->getStyle("A1")->applyFromArray($styleArray);
            $sheet->setCellValue("A1", "INTERNAL USE ONLY");
        }
        return $excel;
    }

    public function writeExcelMerchantReport2_2017($batchs, $final=true)    {
        $charge_rate = (is_null($batchs[0]['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$batchs[0]['remittance_fee']);
        $charge_rate.='%';
        // service minimum charge
        $min_charge = (is_null($batchs[0]['remittance_min_fee'])?0:floatval($batchs[0]['remittance_min_fee']));
        $local_charge = $this->getLocalProcessorFee($batchs[0]['target'], $this->merchant_id);

        $rate = $this->getBatchRate($batchs[0]);
        $tpl = ROOT.self::MERCHANT_REPORT_EXCEL_TEMPLATE2;
        $excel = PHPExcel_IOFactory::load($tpl);
        //$fext = trim(strrchr($tpl,'.'),'.');

        $sheet = $excel->getActiveSheet();
        //$sheet->setTitle(date('M Y'));
        //Settlement Date
        $settletime = (!empty($batchs[0]['complete_time'])?strtotime($batchs[0]['complete_time']):time());
        $sheet->setCellValue("J4", date('j F Y', $settletime));
        $sheet->setCellValue("L4", $rate);
        //batch details
        $sheet->setCellValue("B6", $batchs[0]['batch_id']);
        $sheet->setCellValue("B7", $batchs[0]['merchant_name']);

        //Assume all records with same currency
        $currency = 'CNY';
        $currency2 = ($batchs[0]['currency']==$currency?$batchs[0]['convert_currency']:$batchs[0]['currency']);
        $baseRow = 10;
        $totalRow = 12;
        $failBaseRow = 19;
        $count = count($batchs);
        $idx = 0;
        $fails = array();
        //update meta row
        $metaRow = $baseRow-1 ;
        $sheet->setCellValue("J$metaRow", "Transaction Amount Client Received")
            ->setCellValue("L$metaRow", "Service Charge ($charge_rate)")
            ->setCellValue("M$metaRow", "Amount paid by Merchant")
            ->setCellValue("N$metaRow", "Amount paid by Merchant ($currency2)");
        if ($local_charge)
            $sheet->setCellValue("L$metaRow", "Service Charge (@$local_charge)");

        foreach ($batchs as $r => $batch) {
            /*
             * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
             * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
             */
            if (!$this->isValidRemittance($batch)) {
                $fails[] = $batch;
                continue;
            }
            //write row
            $row = $baseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
            $us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);

            $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("C$row", $batch['bank_name'])
                ->setCellValue("D$row", $batch['bank_branch'])
                ->setCellValue("E$row", $batch['province'])
                ->setCellValue("F$row", $batch['city'])
                ->setCellValueExplicit("G$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("H$row", $currency)
                ->setCellValue("I$row", number_format($amt,2,'.','')) //no thousand comma
                //Merchant’s client bears the fee
                ->setCellValue("J$row", "=I$row-L$row")
                ->setCellValue("K$row", "=I$row-L$row")
                ->setCellValue("L$row", "=MAX(I$row*$charge_rate, $min_charge)")
                ->setCellValue("M$row", "=I$row")
                ->setCellValue("N$row", number_format($us_amt, 2, '.', '')) //no thousand
                ->setCellValueExplicit("O$row", $batch['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING);

            if (!empty($batch['id_type']))
                $sheet->setCellValueExplicit("P$row", $batch['id_type'], PHPExcel_Cell_DataType::TYPE_STRING);

            if ($local_charge)  //Service Charge
                $sheet->setCellValue("L$row", "$local_charge");

            $idx++;
        }
        //total sum
        $totalRow += $idx;
        //Merchant’s client bears the fee
        //$sheet->setCellValue("K$metaRow", 'Transaction Amount Client Received')
        //->setCellValue("L$metaRow", 'Gross Amount for Remittance')
        $sheet->setCellValue("L$totalRow", sprintf("=SUM(L%d:L%d)", $baseRow, $totalRow-1))
              ->setCellValue("N$totalRow", sprintf("=SUM(N%d:N%d)", $baseRow, $totalRow-1))
              ->setCellValue("M$totalRow", "=I$totalRow");

        //FAILED Transaction
        if (count($fails))
            foreach ($fails as $r => $batch) {
                /*
                 * Client ID/ Name	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City	ID Card No.	Currency	Transaction Amount Received
                 * Transaction Amount Client Received (USD)	Gross Amount for Remittance	Service Charge (0.5%)	Amount paid by Merchant
                 * Merchant Reference 	ID Card Type
                 */
                //write row
                $row = $failBaseRow + $idx;
                $sheet->insertNewRowBefore($row+1, 1);

                $amt = ($batch['currency']==$currency?$batch['amount']:$batch['convert_amount']);
                //$us_amt = ($batch['currency']==$currency?$batch['convert_amount']:$batch['amount']);
                $us_amt = ($batch['currency']!='CNY'?$batch['amount']:$batch['convert_amount']);
                $cn_amt = ($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']);

                $sheet->setCellValue("A$row", $batch['beneficiary_name'])
                    ->setCellValueExplicit("B$row", $batch['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit("C$row", $batch['bank_name'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue("D$row", $batch['bank_branch'])
                    ->setCellValue("E$row", $batch['province'])
                    ->setCellValue("F$row", $batch['city'])
                    ->setCellValueExplicit("G$row", $batch['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue("H$row", $currency)
                    ->setCellValue("I$row", number_format($amt,2,'.','')) //no thousand comma
                    //Merchant bears the fee
                    //->setCellValue("K$row", number_format($us_amt, 2, '.', ''))//no thousand comma
                    //->setCellValue("J$row", "=I$row")
                    //->setCellValue("K$row", "=I$row")
                    /*
                                    ->setCellValue("J$row", number_format($cn_amt,2,'.','')) //CNY
                                    ->setCellValue("K$row", "=J$row")
                                    //->setCellValue("L$row", "=MAX(J$row*$charge_rate, $min_charge*$rate_cell_idx)")
                                    ->setCellValue("L$row", 0)
                                    ->setCellValue("M$row", 0)
                                    ->setCellValue("N$row", 0)
                    */
                    //->setCellValue("M$row", "=K$row+L$row")
                    ->setCellValueExplicit("J$row", $batch['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING);
                //->setCellValue("N$row", "=M$row/".$rate_cell_idx) ; //USD
                /*
            if ($local_charge)
                $sheet->setCellValue("L$row", "$local_charge");
                */
                if (!empty($batch['id_type']))
                    $sheet->setCellValueExplicit("K$row", $batch['id_type'], PHPExcel_Cell_DataType::TYPE_STRING);

                $idx++;
            }
        $nCols = 7; //set the number of columns
        //skip col A
        foreach (range(1, $nCols-1) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
        // intermediate report
        if (!$final) {
            $styleArray = array(
                'font'  => array(
                    'bold'  => true,
                    'color' => array('rgb' => 'FF0000'),
                    'size'  => 12,
                    'name'  => 'Verdana'
                ));

            $sheet->getStyle("A1")->applyFromArray($styleArray);
            $sheet->setCellValue("A1", "INTERNAL USE ONLY");
        }
        return $excel;
    }

    public function writeExcelInstantSearchResult($data, $file) {
        $tpl = ROOT.self::INSTANT_REMITTANCE_SEARCHRESULT_TEMPLATE;
        $excel = PHPExcel_IOFactory::load($tpl);

        $sheet = $excel->getActiveSheet();

        $baseRow = 7;
        //$totalRow = 11;
        $count = count($data);
        $idx = 0;
        /*
         * Time	Merchant	Beneficiary Name	Beneficiary Account No.	Bank Name	Bank Branch	Province	City
         * ID Card No.	Transaction Amount Received	Transaction Amount Client Received	Gross Amount for Remittance	Service Charge	Amount paid by Merchant	Currency	Converted Amount paid by Merchant	Exchange Rate	Merchant Reference	ID Card Type	Status	Trans ID
         */
        if ($count>0)
        foreach ($data as $k => $r) {
            //write row
            $row = $baseRow + $idx;
            $sheet->insertNewRowBefore($row+1, 1);

            $sheet->setCellValue("A$row", $r['create_time'])
                ->setCellValue("B$row", $r['merchant_name'])
                ->setCellValue("C$row", $r['name'])
                ->setCellValueExplicit("D$row", $r['account'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("E$row", $r['bank_name'])
                ->setCellValue("F$row", $r['bank_branch'])
                ->setCellValue("G$row", $r['province'])
                ->setCellValue("H$row", $r['city'])
                ->setCellValueExplicit("I$row", $r['id_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("J$row", number_format($r['amount'],2,'.','')) //no thousand comma
                /*
                ->setCellValue("K$row", number_format($r['gross_amount_cny'],2,'.','')) //no thousand comma
                ->setCellValue("L$row", number_format($r['gross_amount_cny'],2,'.','')) //no thousand comma
                ->setCellValue("M$row", number_format($r['fee_cny'],2,'.',''))
                ->setCellValue("N$row", number_format($r['paid_amount'],2,'.',''))
                */
                ->setCellValue("K$row", $r['gross_amount_cny'])
                ->setCellValue("L$row", $r['gross_amount_cny'])
                ->setCellValue("M$row", $r['fee_cny'])
                ->setCellValue("N$row", $r['paid_amount'])
                ->setCellValue("O$row", $r['convert_currency'])
                //->setCellValue("P$row", number_format($r['convert_paid_amount'],2,'.',''))
                //->setCellValue("Q$row", number_format($r['convert_rate'],4,'.',''))
                ->setCellValue("P$row", $r['convert_paid_amount'])
                ->setCellValue("Q$row", $r['convert_rate'])
                ->setCellValueExplicit("R$row", $r['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("S$row", $r['id_type'])
                ->setCellValue("T$row", $r['status_name'])
                ->setCellValue("U$row", $r['id'])
                ->setCellValue("V$row", self::getProcessorReturnMessageEnglish($r['remarks'])) //Remarks
                ->setCellValue("W$row", $r['target_name']);
                //->setCellValue("V$row", $r['remarks']);
            $idx++;
        }
        //total sum
        //$totalRow += $idx;

        $nCols = 21; //set the number of columns
        //skip col A
/*
        foreach (range(1, $nCols-1) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }
*/
        //return $excel;
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $file.=".xlsx";
        $writer->save($file);
        return $file;
    }

    public function setTransactionLimit($amt, $currency='CNY') {
        if (is_numeric($amt))
            $this->transaction_limit_cny = $amt;
    }

    public function checkAmountLimit($amt, $currency='CNY') {
        if ($this->transaction_limit_cny<=0)
            return true;
        if ($currency=='CNY') {
            $cny = $amt;
        } else {
            $fxrate = $this->getRmRate($this->merchant_id, '', $currency, 'CNY');
            $cny = $amt/$fxrate;
        }
        //$this->logger->debug("checkAmountLimit($amt) CNY=", [$cny]);
        if ($cny>$this->transaction_limit_cny)
            return ['code'=>110, 'msg'=>'Transaction amount over limit'];

        return true;
    }

    public function getGPayOrderId() {
        $key = 'gpay_id';
        $id = $this->file_db->get($key);
        if (empty($id))
            $id = self::CHINAGPAY_EXCEL_ORDERID_START;
        $next = $id+1;
        if ($next>999)
            $next = self::CHINAGPAY_EXCEL_ORDERID_START;
        $this->file_db->set($key, $next);

        return date('Ymd').str_pad("$id",3,'0', STR_PAD_LEFT);
    }

    public function getGhtBatchId() {
        $today = date('Ymd');
        $key = 'ght_batch_id';
        $ids = $this->file_db->get($key);   //array
        if (!is_array($ids) || $today != $ids['date'] || empty($ids['id']))
            $id = 1;
        else
            $id = $ids['id'];
        $next = ['date'=>$today, 'id'=>$id+1];
        $this->file_db->set($key, $next);

        return str_pad("$id",5,'0', STR_PAD_LEFT);
    }

    /*
     * 3 digits, start from 001
     */
    public function getJoinPayRemittanceId() {
        $today = date('Ymd');
        $key = 'joinpay_rm_id';
        $ids = $this->file_db->get($key);   //array
        //if (!is_array($ids) || $today != $ids['date'] || empty($ids['id']) || $ids['id']>=999)
        if (!is_array($ids) || empty($ids['id']) || $ids['id']>=999)
            $id = 1;
        else
            $id = $ids['id'];
        $next = ['date'=>$today, 'id'=>$id+1];
        $this->file_db->set($key, $next);

        return str_pad("$id",3,'0', STR_PAD_LEFT);
    }
    /*
     * Get effective rate from DB
     */
    public function getBatchRate($b) {
        //final completed rate
        if (isset($b['complete_convert_rate']) && $b['complete_convert_rate']>0)
            return $b['complete_convert_rate'];
        // approved rate
        if (isset($b['quote_convert_rate']) && $b['quote_convert_rate']>0)
            return $b['quote_convert_rate'];
        //live rate or quoted rate for processing
        if (isset($b['total_convert_rate']) && $b['total_convert_rate']>0)
            return $b['total_convert_rate'];

         return FALSE;
    }

    /*
     * ref: Merchant preference currency in database, settle_currency
     */
    public function getConvertCurrency($c, $ref=null) {
        $this->logger->debug("getConvertCurrency($c, $ref)");

        $c = strtoupper($c);
        $ref = ((isset($ref) && !empty($ref))?strtoupper($ref):$c);

        switch($c) {
            case 'CNY':
                if ($ref!=$c)
                    return $ref;
                return 'USD';
            case 'USD':
                return 'CNY';
            case 'HKD':
                return 'CNY';
            default:
                return 'CNY';
                //return 'USD';
        }
    }
    // 13 chars
    public function getBatchId() {
        return uniqid();
    }

    /*
     * Check if existing open batch or create a new one
     */
    public function getOpenBatchId($merchantid) {
        /*
        $res = DB::queryFirstRow("SELECT b.*,m.name as merchant_name   
            FROM %b b LEFT JOIN merchants m ON (b.merchant_id=m.id) WHERE b.status = %d AND m.id = %s ORDER BY b.upload_time DESC ;",
            self::DATABASE_TABLE_BATCH, self::BATCH_STATUS_OPEN, $merchantid);
*/
        $obid = $this->getExistOpenBatchId($merchantid);

        $batchs = array();
        if (!empty($this->username))
            $batchs['username'] = $this->username;
        if (!empty($this->ip))
            $batchs['ip_addr'] = $this->ip;

        //if (!is_array($res) || !count($res)) {
        if (! $obid) {
            //create batch
            $batchs['id'] = $bid = $this->getBatchId();
            $batchs['merchant_id'] = $merchantid;   //$this->merchant_id;
            $batchs['count'] = 0;   //count($this->remittances);
            $batchs['status'] = self::BATCH_STATUS_OPEN;
            //check same currency
            /*
            if (isset($totals['USD']) && $totals['USD']>0)
                $batchs['total_usd'] = $totals['USD'];
            if (isset($totals['CNY']) && $totals['CNY']>0)
                $batchs['total_cny'] = $totals['CNY'];
            */
            DB::insert(self::DATABASE_TABLE_BATCH, $batchs);
            //open batch
            $this->addNotificationLogs($merchantid, $bid);
        } else {
            $bid = $obid;   //$res['id'];
            $batchs['update_time'] = date('Y-m-d H:i:s');
            //update batch
            DB::update(self::DATABASE_TABLE_BATCH, $batchs, 'id=%s', $bid);
        }
        return $bid;
    }

    public function getExistOpenBatchId($merchantid)  {
        $res = DB::queryFirstRow("SELECT b.*,m.name as merchant_name   
            FROM %b b LEFT JOIN merchants m ON (b.merchant_id=m.id) WHERE b.status = %d AND m.id = %s ORDER BY b.upload_time DESC ;",
            self::DATABASE_TABLE_BATCH, self::BATCH_STATUS_OPEN, $merchantid);

        return (($res != null && isset($res['id']))?$res['id']:false);
    }
    //public function getFxRate($merchantid, $time, $fromCurrency, $toCurrency) {
    public function getRmRate($merchantid, $time='', $fromCurrency='CNY', $toCurrency='USD', $returnArray=false) {
        $this->logger->debug("getRmRate($merchantid, $time, $fromCurrency, $toCurrency)");
        /*
         * USDR01 – USDCNY remittance rate for HYCM
         * USDR02 – USDCNY remittance rate for other merchants
         * HKDR01 – HKD remittance rate. How much CNY for 1 HKD
         */
        $toCurrency = strtoupper($toCurrency);
        $fromCurrency = strtoupper($fromCurrency);

        if ($toCurrency == self::SKIP_GET_FX_RATE)
            return FALSE;

        $reverse = ($fromCurrency!='CNY');
        $symbol = ($reverse?$fromCurrency:$toCurrency);
        //$code = ($merchantid==self::HYCM_MERCHANT_ID?'USDR01':'USDR02');
        $code = self::DEFAULT_REMITTANCE_RATE_SYMBOL;   //USD
        //try get symbol from db
        $mercs = $this->getMerchantDetails($merchantid);
        if (isset($mercs['remittance_symbol']) && !empty($mercs['remittance_symbol']) && preg_match("/^$symbol/", $mercs['remittance_symbol'])) {
            $code = trim($mercs['remittance_symbol']);
        }
        //USD to CNY
            /*
        $reverse = false;
        if ($toCurrency!='USD') {
            // try to get reverse rate
            $reverse = TRUE;
        } else {
            //http://fxrate.wecollect.com/service/getrate?symbol=USDCNY&merchantid=ab124bac-a6a4-11e4-8537-0211eb00a4cc&datetime=20160629141501
            //$code = strtoupper(str_replace(' ', '',"$toCurrency$fromCurrency"));
        }
*/
        $timestr = (empty($time)?'':date('YmdHis',strtotime($time)));
        $rurl = sprintf(self::REMITTANCE_RATE_API_URL,urlencode($code), $timestr);
        //cache FX rate to array
        $key1 = $key = sprintf("%s_t%s", $code, $timestr);
        $key.= ($reverse?'_rev':'');
        $this->logger->debug("fxrate_caches", [$this->fxrate_caches]);

        if (isset($this->fxrate_caches[$key]) && $this->fxrate_caches[$key]>0)
            return $this->fxrate_caches[$key];

        if ($this->debug) print("getRmRate: $rurl\n");
        $this->logger->debug("getRmRate: $rurl");

        $jsons = json_decode(file_get_contents($rurl), TRUE);

        if (is_array($jsons) && $jsons['status']===0) {
            //var_dump($jsons);
            $rate = floatval($jsons['rate'][$code]);
            if ($reverse) {
                //Save also non-reverse rate
                $this->fxrate_caches[$key1] = $rate;
                $rate = 1 / $rate;
            }
            $this->fxrate_caches[$key] = $rate;
            if ($returnArray)
                return ['rate'=>$rate, 'timestamp'=>$jsons['rate']['timestamp'], 'code'=>$code ];
            return $rate;
        }
        return FALSE;
    }

    public function getAuthorizedUsers($mid){
        if (empty($mid))
            return FALSE;
        $res = DB::query("SELECT email FROM merchant_users m where is_authorized=1 AND active=1 AND merchant_id=%s ;",
            $mid);
        return $res;
    }

    public function getAuthorizationKey($email, $bid, $hash) {
        return sha1(sprintf("%s%s%d%s", $email, $bid, time(), $hash));
    }

    public function saveAuthorization($mid, $bid, $secret) {
        if (empty($mid) || empty($bid))
            return FALSE;
        /*
        $mercs = $this->getMerchantDetails($mid);
        $email = $mercs['authorized_email'];
        */
        $usrs = $this->getAuthorizedUsers($mid);

        $this->logger->debug("saveAuthorization($mid, $bid, $secret): ",$usrs);
        if (!is_array($usrs)) {
            $this->logger->error("saveAuthorization($mid, $bid, $secret): email not found");
            return FALSE;
        }
        foreach ($usrs as $usr) {
            $email = $usr['email'];
            $id = $this->getAuthorizationKey($email, $bid, $secret);
            $dba = ['id' => $id, 'batch_id' => $bid, 'merchant_id' => $mid, 'email' => $email, 'active' => 0];

            DB::insert(self::DATABASE_TABLE_AUTHORIZATION, $dba);
        }
        //send email in cronjob
    }

    /*
 * active=0, new auth slot
 * 1, email sent
 * 2, authorized by user
 */
    public function getAuthorizationList() {
        $res = DB::query("SELECT a.*, m.first_name, m.last_name FROM %b a left join merchant_users m on (a.email=m.email)  
        where a.active=0 and a.email is not null AND EXISTS (select * from %b where id=a.batch_id and STATUS=%d) order by a.created",
            self::DATABASE_TABLE_AUTHORIZATION, self::DATABASE_TABLE_BATCH, self::BATCH_STATUS_SIGNING);
        return $res;
    }

    /*
     * Get Authorization of signing batch
     */
    public function getAuthorizationDetails($id, $bid='') {
        $res = DB::queryFirstRow("SELECT a.*, m.first_name, m.last_name FROM %b a left join merchant_users m on (a.email=m.email)  
        where (a.id=%s OR a.batch_id=%s) and a.email is not null AND EXISTS (select * from %b where id=a.batch_id and STATUS=%d) order by a.created",
            self::DATABASE_TABLE_AUTHORIZATION, $id, $bid, self::DATABASE_TABLE_BATCH, self::BATCH_STATUS_SIGNING);
        // 1 record only
        return $res;
    }
    /*
     * Email sent: active=1
     */
    public function setAuthorizationStatus($id, $active=1) {
        $dba['active'] = $active;
        if ($active==1) //email sent
            $dba['sent'] = $this->timenow;
        DB::update(self::DATABASE_TABLE_AUTHORIZATION, $dba, "id=%s", $id);
    }

    /*
     * Check if the merchant support local processing & return Local remittance flat fee
     */
    public function getLocalProcessorFee($processor_id, $mid) {
        if (empty($processor_id) || empty($mid))
            return false;

        $res = DB::queryFirstRow("SELECT m.local_remittance_fee as fee FROM %b p, merchants m where p.id=%i and p.function=1 and p.type like 'local' and m.id=%s and m.local_remittance_enabled = 1; ",
            self::DATABASE_TABLE_PROCESSORS, $processor_id, $mid);
        $this->logger->debug("getLocalProcessorFee($processor_id, $mid)", [$res]);

        if (!is_array($res))
            return false;
        return $res['fee'];
    }

    /*
     * Choose GHT or GPay API processor, highest priority = 1
     */
    public function getPreferredApiProcessor($bank) {
        if (empty($bank))
            return false;
        $banks = $this->getBank($bank);
        //gpay_code, cup_code
        $where = new WhereClause('and');
        $where->add('1=1');
        if (empty($banks['gpay_code']))
            $where->add('name COLLATE UTF8_GENERAL_CI NOT LIKE %ss', 'gpay');
        if (empty($banks['cup_code']))
            $where->add('name COLLATE UTF8_GENERAL_CI NOT LIKE %ss', 'GHT ');

        $res = DB::queryFirstRow("SELECT * FROM %b where type like 'local' and priority>0 AND %l order by priority ASC ; ", self::DATABASE_TABLE_PROCESSORS, $where);
        $this->logger->debug("getPreferredApiProcessor", [$res]);

        if (!is_array($res))
            return false;
        return $res['id'];
    }

    public function setPreferredApiProcessor($t) {
        if (! $t>0)
            return false;

        DB::startTransaction();
        DB::update(self::DATABASE_TABLE_PROCESSORS, ['priority'=>1], "type like 'local' AND id=%d", $t);
        //add 1 to other's priority
        DB::update(self::DATABASE_TABLE_PROCESSORS, ['priority'=> DB::sqleval("priority+1")], "type like 'local' AND priority>0 AND id!=%d", $t);
        DB::commit();
        $this->logger->debug("setPreferredApiProcessor($t)");
        return true;
    }

    public function getUsdChargeFee($batch, $usd) {
        // in %
        $charge_rate = (is_null($batch['remittance_fee'])?DEFAULT_REMITTANCE_CHARGE_RATE:$batch['remittance_fee']);
        // service minimum charge
        $min_charge = (is_null($batch['remittance_min_fee'])?0:floatval($batch['remittance_min_fee']));
        $amt = $usd*$charge_rate/100;
        return max($min_charge, $amt);
    }

    //commit by merchant
    public function isCommitedStatus($status) {
        return in_array($status,
            [self::BATCH_STATUS_QUEUED=>'Queued', self::BATCH_STATUS_PROCESS=>'Processing', self::BATCH_STATUS_COMPLETED=>'Completed', self::BATCH_STATUS_DECLINED=>'Declined',
                self::BATCH_STATUS_AUTHORIZED=>'Authorized'] );
    }

    public function getNotificationCfg($uid) {
        if (empty($uid))
            return false;

        $cfg = DB::queryFirstRow("SELECT * FROM %b WHERE active>0 and users_id=%s", self::DATABASE_TABLE_NOTIFICATION, $uid);
        if (!is_null($cfg)) {
            $states = DB::queryOneColumn('state', "SELECT * FROM %b WHERE users_id=%s", self::DATABASE_TABLE_NOTIFICATIONSTATE, $uid);
            $cfg['states'] = $states;

            $this->logger->debug("getNotificationCfg($uid)",$cfg);
            return $cfg;
        }

        return false;
    }

    public function setNotificationCfg($uid, $method='', $states=null, $email='', $url='', $key='') {
        if (empty($uid))
            return false;

        $method = strtolower(trim($method));
        if (empty($method) || count($states)<1) {
            //disable
            DB::update(self::DATABASE_TABLE_NOTIFICATION, ['active'=>0, 'modified'=>$this->timenow], "users_id=%s", $uid);
            return true;
        }
        $cfg = DB::queryFirstRow("SELECT * FROM %b WHERE users_id=%s", self::DATABASE_TABLE_NOTIFICATION, $uid);
        $dba = ['users_id'=>$uid, 'method'=>$method, 'active'=>1, 'modified'=>$this->timenow];
        if (!empty($key))
            $dba['signkey']=trim($key);
        if ($method=='post') {
            $dba['url']=trim($url);
        } elseif ($method=='email') {
            $dba['email']=trim($email);
        }
        if (is_null($cfg)) {
            $dba['created'] = $this->timenow;
            DB::insert(self::DATABASE_TABLE_NOTIFICATION, $dba);
        } else {
            DB::update(self::DATABASE_TABLE_NOTIFICATION, $dba, "users_id=%s", $uid);
        }
        $this->logger->debug("setNotificationCfg($uid)",$dba);
        //remove existing states
        DB::delete(self::DATABASE_TABLE_NOTIFICATIONSTATE, "users_id=%s", $uid);
        foreach ($states as $state)
            DB::insert(self::DATABASE_TABLE_NOTIFICATIONSTATE, ['users_id'=>$uid, 'state'=>$state]);
    }

    /*
    check if notification occurs in batch update
    type = 1, $bid = batch id
    type = 2, $bid = instant_request id , varchar(64)
    */
    public function checkNotificationCfg($merchant_id, $bid, $type=1) {
        if (empty($merchant_id) || empty($bid))
            return false;
        //todo: check notification_log for history
        if ($type==2) {
            //instant_request
            $res = DB::query("SELECT * FROM notification n, notification_state ns WHERE n.active>0 and n.users_id=ns.users_id
        AND n.type = ns.type AND n.type = %s_type
        and n.users_id in (SELECT id FROM `merchant_users` where merchant_id= %s_mid) AND ns.state in (
            SELECT rb.status FROM %b_table rb where id= %s_bid and merchant_id= %s_mid ) ", ['mid' => $merchant_id, 'bid' => $bid, 'type' => $type, 'table'=>self::DATABASE_TABLE_INSTANTREQ]);
        } else {
            // remittance_batch
            $res = DB::query("SELECT * FROM notification n, notification_state ns WHERE n.active>0 and n.users_id=ns.users_id
        AND n.type = ns.type AND n.type = %s_type
        and n.users_id in (SELECT id FROM `merchant_users` where merchant_id= %s_mid) AND ns.state in (
            SELECT rb.status FROM `remittance_batch` rb where id= %s_bid and merchant_id= %s_mid ) ", ['mid' => $merchant_id, 'bid' => $bid, 'type' => $type]);
        }

        $this->logger->debug("checkNotificationCfg($merchant_id, $bid)",$res);
        return $res;
    }
    //call during state change
    public function addNotificationLogs($merchant_id, $bid, $type=1) {
        //skip test merchant
        /*
        if ($merchant_id=='testonly')
            return false;
*/
        // Internal Notification
        if ($type==1) {
            $batch = $this->getBatchDetails($bid);
            if ($batch[0]['status'] == self::BATCH_STATUS_QUEUED) {
                DB::insert(self::DATABASE_TABLE_NOTIFICATIONLOG, ['batch_id' => $bid, 'users_id' => 'internal', 'method' => 'email', 'email' => self::INTERNAL_NOTIFICATION_EMAIL, 'state' => $batch[0]['status'], 'status' => 0, 'retry' => 0, 'created' => $this->timenow]);
            }
        }

        $notifys = $this->checkNotificationCfg($merchant_id, $bid, $type);
        $this->logger->debug("addNotificationLogs($merchant_id, $bid, $type)", [$notifys]);


        if (!is_array($notifys) || count($notifys)==0)
            return false;
        // status 0 = unsent
        foreach ($notifys as $nfs) {
            if ($type==2) {
                // instant_request
                DB::insert(self::DATABASE_TABLE_NOTIFICATIONLOG, ['req_id' => $bid, 'type'=>$type, 'users_id' => $nfs['users_id'], 'method' => $nfs['method'], 'email' => $nfs['email'], 'url' => $nfs['url'], 'signkey' => $nfs['signkey'], 'state' => $nfs['state'], 'status' => 0, 'retry' => 0, 'created' => $this->timenow]);
            } else {
                DB::insert(self::DATABASE_TABLE_NOTIFICATIONLOG, ['batch_id' => $bid, 'type'=>$type, 'users_id' => $nfs['users_id'], 'method' => $nfs['method'], 'email' => $nfs['email'], 'url' => $nfs['url'], 'signkey' => $nfs['signkey'], 'state' => $nfs['state'], 'status' => 0, 'retry' => 0, 'created' => $this->timenow]);
            }
        }
    }

    /*
        // check if notification occurs in batch update
        public function checkNotificationCfg($merchant_id, $bid) {
            if (empty($merchant_id) || empty($bid))
                return false;
            //todo: check notification_log for history
            $res = DB::query("SELECT * FROM notification n, notification_state ns WHERE n.active>0 and n.users_id=ns.users_id
            and n.users_id in (SELECT id FROM `merchant_users` where merchant_id= %s_mid) AND ns.state in (
                SELECT rb.status FROM `remittance_batch` rb where id= %s_bid and merchant_id= %s_mid ) ", ['mid'=>$merchant_id, 'bid'=>$bid]);

            $this->logger->debug("checkNotificationCfg($merchant_id, $bid)",$res);
            return $res;
        }
        //added after state change
        public function addNotificationLogs($merchant_id, $bid) {
            //skip test merchant
            if ($merchant_id=='testonly')
                return false;

            // Internal Notification
            $batch = $this->getBatchDetails($bid);
            if ($batch[0]['status'] == self::BATCH_STATUS_QUEUED) {
                DB::insert( self::DATABASE_TABLE_NOTIFICATIONLOG ,['batch_id'=>$bid, 'users_id'=>'internal', 'method'=>'email', 'email'=>self::INTERNAL_NOTIFICATION_EMAIL, 'state'=>$batch[0]['status'], 'status'=>0, 'retry'=>0, 'created'=>$this->timenow] );
            }

            $notifys = $this->checkNotificationCfg($merchant_id, $bid);
            if (!count($notifys))
                return false;
            // status 0 = unsent
            foreach ($notifys as $nfs)
                DB::insert( self::DATABASE_TABLE_NOTIFICATIONLOG ,['batch_id'=>$bid, 'users_id'=>$nfs['users_id'], 'method'=>$nfs['method'], 'email'=>$nfs['email'], 'url'=>$nfs['url'], 'signkey'=>$nfs['signkey'], 'state'=>$nfs['state'], 'status'=>0, 'retry'=>0, 'created'=>$this->timenow] );
        }
    */
    public function getAllNotifications($method='') {
        if (!empty($method))
            $res = DB::query("SELECT l.*, m.name as merchant_name FROM %b l left join remittance_batch r on (l.batch_id = r.id)
                  left join %b i on (l.req_id = i.id) 
                  left join merchants m on (r.merchant_id=m.id) where l.status=0 and LOWER(method) like %s order by l.created "
                , self::DATABASE_TABLE_NOTIFICATIONLOG, self::DATABASE_TABLE_INSTANTREQ, strtolower($method));
            //$res = DB::query("SELECT * FROM %b where status=0 and LOWER(method) like %s order by created ", self::DATABASE_TABLE_NOTIFICATIONLOG, strtolower($method));
        else
            $res = DB::query("SELECT l.*, m.name as merchant_name FROM %b l left join remittance_batch r on (l.batch_id = r.id)
                  left join %b i on (l.req_id = i.id)
                  left join merchants m on (r.merchant_id=m.id) where l.status=0 order by l.created "
                , self::DATABASE_TABLE_NOTIFICATIONLOG, self::DATABASE_TABLE_INSTANTREQ);

            //$res = DB::query("SELECT * FROM %b where status=0 order by created ", self::DATABASE_TABLE_NOTIFICATIONLOG);
        return $res;
    }

    public function updateNotification($id, $r) {
        if (empty($id))
            return false;
        //sent
        if (!isset($r['status']))
            $r['status'] = 1;
        $r['modified'] = $this->timenow;

        DB::update(self::DATABASE_TABLE_NOTIFICATIONLOG, $r, "id=%s", $id);
        return true;
    }

    public function getNotificationSignature($bid, $state, $key='') {
        //return md5($bid.self::getStatus($state).$key);
        return md5($bid.$state.$key);
    }

    public function getProcessorApiLog($log_id='', $txid='')
    {
        //$this->logger->debug("getProcessorApiLog: ", [$this->merchant_id, $log_id, $txid]);
        if (empty($log_id) && empty($txid))
            return false;

        $res = DB::queryFirstRow("select * from %b where %b=%s order by create_time DESC;", self::DATABASE_TABLE_API_LOG, (empty($log_id)?'req_id':'log_id'), (empty($log_id)?$txid:$log_id));
        if (isset($res['return_msg']))
            $res['return_msg_en'] = self::getProcessorReturnMessageEnglish($res['return_msg']);
        else
            $res['return_msg_en'] = null;
        return $res;
    }

    public function isValidPDF($f) {
        if (!is_readable($f))
            return false;

        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile("$f");
            $details = $pdf->getDetails();
            $this->logger->debug("isValidPDF($f)", $details);
            /*
             *   ["CreationDate"]=>"2016-08-29T10:44:26+02:00", ["Pages"]=>int(1)
             */
        } catch (\Exception $e) {
            //print($e->getMessage());
            return false;
        }
        return is_array($details);
    }

    public function getExcelDate($d) {
        $d=trim($d);
        //20 May 2016 - 24 May 2016
        if (preg_match('/^([\w\s\-\/]+)( to | - )([\w\s\-\/]+)/i', $d, $matches)!=FALSE)	{
            $st = strtotime(trim($matches[1]));
            $ed = strtotime(trim($matches[3]));
            return array(date(self::DATE_FORMAT,$st), date(self::DATE_FORMAT,$ed));
        }
        if (preg_match('/^\d{2,4}(\/|-)\d{1,2}(\/|-)\d{1,2}/', $d)!=FALSE && strtotime($d)!=FALSE) {
            //$this->logger->debug("strtotime($d)", [strtotime($d)]);
            return date(self::DATE_FORMAT, strtotime($d));
        }
        if (is_numeric($d)) {
            $this->logger->debug("getExcelTime($d)", [$this->getExcelTime($d)]);
            return date(self::DATE_FORMAT, $this->getExcelTime($d));
        }
        if (($st=strtotime($d))!=FALSE) //not accept 0
            return date(self::DATE_FORMAT,$st);

        if ($this->debug)	print("getExcelDate($d) INVALID\n");
        return FALSE;
    }

    private function getExcelTime($i){
        $st='2000-1-1';
        $i=intval($i)-36526;
        $t=strtotime("+$i day",strtotime($st));
        return $t;
    }
/*
    private function trimTitle($t) {
        return trim($t," \t\n\r\0\x0B:,;.");
    }
*/
    public function isServiceHour() {
        $now = date('Gi');

        if ($now >= self::SERVICE_CUTOFF_START && $now <= self::SERVICE_CUTOFF_END)
            return false;
        return true;
    }

    /*
     * take instant req tx id
     */
    public function updateAvodaApiCallback($txid, $apid='') {
        if (empty($txid))
            return false;

        if (empty($apid)) {
            $apis = $this->getInstantRequestApiLog($txid);
            if (count($apis) < 1)
                return null;
            //var_dump($apis);
            $apid = $apis[0]['id'];
        }

        $avoda = new AvodaAPI();
        $res = $avoda->query($apid);
        if (!is_array($res))
            return false;
        $updates = ['callback'=>json_encode($res), 'return_code'=>strtolower($res['transState']), 'return_msg'=>$res['fullMessage'], 'status'=>$avoda->isSuccess($res['transState'])];
        $updates['callback_time'] = self::getTimeNow();

        $this->logger->debug(__METHOD__." ($apid, $txid)", $updates);
        DB::update(self::DATABASE_TABLE_API_LOG, $updates, "id=%s", $apid);

        $ir_status = $this->getInstantRequestStatusFromAvodaState($res['transState']);
        $this->setInstantRequestStatus($txid, $ir_status);
        return $ir_status;
    }

    /*
     * Poll the transaction status every 10min until the transaction is in Captured, Declined or Voided.
     */
    public function updateAllAvodaApiCallback($hour = 72) {
        //for instant request only
        $sql = "SELECT * FROM %b WHERE processor LIKE 'avoda' and not lower(return_code) in ('captured','declined','voided')
AND NOT request like '%,\"testTransaction\":1,%'  
AND create_time BETWEEN (now() - interval %d hour) AND (now() - interval 5 minute)
ORDER BY create_time ASC ";
        $res = DB::query($sql, self::DATABASE_TABLE_API_LOG, $hour);
        $this->logger->debug(__METHOD__, ['total'=>count($res)]);

        foreach ($res as $r) {
            //print("$r['']")
            $txid = $r['req_id'];
            $status = $this->updateAvodaApiCallback($txid, $r['id']);
            //revert balance if failed
            if ($status==self::IR_STATUS_FAILED) {
                $ir = $this->getInstantRequest($txid);
                $merchant_id = $ir['merchant_id'];

                $wallet = new MerchantWallet($merchant_id);
                $wallet_id = $wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
                $wallet_sym = $wallet->getWalletCurrency();

                $this->logger->debug(__METHOD__, compact('merchant_id','wallet_id', 'wallet_sym'));
                $wallet->revokeTransaction(MerchantWallet::TYPE_INSTANT_REMITTANCE, $txid);
            }
        }
    }

    public function getInstantRequestStatusFromAvodaState($s) {
        if (empty($s))
            return false;
        $s = strtolower($s);

        $states =[
            'captured'=> self::IR_STATUS_OK,
            'authed' => self::IR_STATUS_PROCESSING,
            'auth_batched' => self::IR_STATUS_PROCESSING,
            'declined' => self::IR_STATUS_FAILED,
            'voided' => self::IR_STATUS_FAILED,
        ];
        if (isset($states[$s]))
            return $states[$s];
        return self::IR_STATUS_PROCESSING;
    }
}

class RemittanceException extends Exception {
    public function errorMessage() {
        //error message
        $errorMsg = $this->getMessage();
        return $errorMsg;
    }
}
?>