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

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

use App\Lib\TransactionFinder;
use App\Lib\TransactionSearchQuery;
use App\Lib\JobMetaHelper;


use App\Lib\Query\QueryHelper;
use App\Lib\Query\QueryIterator;
use App\Lib\Query\CBDataDriver;

class LockedTypeMap extends TypeMap
{
    public function addDefaults(array $types)
    {
        // We override this function to prevent adding incorrect fields.
        //$this->_defaults = $this->_defaults + $types;
    }
}


class ReconcilationQueriesResult
{
    public $transactions;
    public $merchants;
    public $acquirers;
    public $summary; //
    public $currencies = [];

    public $checksum;

    public $totalRecord = 0;
    public $totalAmount = 0;
    public $totalCharge = 0;
    public $totalProcessorFee = 0;
    public $totalNetAmount = 0;
}

/**
 * Class for reconcilation query result.
 */
class ReconcilationQueries extends TransactionSearchQuery
{
    public $transactions;
    public $merchants;
    public $acquirers;
    public $summary;
    public $currency;

    public $totalRecord = 0;

    public function toResult()
    {
        $searchQueries = $this;
        // Counting from database directly
        $total = $searchQueries->totalRecord;

        $acquirers = QueryHelper::map($searchQueries->acquirers, function ($index, $entity) {
            $r = $entity;

            $r['amount'] = round($r['amount'], 2);
            $r['fee'] = round($r['fee'], 2);
            $r['count'] = intval($r['total_tx']);
            $r['net_amount'] = round($r['net_amount'], 2);
            
            return $r;
        });


        $merchants = QueryHelper::map($searchQueries->merchants, function ($index, $entity) {
            $r = $entity;

            $r['payment_amount'] = round($r['payment_amount'], 2);
            $r['payment_fee'] = round($r['payment_fee'], 2);
            $r['payment_count'] = intval($r['payment_total_tx']);
            $r['refund_amount'] = round($r['refund_amount'], 2);
            $r['refund_fee'] = round($r['refund_fee'], 2);
            $r['refund_count'] = intval($r['refund_total_tx']);
            $r['net_amount'] = round($r['net_amount'], 2);

            return $r;
        });


        // Prepare a list of all transaction log id
        $txids = [];

        $currencies = QueryHelper::map($searchQueries->currency);

        // Create data grid content
        // Huge memory usage for dumping all records
        $transactions = QueryHelper::map($searchQueries->transactions, function ($index, $entity) use (&$txids, &$currencies) {
            $r = $entity;

            $roundp = ((isset($r['round_precision']) && $r['round_precision']>=0)?$r['round_precision']:2);

            // We mark all the necessary information only
            $r['processor_fee'] = $r['processor_fee'];
            $r['net_amount'] = round($r['net_amount'], $roundp);
            $r['fee'] = round($r['fee'], $roundp);
            $r['amount'] = round($r['amount'], $roundp);
            $r['convert_amount'] = round($r['convert_amount'], $roundp);
            $r['processor_net_amount'] = round($r['processor_net_amount'], $roundp);

            $r['convert_rate'] = round($r['convert_rate'], 4);

            if (!in_array($r['id'], $txids)) {
                $txids[] = $r['id'];
            }

            return $r;
        });

        $checksum = '';

        $output = new ReconcilationQueriesResult;
        $output->txids = $txids;
        $output->merchants = $merchants;
        $output->acquirers = $acquirers;
        $output->transactions = $transactions;
        $output->totalRecord = $searchQueries->totalRecord;
        $output->currencies = $currencies;

        $output->summary = [];
        // Currency based summary
        QueryHelper::all($searchQueries->summary, function ($index, $entity) use (&$output) {

            $roundp = 2;

            $r = [];
            $r['currency'] = $entity['currency'];
            $r['net_amount'] = round($entity['net_amount'], $roundp);
            $r['fee'] = round($entity['fee'], $roundp);
            $r['amount'] = round($entity['amount'], $roundp);
            $r['processor_fee'] = round($entity['processor_fee'], $roundp);
            $r['processor_net_amount'] = round($entity['processor_net_amount'], $roundp);
            $r['count'] = intval($entity['total_tx']);

            $output->summary[ $r['currency'] ] = $r;
        });
        if (count($output->summary) > 0) {
            $output->checksum = md5('tx'.date('Ymd').'_'.json_encode($output->summary).'_'.json_encode($txids));
        } else {
            $output->checksum = md5('tx'.date('Ymd').'_{}_'.json_encode($txids));
        }
        return $output;
    }
}

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
    protected $searchTool = null;
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

        $this->connection = ConnectionManager::get('default');
        $this->searchTool = new TransactionFinder($this->connection);

        // Initialize
        
        $this->ReconciliationBatch = TableRegistry::get(
            'srl',
            [
                'connection'=> $this->connection,
                'className'=> 'App\Model\Table\ReconciliationTable',
            ]
        );
        
        $this->MerchantWalletService = TableRegistry::get(
            'mws',
            [
                'connection'=> $this->connection,
                'className'=> 'App\Model\Table\MerchantWalletServiceTable',
            ]
        );

        $this->MerchantGroupIdTable = TableRegistry::get('mgid', ['table'=>'merchants_group_id']);

        // Preparing database adapter to QueryHelper.
        QueryHelper::$driver = CBDataDriver::shared($this->connection);
    }

    /**
     * Before filter
     *
     * @param  Event $event the event occurred
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
     * @return void
     */
    public function fetchInfo($format = 'json')
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
        if (!empty($errors) && !empty($errors1)
            && !empty($errors2)&& !empty($errors3)
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
           'data'=>[],
        ];
        // If asking empty search, return nothing
        if (!empty($params)) {
            // For reloading tx, able to get non-pending status for meeting correct result in the view.
            // if (!isset($this->request->query['ignore_settlement_status']) ||
            //     $this->request->query['ignore_settlement_status'] != 'yes') {
                $params['settlement_status'] = 'PENDING';
            // }
            $searchQueries = $this->createQueries($params, ['tx.STATE_TIME'=>'ASC']);
            $total = $searchQueries->totalRecord;

            if ($total > 0) {
                $searchResult = $searchQueries->toResult();
                $response['total'] = $total;
                $response['txids'] = $searchResult->txids;
                $response['data'] = $searchResult->transactions;
                $response['merchants'] = $searchResult->merchants;
                $response['acquirers'] = $searchResult->acquirers;
                $response['currencies'] = $searchResult->currencies;

                $response['checksum'] = $searchResult->checksum;
                $response['summary'] = $searchResult->summary;

                // Try to find out is the result changed from requested.
                if (isset($params['txids'])) {
                    $counter = 0;
                    $matchIds = [];

                    foreach ($params['txids'] as $txid) {
                        if (in_array($txid, $searchResult->txids) && !in_array($txid, $matchIds)) {
                            $counter++;
                            $matchIds[] = $txid;
                        }
                    }

                    // If changed, tell client-side about the update
                    if (count($matchIds) != count($params['txids'])) {
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

        if ($this->request->is('post')) {
            if ($this->request->data('job') !== null) {
                $job_meta = JobMetaHelper::getMeta($this->request->data('job'));

                if (empty($job_meta) || $job_meta['queue'] != $queue_name || $job_meta['task'] != $task_name) {
                    return $this->dataResponse([
                        'status'=>'error',
                        'error'=>[
                            'message'=>__('Job not exist.')
                        ]
                    ]);
                }
                
                $job_data = $job_meta['data'];

                if (empty($job_data['output'])) {
                    return $this->dataResponse([
                        'status'=>$job_meta['status'],
                        'progress'=>$job_meta['progress'],
                        'warning'=>[
                            'message'=>__('Output file not ready.')
                        ]
                    ]);
                }


                if (!empty($job_meta['fail_date'])) {
                    return $this->dataResponse(['status'=>'error','message'=>$job_meta['failure_message']]);
                }

                $output = $job_data['output'];
                $progress = $job_meta['progress']; // A float from 0 to 1


                if (empty($job_meta['complete_date'])) {
                    return $this->dataResponse(['status'=>'progress', 'offset'=> $progress]);
                }

                // If completed
                if (!is_readable($output)) {
                    $this->log("Error to write: $output", 'debug');

                    return $this->dataResponse([
                        'status'=>'error',
                        'error'=>[
                            'message'=>__('Error to find the output file.')
                        ]
                    ]);
                } else {
                    $this->log("uname:".$this->Auth->user('username'), 'debug');
                    $this->log("output file: $output", 'debug');
                    //$this->Flash->success('We will get back to you soon.'. $output);

                    $output_name = basename($output);
                    $xlsurl = Router::url(['controller'=>'SettlementTransaction', 'action' => 'serveFile', 'xls/'.$output_name]);

                    return $this->dataResponse(['status'=>'done','url'=>$xlsurl, 'download' => true, 'name' => $output_name]);
                }
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Unknown status')
                    ]
                ]);
            }
        }


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
            $reqIds = explode(',', trim($reqIds)) ;
        }

        if (empty($reqIds)) {
            $data = ['status'=>'error','type'=>'RequiredFieldsEmpty'];
        } else {
            $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

            $params = [];
            $params['txid'] = $reqIds;
            // Get the transaction list
            $searchQueries = $this->createQueries($params, ['tx.STATE_TIME'=>'ASC']);
            $searchQueries->transactions->orderBy('state_time', 'ASC');

            $searchQueryIterator = QueryHelper::iterator($searchQueries->transactions);
            $total = $searchQueryIterator->totalRecord();


            $data = ['status'=>'error','type'=>'UnknownAction'];
            if ($total > 0) {
                // Export as excel
                $xlsfile = sprintf('xls/ReconciliationBatch-%s', time());

                $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

                // Save into templated Excel file for donwloa all t
                $result2 = $this->resultToExcel($searchQueries, $xlsfile);

                $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                $xlsurl = Router::url(['controller'=>'SettlementTransaction','action' => 'serveFile', $result2['file']]);

                $data = ['status'=>'done', 'msg'=>'Success','path'=>$xlsurl, 'total'=>$total, 'file_path'=>$result2['file_path']];
            }
        }

        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
        $this->log(__METHOD__.'@'.__LINE__.':'."Peak memory usage: ".((memory_get_peak_usage() / 1024 / 1024)<<0).'MB', 'debug');
        return $this->dataResponse($data);
    }

    /**
     * Protected function for writing an excel file for selected transaction in
     * reconciliation.
     *
     * @param      ReconcilationQuery  $searchQueries  The search queries
     * @param      string              $file           The file
     *
     * @return     <type>              ( description_of_the_return_value )
     */
    protected function resultToExcel(ReconcilationQueries $searchQueries, $file)
    {
        // Prepare excel file path
        $tpl = ROOT.DIRECTORY_SEPARATOR .'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'settlement_reconciliation_template.xlsx';

        $file = $file.".xlsx";

        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;

        // TODO: Using cache storage, some of data cell is lost.
        // $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        // $cacheSettings =    array( ' memoryCacheSize ' =>'64MB');
        // PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);




        // // Open excel file
        $excel = PHPExcel_IOFactory::load($tpl);

        // Setup writer
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');

        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');


        $sheet = $excel->getSheet(0);

        // Preparing Summary Tab
        $merchantsIterator = QueryHelper::iterator($searchQueries->merchants);
        $merchants_count = $merchantsIterator->totalRecord();

        $acquirerIterator = QueryHelper::iterator($searchQueries->acquirers);
        $acquirer_count = $acquirerIterator->totalRecord();


        // Prepend the empty rows
        //
        $acquirer_row_from = 2;
        $acquirer_row_to = $acquirer_row_from+$acquirer_count;
        $sheet->insertNewRowBefore($acquirer_row_from+1, $acquirer_count);

        // Set number format
        // Amount
        $sheet->getStyle('C'.($acquirer_row_from).':C'.($acquirer_row_to))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
        ;
        // Fee & Net Amount
        $sheet->getStyle('E'.($acquirer_row_from).':F'.($acquirer_row_to))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
        ;

        $merchant_row_from = 7+$acquirer_count;
        $merchant_row_to = 7+$acquirer_count + $merchants_count;
        $sheet->insertNewRowBefore($merchant_row_from+1, $merchants_count);
        // Set number format
        // Payment Amount
        $sheet->getStyle('C'.($merchant_row_from).':C'.($merchant_row_to))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
        ;
        // Payment Fee, Refund Amount
        $sheet->getStyle('E'.($merchant_row_from).':F'.($merchant_row_to))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
        ;
        // Refund Fee, Net Amount
        $sheet->getStyle('H'.($merchant_row_from).':I'.($merchant_row_to))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
        ;

        ///// merchants /////

        $fields = ['name'=>'Merchant','currency'=>'P. Currency','payment_amount'=>'Payment Amount','payment_total_tx' =>'Payment Count','payment_fee'=>'Payment Fee','refund_amount'=>'Refund Amount','refund_total_tx'=>'Refund Count','refund_fee'=>'Refund Fee','net_amount'=>'Net Amount'];

        $field_names = array_keys($fields);
        $row_offset = $merchant_row_from;

        // $sheet->insertNewRowBefore($row_offset+1, $merchants_count);
        //
        // Insert label at the head
        $last_column = 'A1';
        foreach ($field_names as $column_index => $field_name) {
            $column_id = chr(65+ $column_index);
            $row_id = $row_offset - 1;
            
            $last_column = $column_id.$row_id;
            $sheet->setCellValue($last_column, $fields[$field_name]);
        }
        $sheet->getStyle('A1:'.$last_column)->applyFromArray(['bold'=>true]);
        // Fetch records one by one
        $merchantsIterator->all(function ($index, $entity) use (&$fields, &$field_names, &$sheet, $row_offset) {


            foreach ($field_names as $column_index => $field_name) {
                $column_id = chr(65+ $column_index);
                $row_id = $row_offset + $index;
                
                $sheet->setCellValue($column_id.$row_id, $entity[$field_name]);
            }
        });


        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');


        ///// acquirers /////
        $fields = ['name'=>'Processor','currency'=>'P. Currency','amount'=>'Amount','total_tx'=>'Count','fee'=>'Fee','net_amount'=>'Net Amount'];

        $field_names = array_keys($fields);

        $row_offset = $acquirer_row_from;
        
        // Prepend the empty rows
        // $sheet->insertNewRowBefore($row_offset+1, $acquirer_count);
        //
        // Insert label at the head
        $last_column = 'A1';
        foreach ($field_names as $column_index => $field_name) {
            $column_id = chr(65+ $column_index);
            $row_id = $row_offset - 1;
            
            $last_column = $column_id.$row_id;
            $sheet->setCellValue($last_column, $fields[$field_name]);
        }
        $sheet->getStyle('A1:'.$last_column)->applyFromArray(['bold'=>true]);

        $acquirerIterator->all(function ($index, $entity) use (&$fields, &$field_names, &$sheet, $row_offset) {

            
            foreach ($field_names as $column_index => $field_name) {
                $column_id = chr(65+ $column_index);
                $row_id = $row_offset + $index;
                
                $sheet->setCellValue($column_id.$row_id, $entity[$field_name]);
            }
        });

        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

        ///// Transactions /////
        $sheet = $excel->getSheet(1);

        $transactionIterator = QueryHelper::iterator($searchQueries->transactions);

        $total_record = $transactionIterator->totalRecord();
        $item_per_page = 3000;
        $total_page = ceil($total_record / $item_per_page);

        $row_offset = 2;
        
        $fields = ['state_time'=>'State Time','state'=>'State','customer_name'=>'Customer Name','email'=>'Email','merchantgroup_name'=>'Merchant','merchant'=>'Account','processor_name'=>'Processor','currency'=>'P. Currency','amount'=>'Amount','processor_fee'=>'Processor Fee','processor_net_amount'=>'Net Amount','merchant_ref'=>'Merchant Ref','transaction_id'=>'Transaction ID','product'=>'Product','ip_address'=>'IP Address'];
        $field_names = array_keys($fields);



        $transactionIterator->all(function ($index, $entity) use (&$fields, &$field_names, &$sheet, $row_offset, $total_record) {

            foreach ($field_names as $column_index => $field_name) {
                $column_id = chr(65+ $column_index);
                $row_id =  $row_offset + $index;
                
                $sheet->setCellValue($column_id.$row_id, $entity[$field_name]);
            }
            
            if ($index > 0 && $index %100 == 0) {
                $this->log(__METHOD__.'@'.__LINE__.':'.$index.'/'.$total_record, 'debug');
            }
        });

        // Set number format
        $sheet->getStyle('I'.(2).':K'.(2 + $total_record))
            ->getNumberFormat()->applyFromArray(['code' =>'#,##0.00_-',])
            ;

        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');


        // Erase from memory
        $items = null;
        $fields = null;
        $field_names = null;
        $sheet = null;


        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

        // Set first sheet.
        $excel->setActiveSheetIndex(0);

        // Save to local file
        $writer->save($file_path);


        $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

        return compact('file', 'file_path');
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
        $searchQueries = $this->createQueries($params, ['tx.STATE_TIME'=>'ASC']);
        $searchResult =  $searchQueries->toResult();


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
                'found'=>$searchQueries->totalRecord,
                'matchIds'=>$matchIds,
                'unmatchIds'=>$unmatchIds,
                ], 'error');

            $this->dataResponse([
                'status'=>'error',
                'checksum'=>$searchResult->checksum,
                'type'=>'InvalidTx',
                'found'=>$searchQueries->totalRecord,
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
            $merchantGroupQuery = $this->MerchantGroupIdTable-> find('all', [
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
            
            $wallet = new \MerchantWallet($masterMerchant->merchant_id, $wallet_id, true);

            // Make up who make the changes
            $wallet->username = $user['username'];

            // Get the wallet account
            $walletAccount = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, $merchantgroup['currency']);

            // If any wallet account is not exist, stop the process and prompt the error message .
            if (empty($walletAccount)) {
                // Unlock path
                tryFileUnlock($tokenFp);

                $this->log([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotFound',
                    'merchantgroup'=>$merchantgroup,
                    'master_merchant_id'=>$masterMerchant->merchant_id,
                    'currency'=>$merchantgroup['currency']
                ], 'error');

                $this->dataResponse([
                    'status'=>'error',
                    'type'=>'MasterMerchantWalletNotFound',
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

                $conn->execute('UPDATE '.$this->searchTool->TransactionLog->table().' SET settlement_status = ?, reconciled_state_time = ?, reconciliation_batch_id = ?  WHERE id = ? AND settlement_status = ?', ['AVAILABLE', $state_time->format('Y-m-d H:i:s'), $batch_id, $item['id'], 'PENDING']);
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
                ])->into($this->ReconciliationBatch->table());

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
            $merchantGroupQuery = $this->MerchantGroupIdTable-> find('all', [
                'conditions'=> [
                    'id'=> $merchantgroup['id'],
                    'master'=>'1',
                ] ,
            ]);

            $masterMerchant = $merchantGroupQuery->first();
            
            // Available Balance
            //
            // Wallet ID for settlement available balance and who is the master merchant
            $wallet_id =  strtoupper($merchantgroup['currency']) == 'CNY' ? \MerchantWallet::WALLET_TYPE_SETTLEMENT_CNY : \MerchantWallet::WALLET_TYPE_SETTLEMENT_MERCHANT_CURRENCY;
            
            $wallet = new \MerchantWallet($masterMerchant->merchant_id, $wallet_id, true);

            // Make up who make the changes
            $wallet->username = $user['username'];

            // If the wallet accoutn does not exist, create once.
            $walletAccount = $wallet->getServiceCurrencyWallet(\MerchantWallet::SERVICE_SETTLEMENT, strtoupper($merchantgroup['currency']));
            // if (empty($walletAccount)) {
            //     $wallet->createAccount('Settlement Available Account', 0);
            // }

            // Transaction could be negative for payment/refund.
            // Important: Clarified with Mennas, it should be processor's net amount.
            $wallet->addTransaction($merchantgroup['processor_net_amount'], \MerchantWallet::TYPE_PAYMENT_PROCESSING, $tx_remarks, $batch_id);
            // $wallet->addTransaction($merchantgroup['net_amount'], \MerchantWallet::TYPE_PAYMENT_PROCESSING, $tx_remarks, $batch_id);
        }

        // Step 5 - Release state change token
        tryFileUnlock($tokenFp);
        
        // Send out result
        $this->dataResponse(['status'=>'done']);
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
        
        $merchantGroupQuery = $this->MerchantGroupIdTable-> find('all', [
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
            $masterMerchantQuery = $this->searchTool->Merchants->find('all', [
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
            $merchantGroupQuery =  $this->MerchantGroupIdTable-> find('all', [
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
            $this->MerchantGroupIdTable->save($masterMerchant);
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
     * Creates queries.
     *
     * @param      array  $params  The parameters
     * @param      array  $order   The order
     */
    protected function createQueries($params, $order = [])
    {
        $searchQueries = new ReconcilationQueries();

        $transactionsQuery = $this->searchTool->query($params, null, $order);
        $transactionsQueryIterator = QueryHelper::iterator($transactionsQuery->query);

        $searchQueries->transactions = $transactionsQuery->query;
        $searchQueries->totalRecord = $transactionsQueryIterator->totalRecord();

        $merchantsResult = $this->searchTool->query($params, function ($query) {

            return [
            'mg.id'=>'id',
            'mg.name'=>'name',
            'tx.currency'=>'currency',
            
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN tx.AMOUNT
    ELSE 0 
END) )"=>'payment_amount',
            
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN 1
    ELSE 0 
END) )"=>'payment_total_tx',
            
            "SUM( (CASE 
    WHEN m.settle_option = 3 AND tx.STATE = 'SALE' THEN (tx.wecollect_fee * -1)
    WHEN tx.STATE = 'SALE' THEN (tx.wecollect_fee_cny * -1)
    ELSE 0 
END) )"=>'payment_fee',
            
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN 0
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT
    ELSE 0 
END) )"=>'refund_amount',
            
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN 0
    WHEN tx.STATE = 'REFUNDED' THEN 1
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN 1
    WHEN tx.STATE = 'REFUND_REVERSED' THEN 1
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN 1
    ELSE 0 
END))"=>'refund_total_tx',
            
            "SUM( (CASE 

    WHEN tx.STATE = 'REFUNDED' THEN (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.wecollect_fee_cny
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.wecollect_fee_cny
    ELSE 0 
END) )"=>'refund_fee',

            
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN tx.AMOUNT + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT + tx.wecollect_fee_cny
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT + tx.wecollect_fee_cny
    ELSE 0 
END) )"=>'net_amount',

            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' AND ptx.id IS NULL THEN tx.AMOUNT
    WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN tx.AMOUNT + ptx.fee * -1
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) 
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT
    ELSE 0 
