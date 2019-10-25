<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;

/*
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
*/
// Handle remittance transaction history and balance
class MerchantWallet
{
    const DATABASE_USER = 'mysqlu';
    const DATABASE_PASSWORD = '362gQtSA_QA7QroNS';
    const DATABASE_NAME = 'srd_dev';
    const DATABASE_TABLE_TX = 'merchant_transaction';
    const DATABASE_TABLE_ACCOUNT = 'merchant_transaction_account';
    const DATABASE_TABLE_MERCHANT = 'merchants';
    const DATABASE_TABLE_SERVICE = 'merchant_wallet_service';
    const DATABASE_TABLE_RM_NETTING = 'remittance_netting';
    //Service Type
    const SERVICE_REMITTANCE = 1;
    const SERVICE_SETTLEMENT = 2;
    const SERVICE_VERIFICATION = 3;

    const DEFAULT_WALLET_ID = 1; //merchant default account
    const DEFAULT_SYSTEM_USERNAME = 'SYSTEM';
    const STATUS_ACTIVE = 1;
    const ROUND_PRECISION = 2;
    //type of transaction
    /*
1	Opening Balance	The system returns the balance prior to the first transaction at the beginning of the date range.
2	Instant Remittance	The transaction amount including fee (follows the fee calculation whether the merchant or the client bears the fee). The operator is “System”
3	Batch Remittance	The amount of the batch including fee (follows the fee calculation whether the merchant or the client bears the fee). The operator is “System”
4	Admin Update	Ad hoc balance adjustment made by Admin.
     */
    const TYPE_OPEN_BALANCE = 1;
    const TYPE_INSTANT_REMITTANCE = 2;
    //Processing to Failed
    const TYPE_INSTANT_REMITTANCE_FAILED_ADJUSTMENT = 201;
    //Status updated by super admin
    const TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT = 202;

    const TYPE_BATCH_REMITTANCE = 3;
    // whole batch
    const TYPE_BATCH_REMITTANCE_ADJUSTMENT = 301;
    // tx within a batch
    const TYPE_BATCH_REMITTANCELOG_ADJUSTMENT = 302;

    const TYPE_PAYMENT_PROCESSING = 401;

    const TYPE_ADMIN_UPDATE = 4;
    //undo balance change of previous transaction
    const TYPE_REVOKE_BALANCE = 99;

    // Types of wallet id
    const WALLET_TYPE_DEFAULT = 1;
    const WALLET_TYPE_SETTLEMENT_CNY = 4;
    const WALLET_TYPE_SETTLEMENT_MERCHANT_CURRENCY = 5;

    public static $type_mappings = [
        self::TYPE_OPEN_BALANCE => 'Opening Balance',
        self::TYPE_INSTANT_REMITTANCE => 'Instant Remittance',
        self::TYPE_INSTANT_REMITTANCE_FAILED_ADJUSTMENT => 'Instant Remittance Adjustment',
        self::TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT => 'Instant Remittance Adjustment',
        self::TYPE_BATCH_REMITTANCE => 'Batch Remittance',
        self::TYPE_BATCH_REMITTANCE_ADJUSTMENT => 'Batch Remittance Adjustment',
        self::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT => 'Batch Remittance Adjustment',
        self::TYPE_ADMIN_UPDATE => 'Admin Update',
        self::TYPE_REVOKE_BALANCE => 'Balance Revoke',
        self::TYPE_PAYMENT_PROCESSING => 'Payment Processing',
    ];
    //transaction description
    const DSC_BATCH_TX_UPDATE = 'Update TX status';

    /*
     * 1.	remittance
     * 2.	settlement
     * 3.	verification
     */
    public static $service_mappings = [
        self::SERVICE_REMITTANCE => 'remittance',
        self::SERVICE_SETTLEMENT => 'settlement',
        self::SERVICE_VERIFICATION => 'verification',
    ];

    public $db_name, $logger;
    public $merchant_id, $wallet_id;
    public $username;   // 	varchar(64)

