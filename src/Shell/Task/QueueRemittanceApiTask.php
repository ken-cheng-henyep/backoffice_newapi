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
use MerchantWallet;

/**
 * A Simple QueueTask example.
 */
class QueueRemittanceApiTask extends QueueTask {
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
        $this->out('This is a very simple example of a QueueTask.');
        $this->out('I will now add an example Job into the Queue.');
        $this->out('This job will only produce some console output on the worker that it runs on.');
        $this->out(' ');
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
        if ($this->QueuedJobs->createJob('RemittanceApi', null)) {
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
        $this->out("[$id] RUN Queue RemittanceApi task.");
        //if cannot get lock, wait until get lock or timeout
        $lockfile = ROOT.'/tmp/Remittance.lock';
        $fp = tryFileLock($lockfile);
        if (!$fp) {
            $this->log("[$id] tryFileLock Failed ($lockfile)", 'debug');
            return true;
        }

        $this->out(var_export($data, true));
        if (empty($data['id']) || empty($data['batch_id'])) {
            //tryFileUnlock($fp);
            $this->log("[$id] No data", 'debug');
            return true;
        }

        // Get remittance details
        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->log("DB: $db_name", 'debug');
        $reader = new \RemittanceReportReader($db_name);
        $batch_id = $data['batch_id'];
        $log_id = $data['id'];

        $this->log("getBatchLog({$log_id}) now", 'debug');
        $rm = $reader->getBatchLog($batch_id, $log_id);
        $batchs = $reader->getBatchDetails($batch_id, $allRec=true);
        //$status = false;
        // true for Task finished
        $status = true;
        $mid = $batchs[0]['merchant_id'];
        $wallet = new MerchantWallet($mid);
        $wallet_id = $wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $wallet_sym = $wallet->getWalletCurrency();
        $this->log("Wallet ID:$wallet_id, Currency: $wallet_sym", 'debug');

        //$this->log("getBatchLog({$log_id})", 'debug');
        $this->log($rm, 'debug');

        if (!is_array($rm) || !isset($rm['status']) || !is_array($batchs)) {
            $this->log("[$id] Record Not Found", 'debug');
            tryFileUnlock($fp);
            return true;
        }
        // Check log if done before
        if (in_array($rm['status'], [\RemittanceReportReader::RM_STATUS_OK, \RemittanceReportReader::RM_STATUS_FAILED])) {
            $this->log("[$id] Log DONE before (status={$rm['status']})", 'debug');
            tryFileUnlock($fp);
            return true;
        }

        //test only
        /*
        sleep(10);
        $reader->setBatchLogStatus($batch_id, $log_id, \RemittanceReportReader::RM_STATUS_FAILED);
        $this->out("[$id] TEST DONE");
        tryFileUnlock($fp);
        return true;
        //test end
*/
        $sleeptm = 10;  //sec
        $this->log("Call API: {$data['api']}", 'debug');

        // Call API & log
        switch ($data['api']) {
            case 3: // ChinaGPay API
                //dev only
                //$gpay = new \ChinaGPayAPI($prd = false);
                $gpay = new \ChinaGPayAPI();
                $returns  = $gpay->sendRemittance($rm);
                //test
                //$returns['result']=true ;
                break;
            case 7: //GHT
                $sleeptm = 3;
		// dev only
                //$ght = new \gaohuitong_pay($prd=false);
                $ght = new \gaohuitong_pay($prd=true);
                $returns  = $ght->sendRemittance($rm);
                break;
            case 13: //Avoda
                // dev only
                $avoda = new \AvodaAPI();
                $returns  = $avoda->sendRemittance($rm);
                break;
            default:
                tryFileUnlock($fp);
                return true;
        }
        $this->log($returns, 'debug');
        // ['result'=>$status, 'return'=> $result, 'order_id'=>$this->orderId];
        if (isset($returns['result']) && $returns['result']==true) {
            //$this->out(' ->Success, the RemittanceApi Job was run.<-');
            $this->log("RM success ($batch_id, $log_id)", 'debug');
            $reader->setBatchLogStatus($batch_id, $log_id, \RemittanceReportReader::RM_STATUS_OK);
            $status = true;
        } else {
            $this->log("RM fail ($batch_id, $log_id)", 'debug');
            $reader->setBatchLogStatus($batch_id, $log_id, \RemittanceReportReader::RM_STATUS_FAILED);
            // Undo transaction deduction
            $paid_amount = ($rm['currency']==$wallet_sym?$rm['paid_amount']:$rm['convert_paid_amount']);
            //$paid_amount = $reader->getBatchLogPaidAmountCny($batch_id, $log_id);
            //$wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT, $dsc = '', $log_id);
            $wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT, $dsc = '', $batch_id);
        }
        // Update batch details
        if ($reader->isAllBatchLogProcessed($batch_id)) {
            // set to completed
            $nextstatus = \RemittanceReportReader::BATCH_STATUS_COMPLETED;
            //updateBatch($id, $rate_update=TRUE, $quote_rate=0, $complete_rate=0)
            $reader->updateBatch($batch_id, FALSE, 0, $batchs[0]['quote_convert_rate']);
            $reader->setBatchStatus($batch_id, $nextstatus);
           /*
            if (\RemittanceReportReader::isValidStateChange($batchs['status'], $nextstatus_int)) {

            }
           */
            //$paid = $reader->getBatchPaidAmount($batch_id);
        }
        //pause between API calls
        sleep($sleeptm);
        tryFileUnlock($fp);
        return $status;
    }
}
