<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Cake\Database\TypeMap;
use Cake\Network\Exception\NotFoundException;
use Cake\Log\Log;

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;


use WC\Query\QueryHelper;
use WC\Backoffice\SettlementService;


use App\Lib\CakeDbConnector;
use App\Lib\JobMetaHelper;
use App\Lib\CakeLogger;


/**
 * The controller to provide reconciliation process.
 *
 * In this process, system will find out all transaction 's settlement_status in "PENDING".
 * These records may be changed their date when admin user select from different date.
 *
 * After the selected list are ready to confirm their final date,
 * admin user can submit the result and mark all the selected reocrds as "AVAILABLE" and mark up the reconiliation_date
 *
 * To preventing overlapping if more than 1 users to control data,
 * Each search result assigned a value "checksum".
 *
 * Admin user promopted with an alert message when submitting the selected list,
 * if any changes from the fetched transaction log id, runtime date, and amount.
 * (Value of checksum is different.)
 */
class ReconciliationController extends AppController
{

    protected $wallet = null;
    protected $connection = null;

    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('Security');`
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        // Load models
        $this->loadModel('Reconcilation');
        $this->loadModel('Merchants');
        $this->loadModel('MerchantGroupId');
        $this->loadModel('MerchantGroup');
        $this->loadModel('MerchantWalletService');
        $this->loadModel('TransactionLog');

        $this->connection = ConnectionManager::get('default');
        
        $logger = CakeLogger::shared();

        // Preparing database adapter to QueryHelper.
        CakeDbConnector::setShared($this->connection);
        $this->service = new SettlementService($logger);


    }

    /**
     * Before filter
     *
     * @param  Event $event the event occurred
     * 
     * @return void
     */
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);

        //
        $user = $this->Auth->user();
        if ($user) {
            $this->Auth->allow();
        }
    }

    /**
     * Index view
     */
    public function index()
    {
    }

    /**
     * Return transaction list in json
     *
     * @param  string $format Request format
     * 
     * @return void
     */
    public function fetchInfo($format = 'json')
    {

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
        'xml' => 'Xml',
        'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);

        $data = ['status'=>'done'];


        $validator = new Validator();
        $validator
            ->requirePresence('start_date_ts')
            ->date('start_date')
            ->requirePresence('end_date_ts')
            ->date('end_date')
        ;

        $validator2 = new Validator();
        $validator2
            ->requirePresence('txid')
        ;


        $errors = $validator->errors($this->request->query);
        $errors1 = $validator->errors($this->request->data);
        $errors2 = $validator2->errors($this->request->query);
        $errors3 = $validator2->errors($this->request->data);
        if (!empty($errors) && !empty($errors1) && !empty($errors2)&& !empty($errors3)
            ) {
            $this->set([
                'response'=>['status'=>'failure','msg'=>'Missing required fields.'],
                '_serialize'=>'response'
            ]);
            return;
        }

        $params = [];

        // Copy all necessary parameters
        foreach (explode(',', 'txid,exclude_txid,start_date,start_date_ts,end_date,end_date_ts,filter,sort') as $field_name) {
            $params[ $field_name ] = null;

            if (!empty($this->request->query[$field_name])) {
                $params[ $field_name ] =$this->request->query[$field_name];
            }
            if (!empty($this->request->data[$field_name])) {
                $params[ $field_name ] =$this->request->data[$field_name];
            }
        }
        $reqIds = [];
        if (isset($params['txid'])) {
            $reqIds = is_string($params['txid']) ? explode(',', trim($params['txid'])) : $params['txid'];
        }

        $response = [
           'status'=>'done',
           'msg'=>'Success',
           'total'=>0,
           'summary'=>null,
           'txids'=>[],
           'checksum'=>'',
           'merchants'=>[],
           'acquirers'=>[],
           'currencies'=>[],
           'unmatchedIds'=>[],
           'data'=>[],
        ];
        $response['txids_changed'] = false;
        // If asking empty search, return nothing
        if (!empty($params)) {
            // For reloading tx, able to get non-pending status for meeting correct result in the view.
            // if (!isset($this->request->query['ignore_settlement_status']) ||
            //     $this->request->query['ignore_settlement_status'] != 'yes') {
                $params['settlement_status'] = 'PENDING';
            // }

            $querySet = $this->service->reconcilationQueryBuilder->create($params, ['tx.STATE_TIME'=>'ASC']);
            $total = $querySet->totalRecord;

            if ($total > 0) {
                $searchResult = $querySet->toResult();
                $response['total'] = $total;
                $response['txids'] = $searchResult->txids;
                $response['data'] = $searchResult->transactions;
                $response['merchants'] = $searchResult->merchants;
                $response['acquirers'] = $searchResult->acquirers;
                $response['currencies'] = $searchResult->currencies;

                $response['checksum'] = $searchResult->checksum;
                $response['summary'] = $searchResult->summary;


                $matchIds = [];
                $unmatchIds = [];

                
                // Try to find out is the result changed from requested.
                if (!empty($reqIds)) {
                    $counter = 0;
                    // Try to find out is all the selection is available from database.
                    for ($i = 0; $i < count($reqIds); $i ++) {
                        if (in_array($reqIds[$i], $searchResult->txids) && !in_array($reqIds[$i], $matchIds)) {
                            $matchIds[] = $reqIds[$i];
                        } else {
                            $unmatchIds[] = $reqIds[$i];
                        }
                    }
    
                    $response['unmatchIds'] = $unmatchIds;
                    
                    // If changed, tell client-side about the update
                    if (count($unmatchIds)> 0) {
                        $response['txids_changed'] = true;
                    }
                }
            }
        }

        $this->dataResponse($response);
    }


    public function queueDownload()
    {

        $task_name = '\\App\\Tasks\\ReconciliationBatchExportTask';
        $queue_name = 'excelexport';

        $any_data = false;

        $params = [];
        foreach (explode(',', 'txid') as $field_name) {
            $params[ $field_name ] = isset($this->request->data[$field_name]) ? $this->request->data[$field_name] : null;
            if (!empty($params[ $field_name ])) {
                $any_data = true;
            }
        }

        if (!$any_data) {
            return $this->dataResponse(['status'=>'error','error'=>['Not accepted without any parameters.']]);
        }

        if (empty($params['txid'])) {
            return $this->dataResponse(['status'=>'error','error'=>['Not accepted without required parameters.']]);
        }

        $job_data = compact('params');
        $this->log(__METHOD__.': '. print_r($job_data, true));

        // $job = $this->QueuedJobs->createJob('SettlementTransactionLogExport', $job_data);
        // $job_id = $job->id;
        
        $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

        $this->log("Added Queue Task for {$task_name}. JobID={$job_id}", 'info');

        return $this->dataResponse(['status'=>'start','id'=>$job_id]);
    }

    /**
     * Download as excel file for the transactions
     *
     */
    public function download()
    {
        // Preventing endless request.
        set_time_limit(600);
        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

        $reqIds = isset($this->request->data['txid'])? $this->request->data['txid']: [];

        if (is_string($reqIds)) {
            $reqIds = explode(',', trim($reqIds));
        }

        $writerData = ['start_date'=>null, 'end_date'=>null];


        if (!empty($this->request->data['start_date'])) {
            $writerData['start_date'] = $this->request->data['start_date'];
        }

        if (!empty($this->request->data['end_date'])) {
            $writerData['end_date'] = $this->request->data['end_date'];
        }

        if (empty($reqIds)) {
            $response = ['status'=>'error','type'=>'RequiredFieldsEmpty'];
        } else {
            $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

            $params = [];
            $params['txid'] = $reqIds;
            // Get the transaction list
            $querySet = $this->service->reconcilationQueryBuilder->create($params, ['tx.STATE_TIME'=>'ASC']);
            $querySet->transactions->orderBy('state_time', 'ASC');

            $searchRs = $this->service->resultset($querySet->transactions);
            $total = $searchRs->count();


            $response = ['status'=>'error','type'=>'UnknownAction'];
            if ($total > 0) {
                // Export as excel
                $xlsfile = sprintf('xls/ReconciliationBatch-%s', time());

                $writer = new \App\Tasks\Writers\ReconcilationBatchWriter($writerData);
                $writer->config($this->service, $querySet);
                $writer->save($xlsfile);
                $result2 = $writer->data();

                $xlsurl = Router::url(['controller'=>'QueueJob','action' => 'serveFile', $result2['file']]);

                $response = ['status'=>'done', 'msg'=>'Success','path'=>$xlsurl, 'total'=>$total];
            }
        }

        return $this->dataResponse($response);
    }


    /**
     * Submit the reconciliation setting by a batch
     */
    public function submit()
    {

        $validator = new Validator();
        $validator
            ->requirePresence('start_date_ts')
            ->date('start_date')
            ->requirePresence('end_date_ts')
            ->date('end_date')
            ->requirePresence('txid')
            ->requirePresence('checksum')
        ;

        $errors = $validator->errors($this->request->data);
        if (!empty($errors)) {
            $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
            return;
        }

        $start_date = new \DateTime(date('Y-m-d H:i:s', $this->request->data['start_date_ts']/1000));
        $start_date->setTime(0, 0, 0);

        $end_date = new \DateTime(date('Y-m-d H:i:s', $this->request->data['end_date_ts']/1000));
        $end_date->setTime(23, 59, 59);


        // Get user information
        $user = $this->Auth->user();

        $batch_id = Text::uuid();


        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'reconciliationProcess.lock';

        // if (file_exists($tokenLockPath)) {
        //     $this->set([
        //         'response' => ['status'=>'error','type'=>'TokenUsed'],
        //         '_serialize' => 'response'
        //     ]);

        //     return;
        // }

        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }
        

        // Step 2 - Check any non "pending" status tx. If any found, release token and stop here
        
        $reqIds = isset($this->request->data['txid'])? $this->request->data['txid']: [];

        if (is_string($reqIds)) {
            $reqIds = explode(',', trim($reqIds)) ;
        }

        // Only pending status transaction can submit reconciliation setting.
        $params = ['txid'=>$reqIds, 'settlement_status'=>'PENDING'];
        $querySet = $this->service->reconcilationQueryBuilder->create($params, ['tx.STATE_TIME'=>'ASC']);
        $searchResult =  $querySet->toResult();


        // If the checksum value is not matched, the selected tx has changes.
        // if (count($searchResult->currencies) != 1) {
        //     // Unlock path
        //     tryFileUnlock($tokenFp);

        //     $this->dataResponse(['status'=>'error','type'=>'MixedCurrencyNotSupported']);
        //     return;
        // }


        $matchIds = [];
        $unmatchIds = [];

        // Try to find out is all the selection is available from database.
        for ($i = 0; $i < count($reqIds); $i ++) {
            if (in_array($reqIds[$i], $searchResult->txids) && !in_array($reqIds[$i], $matchIds)) {
                $matchIds[] = $reqIds[$i];
            } else {
                $unmatchIds[] = $reqIds[$i];
            }
        }

        // If the number of requested tx ids does not match server found result, stop here and prompt user
        if (count($unmatchIds) > 0) {
            // Unlock path
            tryFileUnlock($tokenFp);

                $this->log([
                'status'=>'error',
                'checksum'=>$searchResult->checksum,
                'type'=>'InvalidTx',
                'found'=>$searchResult->totalRecord,
                'matchIds'=>$matchIds,
                'unmatchIds'=>$unmatchIds,
                ], 'error');

            $this->dataResponse([
                'status'=>'error',
                'checksum'=>$searchResult->checksum,
                'type'=>'InvalidTx',
                'found'=>$searchResult->totalRecord,
                'matchIds'=>$matchIds,
                'unmatchIds'=>$unmatchIds,
            ]);
            return;
        }

        // If the checksum value is not matched, the selected tx has changes.
        if (empty($searchResult->checksum) || $searchResult->checksum != $this->request->data['checksum']) {
            // Unlock path
            tryFileUnlock($tokenFp);

                $this->log(['status'=>'error',
                'type'=>'InvalidChecksum',
                'found'=>$searchResult->totalRecord,
                'checksum'=>$searchResult->checksum,
                'summary'=>$searchResult->summary,
                // 'txids'=> $searchResult->txids,
                // 'reqIds'=>$reqIds,
                ], 'error');
            $this->dataResponse(['status'=>'error',
                'type'=>'InvalidChecksum',
                'found'=>$searchResult->totalRecord,
                'checksum'=>$searchResult->checksum,
                'summary'=>$searchResult->summary,
                // 'txids'=> $searchResult->txids,
                // 'reqIds'=>$reqIds,
            ]);
            return;
        }

        // Find all master merchant account
        foreach ($searchResult->merchants as $merchantgroup) {
            $merchantGroupQuery = $this->MerchantGroupId-> find('all', [
                'conditions'=> [
                    'id'=> $merchantgroup['id'],
                    'master'=>'1',
                ] ,
            ]);


            if ($merchantGroupQuery->count() != '1') {
                // Unlock path
                tryFileUnlock($tokenFp);

                $this->log(['status'=>'error','type'=>'MasterMerchantNotFound','merchantgroup'=>$merchantgroup], 'debug');


                $this->dataResponse([
                    'status'=>'error',
                    'checksum'=>$searchResult->checksum,
                    'type'=>'MasterMerchantNotFound',
                    'merchantgroup'=>$merchantgroup,
                    'currency'=>$merchantgroup['currency'],
                ]);
                return;
            }
            $masterMerchant = $merchantGroupQuery->first();

            // $master_merchnats[] = $found_master_merchants[0];
            //
            //
            // Wallet ID for settlement available balance and who is the master merchant
            //
            $wallet_id =  $merchantgroup['currency'] == 'CNY' ? \MerchantWallet::WALLET_TYPE_SETTLEMENT_CNY : \MerchantWallet::WALLET_TYPE_SETTLEMENT_MERCHANT_CURRENCY;
            
            $wallet = new \MerchantWallet($masterMerchant->merchant_id);

            // Make up who make the changes
            $wallet->username = $user['username'];

            // Get the wallet account
            $walletId = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, $merchantgroup['currency']);

            // If any wallet account is not exist, stop the process and prompt the error message .
            if (empty($walletId)) {
                // Unlock path
                tryFileUnlock($tokenFp);

                $this->log([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotConfigured',
                    'merchantgroup'=>$merchantgroup,
                    'master_merchant_id'=>$masterMerchant->merchant_id,
                    'currency'=>$merchantgroup['currency']
                ], 'error');

                $this->dataResponse([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotConfigured',
                    'checksum'=>$searchResult->checksum,
                    'wallet_id'=>$wallet_id,
                    'merchantgroup'=>$merchantgroup,
                    'master_merchant_id'=>$masterMerchant->merchant_id,
                    'currency'=>$merchantgroup['currency']
                ]);
                return;
            }
            
            if (!$wallet->isWalletExist($walletId)) {
                // Unlock path
                tryFileUnlock($tokenFp);

                $this->log([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotExist',
                    'merchantgroup'=>$merchantgroup,
                    'master_merchant_id'=>$masterMerchant->merchant_id,
                    'currency'=>$merchantgroup['currency']
                ], 'error');

                $this->dataResponse([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotExist',
                    'checksum'=>$searchResult->checksum,
                    'wallet_id'=>$wallet_id,
                    'merchantgroup'=>$merchantgroup,
                    'master_merchant_id'=>$masterMerchant->merchant_id,
                    'currency'=>$merchantgroup['currency']
                ]);
                return;
            }
        }



        // Step 3 - Mark the selected transaction from "PENDING" to "AVAILABLE"
        $this->connection->transactional(function ($conn) use ($searchResult, $start_date, $end_date, $batch_id, $user) {

            // Update transaction states
            foreach ($searchResult->transactions as $index => $entity) {
                $item = $entity;//->toArray();

                $state_time = new \DateTime($item['state_time']);

                $in_range = $start_date <= $state_time && $state_time <= $end_date;

                // If the tx out of range, set the tx reconciled_state_date
                if (!$in_range) {
                    $state_time = clone $end_date;
                    $state_time -> setTime(23, 59, 59);
                }

                $conn->execute('UPDATE '.$this->TransactionLog->table().' SET settlement_status = ?, reconciled_state_time = ?, reconciliation_batch_id = ?  WHERE id = ? AND settlement_status = ?', ['AVAILABLE', $state_time->format('Y-m-d H:i:s'), $batch_id, $item['id'], 'PENDING']);
            };

            $from_date = $start_date->format('Y-m-d H:i:s');
            $to_date = $end_date->format('Y-m-d H:i:s');
            $today = (new \DateTime())->format('Y-m-d H:i:s');

            $mysql_driver =new \SQLBuilder\Driver\MySQLDriver();
            $arguments = new \SQLBuilder\ArgumentArray();
            
            $sequence = 1;
            // Insert batch record for each currency
            foreach ($searchResult->summary as $currency => $summaryInfo) {
                $insertQuery = new \SQLBuilder\Universal\Query\InsertQuery();
                $insertQuery->insert([
                        'id'=>$batch_id,
                        'sequence'=>$sequence++,
                        'currency'=>$currency,
                        'from_date'=>$from_date,
                        'to_date'=>$to_date,
                        'reconcilie_time'=>$today,
                        'reconcilie_by'=> $user['username'],
                        'amount'=>$summaryInfo['amount'],
                        'fee'=>$summaryInfo['fee'], // Wecollect Fee
                        'net_amount'=>$summaryInfo['net_amount'], // Amount - Wecollect Fee
                        'processor_fee'=>$summaryInfo['processor_fee'], // Processor fee
                        'processor_net_amount'=>$summaryInfo['processor_net_amount'], // Amount - Processor fee
                        'count'=>$summaryInfo['count'],
                ])->into($this->Reconciliation->table());

                $insertSql = $insertQuery ->toSql($mysql_driver, $arguments);
                $this->log('Submit.batchInsertQuery: '.$insertSql, 'debug');

                $conn->execute($insertSql);
            }
        });

        
        // Step 4 - Send the result into Merchant Wallet - Settlement Available Balance + Currency
        $tx_remarks = 'For transaction date '.$start_date->format('Y/m/d');

        // If the number of days are more than 1
        if ($start_date->format('Y-m-d') != $end_date->format('Y-m-d')) {
            $tx_remarks = 'For transaction dates '.$start_date->format('Y/m/d').' - '.$end_date->format('Y/m/d');
        }

        foreach ($searchResult->merchants as $merchantgroup) {
            $merchantGroupQuery = $this->MerchantGroupId-> find('all', [
                'conditions'=> [
                    'id'=> $merchantgroup['id'],
                    'master'=>'1',
                ] ,
            ]);

            $masterMerchant = $merchantGroupQuery->first();
            
            // Available Balance
            //
            $wallet = new \MerchantWallet($masterMerchant->merchant_id);

            // Make up who make the changes
            $wallet->username = $user['username'];

            // If the wallet accoutn does not exist, create once.
            $walletId = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, strtoupper($merchantgroup['currency']));
            $wallet->setWallet($walletId);
            // if (empty($walletAccount)) {
            //     $wallet->createAccount('Settlement Available Account', 0);
            // }

            // Transaction could be negative for payment/refund.
            // Important: Clarified with Mennas, it should be merchants's net amount (after deduce with wecollect fee).
            // $wallet->addTransaction($merchantgroup['processor_net_amount'], \MerchantWallet::TYPE_PAYMENT_PROCESSING, $tx_remarks, $batch_id);
            $wallet->addTransaction($merchantgroup['net_amount'], \MerchantWallet::TYPE_PAYMENT_PROCESSING, $tx_remarks, $batch_id);
        }

        // Step 5 - Release state change token
        tryFileUnlock($tokenFp);
        
        // Send out result
        $this->dataResponse(['status'=>'done', 'id'=>$batch_id]);
    }

    /**
     * Gets the master merchant wallet.
     *
     * @param      string  $merchantgroup_id  The merchant identifier
     */
    public function getMasterMerchantWallet($merchantgroup_id, $format = 'json')
    {
        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
        'xml' => 'Xml',
        'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);


        // Get user information
        $user = $this->Auth->user();
        
        $merchantGroupQuery = $this->MerchantGroupId-> find('all', [
            'conditions'=> [
                'id'=> $merchantgroup_id,
                'master'=>'1',
            ] ,
        ]);
        $this->log($merchantGroupQuery, 'debug');

        if ($merchantGroupQuery->count() > 1) {
            $this->dataResponse(['status'=>'error','type'=>'MasterMerchantNotDefined','merchantgroup_id'=>$merchantgroup_id]);

            return;
        } elseif ($merchantGroupQuery->count() < 1) {
            $masterMerchantQuery = $this->Merchants->find('all', [
                'join'=>[
                    'mgid'=>[
                        'table'=>'merchants_group_id',
                        'conditions'=>[
                            'mgid.merchant_id = m.id',
                        ]
                    ]
                ],
                'conditions'=>[
                    'mgid.id'=>$merchantgroup_id ,
                    'processor_account_type'=>'1',
                ]
            ]);
            $this->log([__LINE__, $masterMerchantQuery], 'debug');

            if ($masterMerchantQuery->count() < 1) {
                $this->dataResponse(['status'=>'error','type'=>'MasterMerchantNotFound','merchantgroup_id'=>$merchantgroup_id]);

                return;
            }

            $merchant = $masterMerchantQuery->first();
            $this->log($merchant, 'debug');

            // Find the master merchant mapped record
            $merchantGroupQuery =  $this->MerchantGroupId-> find('all', [
                'conditions'=> [
                    'id'=> $merchantgroup_id,
                    'merchant_id'=>$merchant->id,
                ] ,
            ]);

            if ($merchantGroupQuery->count() != 1) {
                $this->dataResponse(['status'=>'error','type'=>'MasterMerchantMapNotFound','merchantgroup_id'=>$merchantgroup_id]);

                return;
            }

            $masterMerchant = $merchantGroupQuery->first();
            $this->log([__LINE__, $masterMerchant], 'debug');
            $masterMerchant->master = '1';
            $this->MerchantGroupId->save($masterMerchant);
        } else {
            $masterMerchant = $merchantGroupQuery->first();
            $this->log([__LINE__, $masterMerchant], 'debug');
        }
        if (empty($masterMerchant) || empty($masterMerchant->merchant_id)) {
            $this->dataResponse(['status'=>'error','type'=>'MasterMerchantEmpty','merchantgroup_id'=>$merchantgroup_id]);
            return;
        }


        // Available Balance
        //
        // Wallet ID for settlement available balance and who is the master merchant
        $wallet = new \MerchantWallet($masterMerchant->merchant_id, self::WALLET_AVAILABLE_BALANCE, true);

        // Make up who make the changes
        $wallet->username = $user['username'];

        // If the wallet accoutn does not exist, create once.
        $walletAccount = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, $currency);
        if (empty($walletAccount)) {
            $wallet->createAccount('Settlement Primary', 0);
            $walletAccount = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, $currency);
        }

        // Create a MerchantWalletService for remark what is the wallet for .
        $mwsData = ['merchant_id'=> $masterMerchant->merchant_id, 'wallet_id'=>self::WALLET_AVAILABLE_BALANCE, 'type'=> \MerchantWallet::SERVICE_SETTLEMENT ];
        $res = $this->MerchantWalletService->find('all')->where($mwsData)->toArray();
        if (count($res) < 1) {
            $entity = $this->MerchantWalletService->newEntity();
            $this->MerchantWalletService->patchEntity($entity, $mwsData);
            $this->MerchantWalletService->save($entity);
        }

        $response = ['status'=>'done', 'master_merchant'=>$masterMerchant, 'wallet'=>$walletAccount];
        $this->dataResponse($response);
    }

    /**
     * Search view
     *
     * @param string $format The requested output format.
     * 
     * @return void
     */
    public function search($format = 'json')
    {
        if ($this->request->is('post')) {

            set_time_limit(0);

            $this->log(__METHOD__, 'debug');
            $this->log($this->request->query, 'debug');
            $data = ['status'=>'done'];


            $validator = new Validator();
            $validator
                ->requirePresence('start_date_ts')
                ->date('start_date')
                ->requirePresence('end_date_ts')
                ->date('end_date');


            $errors = $validator->errors($this->request->query);
            $errors2 = $validator->errors($this->request->data);
            if (!empty($errors) && !empty($errors2)) {
                $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
                return;
            }

            $params = [];
            foreach (explode(',', 'start_date,start_date_ts,end_date,end_date_ts,filter,sort') as $field_name) {
                $params[ $field_name ] = null;

                if (isset($this->request->query[$field_name])) {
                    $params[ $field_name ] =$this->request->query[$field_name];
                }
                if (isset($this->request->data[$field_name])) {
                    $params[ $field_name ] =$this->request->data[$field_name];
                }
            }

            // CakePHP Query Object
            $query = $this->Reconciliation->find('all');
            $query->order(['reconcilie_time'=>'ASC', 'sequence'=>'ASC']);


            // Filter: Date Range (reconciled_state_time)
            $start_date = null;
            $end_date = null;

            // If passing string format date value
            $start_date_str = isset($params['start_date']) ? $params['start_date']:null;
            if (!empty($start_date_str)) {
                $start_date = is_object($start_date_str) && is_subclass_of($start_date_str, 'DateTime') ? $start_date_str : new \DateTime($start_date_str);
            }

            // If passing timestamp format date value
            $start_date_ts_str = isset($params['start_date_ts']) ? $params['start_date_ts']:null;
            if (!empty($start_date_ts_str)) {
                $start_date = new \DateTime(date('Y-m-d H:i:s', intval($start_date_ts_str) / 1000));
            }

            // If passing string format date value
            $end_date_str = isset($params['end_date']) ? $params['end_date']:null;
            if (!empty($end_date_str)) {
                $end_date = is_object($end_date_str) && is_subclass_of($end_date_str, 'DateTime') ? $end_date_str :new \DateTime($end_date_str);
            }

            // If passing timestamp format date value
            $end_date_ts_str = isset($params['end_date_ts']) ? $params['end_date_ts']:null;
            if (!empty($end_date_ts_str)) {
                $end_date = new \DateTime(date('Y-m-d H:i:s', intval($end_date_ts_str) / 1000));
            }

            // Use date filtering when object / value passed
            if (!empty($start_date) || !empty($end_date)) {
                if (empty($start_date)) {
                    if (empty($end_date)) {
                        $start_date = new \DateTime('-2 days');
                    } else {
                        $start_date = clone $end_date;
                        $start_date->sub(new \DateInterval('P1D'));
                    }
                }

                if (empty($end_date)) {
                    $end_date = clone $start_date;
                    $end_date->add(new \DateInterval('P1D'));
                }

                // Set to request day 00:00:00
                $start_date->setTime(0, 0, 0);

                // Set to next day 00:00:00
                $end_date->setTime(0, 0, 0)->add(new \DateInterval('P1D'))->sub(new \DateInterval('PT1S'));

                // Re-format date into string format for database query
                $start_time_str = $start_date->format('Y-m-d H:i:s');
                $end_time_str = $end_date->format('Y-m-d H:i:s');

                $conds = [
                    'reconcilie_time >='=> $start_time_str,
                    'reconcilie_time <='=> $end_time_str,
                ];
                $query->where($conds);
            }


            $_items = $query->toArray();

            // Reorgranize the data into same group by batch id
            // store different amount values by currency under same group
            $grouped = [];
            foreach ($_items as $item) {
                $group =  $item;

                if (isset($grouped [ $item['id'] ])) {
                    $group = $grouped[ $item['id'] ];
                } else {
                    $group_data = [
                        'id'=>$item['id'],
                        'reconcilie_time'=>$item['reconcilie_time'],
                        'reconcilie_by'=>$item['reconcilie_by'],
                        'from_date'=>$item['from_date'],
                        'to_date'=>$item['to_date'],
                        'details'=>[],
                        's_amount'=>'',
                        's_count'=>'',
                        's_fee'=>'',
                        's_net_amount'=>'',
                        's_processor_fee'=>'',
                        's_processor_net_amount'=>'',
                        's_currency'=>'',
                    ];
                    $group = json_decode(json_encode($group_data));

                    $grouped[ $group->id ] = $group;
                }

                if (count($group->details)> 0) {
                    $group->s_amount .= "<br />\n";
                    $group->s_count .= "<br />\n";
                    $group->s_fee .= "<br />\n";
                    $group->s_net_amount .= "<br />\n";
                    $group->s_processor_fee .= "<br />\n";
                    $group->s_processor_net_amount .= "<br />\n";
                    $group->s_currency .= "<br />\n";
                }

                $group->s_amount .= number_format($item['amount'], 2, '.', ',');
                $group->s_count .= $item['count'];
                $group->s_fee .= number_format($item['fee'], 2, '.', ',');
                $group->s_net_amount .= number_format($item['net_amount'], 2, '.', ',');
                $group->s_processor_fee .= number_format($item['processor_fee'], 2, '.', ',');
                $group->s_processor_net_amount .= number_format($item['processor_net_amount'], 2, '.', ',');
                $group->s_currency .= $item['currency'];

                $group->details[] = [
                    'sequence'=>$item['sequence'],
                    'currency'=>$item['currency'],
                    'amount'=>$item['amount'],
                    'count' => $item['count'],
                    'fee'=>$item['fee'],
                    'net_amount'=>$item['net_amount'],
                    'processor_fee'=>$item['processor_fee'],
                    'processor_net_amount'=>$item['processor_net_amount'],
                ];
            }

            $group_ids = array_keys($grouped);
            $data = [];
            // Transalate key-value based group into simple array form.
            foreach ($group_ids as $group_id) {
                $data[] = $grouped[ $group_id ];
            }

            $response = [
                'status'=>'done','msg'=>'Success','total'=>count($group_ids), 'data'=>$data,
            ];
            return $this->dataResponse($response);
        }
    }

    /**
     * Search all transaction by a batch id
     *
     * @param string $format The format
     *
     * @throws NotFoundException  The format incorrect
     * 
     * @return void
     */
    public function searchBatchTransaction($format = 'json')
    {
        
        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);


        // $this->log(__METHOD__, 'debug');
        // $this->log($this->request->query, 'debug');

        // Check any requried parameters in http quest search
        $validator = new Validator();
        $validator->requirePresence('reconciliation_batch_id');


        $errors = $validator->errors($this->request->data);
        if (!empty($errors)) {
            $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
            return;
        }


        $batchRow = $this->Reconciliation->get($this->request->data['reconciliation_batch_id']);

        if (empty($batchRow['id'])) {
            $this->dataResponse(['status'=>'failure','msg'=>'Batch does not exist.']);
            return;
        }

        // Setup query base
        $params = [ 'reconciliation_batch_id'=>$batchRow['id']];


        $response =  [
           'status'=>'done',
           'msg'=>'Success',
           'total'=>0,
           'batch_id'=>$batchRow['id'],
           'from_date'=>$batchRow['from_date']->format('Y-m-d'),
           'to_date'=>$batchRow['to_date']->format('Y-m-d'),
           'txids'=>[],
           'merchants'=>[],
           'acquirers'=>[],
           'data'=>[],
        ];

        // Copy all necessary parameters
        foreach (explode(',', 'start_date,start_date_ts,end_date,end_date_ts,filter,sort') as $field_name) {
            $params[ $field_name ] = null;

            if (isset($this->request->query[$field_name])) {
                $params[ $field_name ] =$this->request->query[$field_name];
            }
            if (isset($this->request->data[$field_name])) {
                $params[ $field_name ] =$this->request->data[$field_name];
            }
        }
        $querySet = $this->service->reconcilationQueryBuilder->create($params, ['reconciled_state_time'=>'asc']);

        $rs = $this->service->resultset($querySet->transactions);
        $total = $rs->count();
        if ($total > 0) {
            $searchResult = $querySet->toResult();

            $response['merchants'] = $searchResult->merchants;
            $response['acquirers'] = $searchResult->acquirers;
            $response['data'] = $searchResult->transactions;
            $response['txids'] = $searchResult->txids;
        }

        $this->dataResponse($response);
    }
}