    private $EXCEL_TEMP_PATH;
    private $lock_file, $skip_balance_check, $rm_netting;

    function __construct($mid = '', $walletid = 0, $debug = false)
    {
        if (!defined('ROOT')) {
            define('ROOT', dirname(dirname(__DIR__)));
        }

        $this->EXCEL_TEMP_PATH = ROOT .'/tmp/xls/';
        $this->lock_file = ROOT .'/tmp/wallet.lock';
        $this->debug=$debug;
        $this->skip_balance_check = false;
        $this->rm_netting = 0;
/*
        if (!empty($mid)) {
            $this->merchant_id = trim($mid);
            //$this->lock_file = sprintf('%s/tmp/wallet-%s.lock', ROOT, $this->merchant_id);
        }
        $this->wallet_id = (empty($walletid)?self::DEFAULT_WALLET_ID:$walletid);
*/
        $this->logger = new Logger('wallet');
        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/MerchantWallet.log', Logger::DEBUG));
        //$this->file_db = new Flintstone('Remittance', ['dir' => $this->CHINAGPAY_EXCEL_ORDERID_PATH, 'cache'=>false]);
        $this->setDatabase(self::DATABASE_NAME);
        $this->setMerchant($mid);
        $this->setWallet($walletid);
    }

    public static function getTypeName($s)
    {
        if (isset(self::$type_mappings[$s])) {
            return self::$type_mappings[$s];
        }
        return 'N/A';
    }

    public static function getServiceName($s)
    {
        if (isset(self::$service_mappings[$s])) {
            return self::$service_mappings[$s];
        }
        return 'N/A';
    }

    public function setDatabase($db)
    {
        $this->db_name = $db;

        $db_host = 'localhost';

        if (!empty($_SERVER['DB_HOST'])) {
            $db_host = $_SERVER['DB_HOST'];
        }

        if (!empty($_ENV['DB_HOST'])) {
            $db_host = $_ENV['DB_HOST'];
        }
        DB::$host = $db_host;

        DB::$user = self::DATABASE_USER;
        DB::$password = self::DATABASE_PASSWORD;
        DB::$dbName = $this->db_name;
        DB::$encoding = 'utf8';
        DB::$error_handler = false; // since we're catching errors, don't need error handler
        DB::$throw_exception_on_error = true;
    }

    public function setMerchant($mid)
    {
        if (!empty($mid)) {
            $this->merchant_id = trim($mid);
        }

        $merc = DB::queryFirstRow("select * from %b WHERE id=%s AND enabled>0 ;", self::DATABASE_TABLE_MERCHANT, $this->merchant_id);
        if (isset($merc['skip_balance_check']) && $merc['skip_balance_check']>0) {
            $this->skip_balance_check = true;
        }
        if (isset($merc['remittance_netting']) && $merc['remittance_netting']>0) {
            $this->rm_netting = 1;
        }

        $this->logger->debug("setMerchant($mid)", [$merc]);
        return true;
    }

    /*
     * Switch wallet by ID, return false if wallet not exists
     */
    public function setWallet($id) {
        //$this->wallet_id = (empty($id) || !is_numeric($id))?self::DEFAULT_WALLET_ID:$id;
        if (is_numeric($id) && $id>0 && $this->isWalletExist($id)) {
            $this->wallet_id = $id;
            return true;
        }
        if (is_null($this->wallet_id))
            $this->wallet_id = self::DEFAULT_WALLET_ID;
        if ($this->isWalletExist($this->wallet_id))
            return true;
        return false;
    }

    public function isWalletExist($id) {
        /*
        $wallet = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND status=%d ;",
            self::DATABASE_TABLE_ACCOUNT, $this->merchant_id, $id, self::STATUS_ACTIVE);
        */
        $wallet = $this->getWalletDetails($id);
        return is_array($wallet);
    }

    public function setUser($u)
    {
        if (!empty($u)) {
            $this->username = strtolower(trim($u));
        }
    }

