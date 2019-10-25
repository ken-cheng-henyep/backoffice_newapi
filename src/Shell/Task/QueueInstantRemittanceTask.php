<?php
/**
 * @author MGriesbach@gmail.com
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://github.com/MSeven/cakephp_queue
 */

//namespace Queue\Shell\Task;
//namespace Cake\Shell\Task;
namespace App\Shell\Task;

use Queue\Shell\Task\QueueTask;
use Cake\Datasource\ConnectionManager;
use RemittanceReportReader;
use MerchantWallet;

/**
 * QueueTask of InstantRemittance .
 */
class QueueInstantRemittanceTask extends QueueTask {
    /**
     * Timeout for run, after which the Task is reassigned to a new worker.
     *
     * @var int
     */
    public $timeout = 300;   //10;

    /**
     * Number of times a failed instance of this task should be restarted before giving up.
     *
     * @var int
     */
    public $retries = 0;  //no retry
    // public $retries = 1;

    /**
     * Example add functionality.
     * Will create one example job in the queue, which later will be executed using run();
     *
     * @return void
     */
    public function add() {
        $this->out('CakePHP Queue Example task.');
        $this->hr();
        $this->out('To run a Worker use:');
        $this->out('	bin/cake queue runworker');
        $this->out(' ');
        $this->out('You can find the sourcecode of this task in: ');
        $this->out(__FILE__);
        $this->out(' ');

        //$options = getopt('',['id:']);
        /*
         * Adding a task of type 'example' with no additionally passed data
         */
        //if ($this->QueuedJobs->createJob('RemittanceApi', ['id'=>4033, 'batch_id'=>'58e44de0f33a7'])) {
        if ($this->QueuedJobs->createJob('InstantRemittance', null,  ['priority'=>1])) {
            $this->out('OK, job created, now run the worker');
        } else {
            $this->err('Could not create Job');
        }
    }

    /**
     * Example run function.
     * This function is executed, when a worker is executing a task.
     * The return parameter will determine, if the task will be marked completed, or be requeued.
     *
     * @param array $data The array passed to QueuedTask->createJob()
     * @param int|null $id The id of the QueuedTask
     * @return bool Success
     */
    public function run(array $data, $id) {
        $this->out("[$id] RUN Queue InstantRemittance task.");
        //if cannot get lock, wait until get lock or timeout
        $lockfile = ROOT.'/tmp/Remittance.lock';
        $fp = tryFileLock($lockfile);
        if (!$fp) {
            $this->log("[$id] tryFileLock Failed ($lockfile)", 'debug');
            return true;
        }

        $this->out(var_export($data, true));
        /*
         * {"api":"3","merchant_id":"testonly","id":"2674f66f-c7ef-419c-862f-44298525b369"}
         */
        if (empty($data['id']) || empty($data['api']) || empty($data['merchant_id'])) {
            //tryFileUnlock($fp);
            $this->log("[$id] No data", 'debug');
            return true;
        }

        // Get remittance details
        $db_name = ConnectionManager::get('default')->config()['database'];
        $merchantid = $data['merchant_id'];
        $txid = $data['id'];

        $reader = new \RemittanceReportReader($db_name);
        $reader->setMerchant($merchantid);
        $rm = $reader->getInstantRequest($txid);

        $wallet = new MerchantWallet($merchantid);
        $wallet_id = $wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $wallet_sym = $wallet->getWalletCurrency();
        $this->log("Wallet ID:$wallet_id, Currency: $wallet_sym", 'debug');
        // always true for Task finished
        $status = true;

        $this->log("getInstantRequest($txid)", 'debug');
        $this->log($rm, 'debug');

        if (!is_array($rm) || !isset($rm['status']) ) {
            $this->log("[$id] Record Not Found", 'debug');
            tryFileUnlock($fp);
            return true;
        }
        // Check if should be processing by API
        if (! in_array($rm['status'], [\RemittanceReportReader::IR_STATUS_PROCESSING])) {
            $this->log("[$id] Log not ready (status={$rm['status']})", 'debug');
            tryFileUnlock($fp);
            return true;
        }
        // Check API log
        $apis = $reader->getInstantRequestApiLog($txid);
        if (($count=count($apis)) > $this->retries) {
            $this->log("[$txid] found $count times, Reach retry limit ", 'debug');
            tryFileUnlock($fp);
            return true;
        }
        //check & deduct balance
        $paid_amount = ($wallet_sym=='CNY'?$rm['paid_amount']:$rm['convert_paid_amount']);

        $balance_ok = $wallet->checkBalance($paid_amount);
        $this->log("[$txid] amount=$paid_amount, balance_ok=$balance_ok", 'debug');

        if (! $wallet_id || ! $balance_ok) {
            $reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_REJECTED);
            return true;
        }