END) )" => 'processor_net_amount',
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN ptx.fee * -1
    ELSE 0 
END) )" => 'processor_fee',
            ];
        }, ['name'=>'asc']);
        // Updated on 19 Sep, Added 'currency' into grouped list.
        $merchantsResult->query->groupBy(['mg.id','tx.currency']);
        $searchQueries->merchants = $merchantsResult->query;


        $currencyResult = $this->searchTool->query($params, function ($query) {
            return [
            'tx.currency'=>'currency',
            ];
        }, ['name'=>'asc']);
        // Updated on 19 Sep, Added 'currency' into grouped list.
        $currencyResult->query->groupBy(['tx.currency']);
        // $merchantsResult->query->selectTypeMap(new LockedTypeMap([
        //     'mg_id'=>'string',
        //     'name'=>'string',
        //     'payment_amount'=>'float',
        //     'payment_count'=>'integer',
        //     'payment_fee'=>'float',
        //     'refund_amount'=>'float',
        //     'refund_count'=>'integer',
        //     'refund_fee'=>'float',
        // ]));
        $searchQueries->currency = $currencyResult->query;

        // Creating acquirer query
        $acquirersResult = $this->searchTool->query($params, function ($query) {

            return  [
                'tx.acquirer_mid'=>'id',
                'pa.name'=>'name',
                'tx.currency'=>'currency',
                
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN (tx.AMOUNT)
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN (tx.ADJUSTMENT)
    WHEN tx.STATE = 'REFUND_REVERSED' THEN (tx.AMOUNT)
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN (tx.ADJUSTMENT)
    ELSE 0 
END) )" => 'amount',
                
            "COUNT( tx.id )"=>'total_tx',
                
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN  ptx.fee * -1
    ELSE 0 
END) )"=>'fee',
                
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' AND ptx.id IS NULL THEN tx.AMOUNT
    WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN tx.AMOUNT + ptx.fee * -1
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) 
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT
    ELSE 0 