    //check if account exists, return null if not
    public function getAccount()
    {
        $account = DB::queryFirstRow(
            "select * from %b WHERE merchant_id=%s AND wallet_id=%d AND status=%d ;",
            self::DATABASE_TABLE_ACCOUNT,
            $this->merchant_id,
            $this->wallet_id,
            self::STATUS_ACTIVE
        );
        return $account;
    }

    //Get all active accounts of the merchant
    public function getAccountsList() {
        $accounts = DB::query("select *, format(balance,2,'en_US') as balance_text from %b WHERE merchant_id=%s AND status=%d ;", self::DATABASE_TABLE_ACCOUNT, $this->merchant_id, self::STATUS_ACTIVE);
        foreach ($accounts as $k=>$r) {
            $this->setWallet($r['wallet_id']);
            $services = $this->getWalletService();
            if (count($services)) {
                $accounts[$k]['service_name'] = implode(',', $services);
            }
        }

        $this->logger->debug("getAccountsList()", $accounts);
        return $accounts;
    }

    /*
     * Get which wallet to use for the service
     */
    public function getServiceWallet($serviceType)
    {
        $wallets = DB::queryFirstRow(
            "select wallet_id from %b WHERE merchant_id=%s AND type=%d ;",
            self::DATABASE_TABLE_SERVICE,
            $this->merchant_id,
            $serviceType
        );
        if (is_array($wallets)) {
            return $wallets['wallet_id'];
        }

        //return self::DEFAULT_WALLET_ID;
        return false;
    }

    /*
     * Get which wallet to use for the service
     *
     * @param      <type>  $serviceType  The service type
     * @param      string  $currency     The currency
     *
     * @return     false|string  The service wallet.
     */
    public function getServiceCurrencyWallet($serviceType, $currency = 'CNY')
    {
        $wallets = DB::queryFirstRow(
            "SELECT a.wallet_id from %b a JOIN %b b ON a.merchant_id = b.merchant_id AND a.wallet_id = b.wallet_id WHERE a.merchant_id=%s AND a.type=%d AND b.currency = %s",
            self::DATABASE_TABLE_SERVICE,
            self::DATABASE_TABLE_ACCOUNT,
            $this->merchant_id,
            $serviceType,
            $currency
        );
        if (is_array($wallets) && !empty($wallets['wallet_id'])) {
            return $wallets['wallet_id'];
        }

        //return self::DEFAULT_WALLET_ID;
        return false;
    }

    /*
    * Get which services allocated to the wallet
    */
    public function getWalletService()
    {
        $types = DB::query(
            "select type from %b WHERE merchant_id=%s AND wallet_id=%d ;",
            self::DATABASE_TABLE_SERVICE,
            $this->merchant_id,
            $this->wallet_id
        );
        if (count($types)) {
            $rtn = array();
            foreach ($types as $t) {
                $rtn[$t['type']] = $this->getServiceName($t['type']);
                //$rtn[] = $t['type'];
            }

            return $rtn;
        }
        return null;
    }

    /*
     * Switch to the wallet for the service
     */
    public function switchServiceWallet($s)
    {
        $wid = $this->getServiceWallet($s);
        if (!$wid) { //wallet not exist
            return false;
        }
        $this->setWallet($wid);
        return $wid;
    }

    public function getWalletDetails($wid=null) {
        if (is_null($wid))
            $wid = $this->wallet_id;

        $account = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND status=%d ;",
            self::DATABASE_TABLE_ACCOUNT, $this->merchant_id, $wid, self::STATUS_ACTIVE);