        $wallet_status = $wallet->addTransaction("-$paid_amount", MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc = '', $txid);
        if (!$wallet_status) {
            $reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_REJECTED);
            return true;
        }

/*
        //test only
        sleep(10);
        $reader->setInstantRequestStatus($txid, \RemittanceReportReader::IR_STATUS_FAILED);
        $paid_amount = $rm['paid_amount'];
        $wallet->addTransaction("$paid_amount", \MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc='Revert balance of failed InstantRequest', $txid);
        $this->out("[$id] TEST DONE");
        tryFileUnlock($fp);
        return true;
        //test end
*/
/**/
        $sleeptm = 5;
        // Call API & log
        switch ($data['api']) {
            case 3: // ChinaGPay API
                $gpay = new \ChinaGPayAPI($prd = false);
                //$gpay = new \ChinaGPayAPI();    //Prd
                $returns  = $gpay->sendRemittance($rm);
                //test
                //$returns['result']=true ;
                break;
            case 7: //GHT
                $sleeptm = 0;
                $ght = new \gaohuitong_pay($prd=false);
                //$ght = new \gaohuitong_pay($prd=true);  //Prd
                $returns  = $ght->sendRemittance($rm);
                break;
            case 13: //Avoda
                // dev only
                $avoda = new \AvodaAPI($prd=true);
                $returns  = $avoda->sendRemittance($rm);
                break;
            case 14: //GeoSwift
            	// dev only
                $geoswift = new \GeoSwiftAPI($prd=true);
                $returns  = $geoswift->sendRemittance($rm);
                break;
            default:
                tryFileUnlock($fp);
                return true;
        }
        $this->log($returns, 'debug');
        // ['result'=>$status, 'return'=> $result, 'order_id'=>$this->orderId];
        if (isset($returns['result']) && $returns['result']==true) {
            /*
             * GPay :1001	交易成功
             * GHT: 0000	交易成功
             */
            $this->log("InstantRequest success ($merchantid, $txid)", 'debug');
            $reader->setInstantRequestStatus($txid, \RemittanceReportReader::IR_STATUS_OK);
            $status = true;
        } elseif (isset($returns['processing']) && $returns['processing']==true) {
            //still Processing, T/F
            $this->log("InstantRequest processing ($merchantid, $txid)", 'debug');
            $reader->setInstantRequestStatus($txid, \RemittanceReportReader::IR_STATUS_PROCESSING);
            // Undo the transaction balance update if API failed
            $status = true;
        } else {
            $this->log("InstantRequest failed ($merchantid, $txid)", 'debug');
            $reader->setInstantRequestStatus($txid, \RemittanceReportReader::IR_STATUS_FAILED);

            // Undo the transaction balance update if API failed
            //$paid_amount = $rm['paid_amount'];
            $wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_INSTANT_REMITTANCE_FAILED_ADJUSTMENT, $dsc='Revert balance of failed InstantRequest', $txid);
        }
        //pause between API calls
        sleep($sleeptm);
        tryFileUnlock($fp);
        return $status;
    }
}