END) )"=>'net_amount',
            ];
        }, ['name'=>'asc']);
        // Updated on 19 Sep, Added 'currency' into grouped list.
        $acquirersResult->query->groupBy(['tx.acquirer_mid','tx.currency']);
        // $acquirersResult->query->selectTypeMap(new LockedTypeMap(['id'=>'string','name'=>'string','amount'=>'float','count'=>'integer','fee'=>'float']));
        $searchQueries->acquirers = $acquirersResult->query;

        // Summary, group by currency
        $summaryResult = $this->searchTool->query($params, function ($query) {



            return  [
                "tx.currency"=>"currency",
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN (tx.AMOUNT)
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN (tx.ADJUSTMENT)
    WHEN tx.STATE = 'REFUND_REVERSED' THEN (tx.AMOUNT)
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN (tx.ADJUSTMENT)
    ELSE 0 
END) )" => 'amount',
                
            "SUM( (CASE
    WHEN tx.STATE = 'SALE' THEN tx.wecollect_fee_cny * -1
    WHEN tx.STATE = 'REFUNDED' THEN tx.wecollect_fee_cny * -1
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.wecollect_fee_cny * -1
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.wecollect_fee_cny
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.wecollect_fee_cny
    ELSE '0'
    END)
 )" => 'fee',
                
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN tx.AMOUNT + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT + (tx.wecollect_fee_cny * -1)
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT + tx.wecollect_fee_cny
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT + tx.wecollect_fee_cny
    ELSE 0 