        return $account;
    }

    public function getWalletCurrency($wid=null) {
        /*
        if (is_null($wid))
            $wid = $this->wallet_id;
        }
        $account = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND status=%d ;",
            self::DATABASE_TABLE_ACCOUNT, $this->merchant_id, $wid, self::STATUS_ACTIVE);
        */
        $account = $this->getWalletDetails($wid);

        return (is_array($account)?strtoupper($account['currency']):null);
    }

    public function createAccount($name, $amt = 0)
    {
        $account = $this->getAccount();
        if (!is_null($account)) {
            $this->logger->debug("createAccount($name) Exists", $account);
            return false;
        }

        $name = trim($name);
        $dba = ['merchant_id'=>$this->merchant_id, 'wallet_id'=>$this->wallet_id, 'name'=>$name, 'status'=> 1, 'balance'=>round($amt, self::ROUND_PRECISION)];
        DB::insert(self::DATABASE_TABLE_ACCOUNT, $dba);

        $this->logger->debug("createAccount($name) Inserted", $dba);
        //must be bank-in for new account
        if ($amt>0) {
            //insert transaction
            $this->addTransaction($amt, self::TYPE_ADMIN_UPDATE, $dsc = 'New Account');
        }
    }

    public function addTransaction($amt, $type, $dsc = '', $refid = '')
    {
        // amt < 0 meant deduct from balance

        $account = $this->getAccount();
        if (is_null($account)) {
            $this->logger->debug("addTransaction Account NOT exist", [$this->merchant_id]);
            return false;
        }
        if (!is_numeric($amt)) {
            return false;
        }

        $this->logger->debug("addTransaction ($amt, $type, $dsc, $refid)");
        $fp = tryFileLock($this->lock_file);
        $dba = array();
        $dba['id'] = 0; // auto incrementing column
        $dba['merchant_id'] = $this->merchant_id;
        $dba['wallet_id'] = $this->wallet_id;
        $dba['type'] = $type;
        if (!empty($dsc)) {
            $dba['remarks'] = substr(trim($dsc), 0, 1024);
        }
            //$dba['dsc'] = trim($dsc);
        $dba['amount'] = round($amt, self::ROUND_PRECISION);
        $dba['balance'] = round(($this->getBalance() + $amt), self::ROUND_PRECISION);
        /*
        if (in_array($type, [self::TYPE_INSTANT_REMITTANCE, self::TYPE_BATCH_REMITTANCE]))
            $dba['username'] = self::DEFAULT_SYSTEM_USERNAME;
        */
        if (!empty($this->username)) {
            $dba['username'] = $this->username;
        } else {
            $dba['username'] = self::DEFAULT_SYSTEM_USERNAME;
        }

        if (!empty($refid)) {
            $dba['ref_id'] = trim($refid);
        }
        $dba['latest'] = 1;

        $this->logger->debug('insert', $dba);
        try {
            DB::startTransaction();
            DB::update(self::DATABASE_TABLE_TX, ['latest' => -1], "merchant_id=%s AND wallet_id=%d", $this->merchant_id, $this->wallet_id);
            DB::insert(self::DATABASE_TABLE_TX, $dba);
            $tx_id = DB::insertId();
            DB::update(self::DATABASE_TABLE_ACCOUNT, ['balance' => $dba['balance']], "merchant_id=%s AND wallet_id=%d", $this->merchant_id, $this->wallet_id);
            DB::commit();
            //DB::rollback();
            $inserts = ['tx_id'=>$tx_id, 'netting_status'=>$this->rm_netting];
            $this->logger->debug(self::DATABASE_TABLE_RM_NETTING, $inserts);

            DB::insert(self::DATABASE_TABLE_RM_NETTING, $inserts);
        } catch (MeekroDBException $e) {
            $this->logger->debug("MeekroDBException", ['error'=>$e->getMessage(), 'query'=>$e->getQuery() ]);
            return false;
        }
        tryFileUnlock($fp);
        $this->logger->debug("addTransaction committed", $dba);
        return true;
    }

    /*
     * type: Type of Transaction to be revoked
     * refid: Batch ID or Batch log ID
     */
    public function revokeTransaction($type, $refid)
    {
        // amt < 0 meant deduct from balance
        if (empty($type) || empty($refid)) {
            return false;
        }

        $account = $this->getAccount();
        if (is_null($account)) {
            $this->logger->debug("addTransaction Account NOT exist", [$this->merchant_id]);
            return false;
        }

        $this->logger->debug("revokeTransaction($type, $refid)");
        //find latest transaction
        $txs = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND type=%d AND ref_id=%s ORDER BY create_time DESC;", self::DATABASE_TABLE_TX, $this->merchant_id, $this->wallet_id, $type, $refid);
        if (is_null($txs)) {
            return false;
        }
        $this->logger->debug("revokeTransaction", $txs);

        $amount = floatval($txs['amount'])*-1;
        return $this->addTransaction($amount, self::TYPE_REVOKE_BALANCE, $dsc = '', $refid);
    }
    /*
     * type: Type of Transaction to be searched
     * refid: Batch ID or Batch log ID
     */
    public function getLastTransaction($type, $refid)
    {
        if (empty($type) || empty($refid)) {
            return false;
        }

        $account = $this->getAccount();
        if (is_null($account)) {
            $this->logger->debug("getLastTransaction Account NOT exist", [$this->merchant_id]);
            return false;
        }

        $this->logger->debug("getLastTransaction($type, $refid)");
        //find latest transaction
        $txs = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND type=%d AND ref_id=%s ORDER BY create_time DESC;", self::DATABASE_TABLE_TX, $this->merchant_id, $this->wallet_id, $type, $refid);
        if (is_null($txs)) {
            return false;
        }
        $this->logger->debug("getLastTransaction", $txs);

        return floatval($txs['amount'])*-1;
    }

    public function getBalance($mid = '', $walletid = 0)
    {
        $this->setMerchant($mid);
        //if ($walletid >0)
        $this->setWallet($walletid);

        $account = $this->getAccount();
        if (is_null($account)) {
            // account not exists or disabled
            $this->logger->debug("getBalance($mid): account not exists or disabled");
            return false;
        }

        $txs = DB::queryFirstRow("select * from %b WHERE merchant_id=%s AND wallet_id=%d AND latest=1 ORDER BY create_time DESC;", self::DATABASE_TABLE_TX, $this->merchant_id, $this->wallet_id);
        $this->logger->debug("getBalance", [$txs]);

        if (is_null($txs)) {
            return 0;
        }

        //return $txs['balance'];
        return round($txs['balance'], self::ROUND_PRECISION);
    }

    /*
     * Return account balance array at a certain time
     */
    public function getPreviousBalances($time, $mid = '', $walletid = 0)
    {
        $this->setMerchant($mid);
        $this->setWallet($walletid);

        $date = date('Y-m-d H:i:s', strtotime($time));
        $account = $this->getAccount();
        if (is_null($account)) {
            // account not exists or disabled
            $this->logger->debug("getPreviousBalance($mid): account not exists or disabled");
            return false;
        }

        $txs = DB::queryFirstRow("select t.*, m.name as merchant_name from %b t, %b m WHERE t.merchant_id=m.id AND merchant_id=%s AND wallet_id=%d AND t.create_time<=%s ORDER BY t.create_time DESC;", self::DATABASE_TABLE_TX, self::DATABASE_TABLE_MERCHANT, $this->merchant_id, $this->wallet_id, $date);
        $this->logger->debug("getPreviousBalance", [$txs]);

        if (is_null($txs))
            return false;

        return $txs;
        //return $txs['balance'];
    }

    /*
     * Return true if balance OK
     */
    public function checkBalance($amt, $mid = '', $walletid = 0)
    {
        $this->setMerchant($mid);
        //if ($walletid >0)
        $this->setWallet($walletid);

        if (! is_numeric($amt)) {
            return false;
        }
        if ($amt <= 0) {
            return true;
        }
        if ($this->skip_balance_check) {
            return true;
        }

        $balance = $this->getBalance($mid, $walletid);
        $amt = round($amt, self::ROUND_PRECISION);
        $this->logger->debug("checkBalance($balance >= $amt)");

        return ($balance >= $amt);
    }

/*
    public function newUuid() {
        // return random UUID
        try {
            $uuid4 = Uuid::uuid4();
            return $uuid4->toString();
        } catch (UnsatisfiedDependencyException $e) {
            $this->logger->debug("newUuid exception: ". $e->getMessage());
            return false;
        }
    }
*/
}