END) )"=>'net_amount',

            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' AND ptx.id IS NULL THEN tx.AMOUNT
    WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN tx.AMOUNT + ptx.fee * -1
    WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) 
    WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT
    WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT
    WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT
    ELSE 0 
END) )" => 'processor_net_amount',
            "SUM( (CASE 
    WHEN tx.STATE = 'SALE' THEN ptx.fee * -1
    ELSE 0 
END) )" => 'processor_fee',
            "COUNT( tx.id )" => 'total_tx',
            ];
        }, ['tx.currency'=>'ASC']);
        $summaryResult->query->groupBy('tx.currency');
        $searchQueries->summary = $summaryResult->query;


        return $searchQueries;
    }

    public function search()
    {
    }

    /**
     * Search all reconciliation batch records.
     *
     */
    public function searchBatch($format = 'json')
    {
        set_time_limit(0);

        $this->log(__METHOD__, 'debug');
        $this->log($this->request->query, 'debug');
        $data = ['status'=>'done'];


        $validator = new Validator();
        $validator
            ->requirePresence('start_date_ts')
            ->date('start_date')
            ->requirePresence('end_date_ts')
            ->date('end_date')
        ;


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
        $query = $this->ReconciliationBatch->find('all');
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

            $query->where([
                'reconcilie_time >='=> $start_time_str,
                'reconcilie_time <='=> $end_time_str,
            ])
            ;
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
                $group = json_decode(json_encode([
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
                ]));

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

        $this->dataResponse([
           'status'=>'done','msg'=>'Success','total'=>count($group_ids), 'data'=>$data,
        ]);
    }

    /**
     * Search all transaction by a batch id
     *
     * @param      string             $format  The format
     *
     * @throws     NotFoundException  (description)
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


        $this->log(__METHOD__, 'debug');
        $this->log($this->request->query, 'debug');

        // Check any requried parameters in http quest search
        $validator = new Validator();
        $validator
            ->requirePresence('reconciliation_batch_id')
        ;


        $errors = $validator->errors($this->request->data);
        if (!empty($errors)) {
            $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
            return;
        }

        // Setup query base
        $params = [ 'reconciliation_batch_id'=>$this->request->data['reconciliation_batch_id']];


        $response =  [
           'status'=>'done',
           'msg'=>'Success',
           'total'=>0,
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
        $searchQueries = $this->createQueries($params, ['reconciled_state_time'=>'asc']);

        $total = $searchQueries->totalRecord;
        if ($total > 0) {
            $searchResult = $searchQueries->toResult();

            $response['merchants'] = $searchResult->merchants;
            $response['acquirers'] = $searchResult->acquirers;
            $response['data'] = $searchResult->transactions;
            $response['txids'] = $searchResult->txids;
        }

        $this->dataResponse($response);
    }
}
