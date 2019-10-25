<?php

namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Common\Type;

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

use MerchantWallet;

use WC\Query\QueryHelper;
use WC\Backoffice\SettlementService;
use WC\Backoffice\MerchantService;
use WC\Backoffice\TransactionSettlementState;
use WC\Backoffice\ProcessException;

use App\Lib\CakeDbConnector;
use App\Lib\JobMetaHelper;
use App\Lib\CakeLogger;

/**
 * Controller for settlement transaction search
 *
 * @category Controller
 * @package  App\Controller
 * @author   WeCollect <admin@wecollect.com>
 * @license  MIT
 * @link     https://www.wecollect.com/
 */
class SettlementTransactionController extends AppController
{
    protected $extra_columns = [
        ['field'=>'id','title'=>'ID','width'=>100 ],
        ['field'=>'ptx_id','title'=>'PTX ID','width'=>100 ],
        ['field'=>'merchantgroup_id','title'=>'Merchant Group','width'=>150 ],
        ['field'=>'merchant_id','title'=>'Merchant ID','width'=>300 ],
        ['field'=>'merchant_fx_package','title'=>'FX Package','width'=>80 ],
        ['field'=>'processor_state_time','title'=>'Processor State Time','width'=>150, 'template'=> '#= kendo.toString(kendo.parseDate(processor_state_time, "yyyy-MM-dd HH:mm:ss"), "yyyy-MM-dd HH:mm:ss")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'acquirer','title'=>'PC Acquirer','width'=>100 ],
        ['field'=>'acquirer_name','title'=>'PC Acquirer Name','width'=>150 ],
        ['field'=>'acquirer_mid','title'=>'PC Acquirer Mid','width'=>200 ],
        // ['field'=>'processor','title'=>'Processor','width'=>100 ], // duplicate fields
        ['field'=>'processor_fee','title'=>'Processor Fee','width'=>100, 'template'=>'#= kendo.toString(processor_fee, "n2")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'processor_net_amount','title'=>'Processor Net Amount','width'=>100, 'template'=>'#= kendo.toString(net_amount_processor, "n2")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'search_state_time','title'=>'Search State Date','width'=>150, 'template'=> '#= kendo.toString(kendo.parseDate(search_state_time, "yyyy-MM-dd HH:mm:ss"), "yyyy-MM-dd")#','attributes'=>['style'=>"text-align:right;"] ],
    ];

    protected $grid_columns = [
        ['field'=>'state_time','title'=>'State Time','width'=>150, 'template'=> '#= kendo.toString(kendo.parseDate(state_time, "yyyy-MM-dd HH:mm:ss"), "yyyy-MM-dd HH:mm:ss")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'state','title'=>'Trans type','width'=>100],
        ['field'=>'customer_name','title'=>'Customer','width'=>200],
        ['field'=>'email','title'=>'Email','width'=>250],
        ['field'=>'merchant','title'=>'Account','width'=>300],
        ['field'=>'currency','title'=>'P. Currency','width'=>80],
        ['field'=>'amount','title'=>'Amount','width'=>120, 'template'=>'#= kendo.toString(amount, "n2")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'fee','title'=>'Charges','width'=>120, 'template'=>'#= kendo.toString(fee, "n2")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'net_amount','title'=>'Net Amount','width'=>120 , 'template'=> '#= kendo.toString(net_amount, "n2")#','attributes'=>['style'=>"text-align:right;"] ],
        ['field'=>'convert_currency','title'=>'M. Currency','width'=>80],
        ['field'=>'convert_rate','title'=>'FX Rate','width'=>100, 'template'=>'#= kendo.toString(convert_rate, "n4")#','attributes'=>['style'=>"text-align:right;"]],
        ['field'=>'convert_amount','title'=>'Converted Amount','width'=>150, 'template'=>'#= kendo.toString(convert_amount, "n2")#','attributes'=>['style'=>"text-align:right;"]],
        ['field'=>'merchant_ref','title'=>'Merchant Ref.','width'=>300],
        ['field'=>'transaction_id','title'=>'Transaction Id','width'=>350],
        ['field'=>'product','title'=>'Product','width'=>250],
        ['field'=>'ip_address','title'=>'IP Address','width'=>150,'attributes'=>['style'=>"text-align:right;"]],
        ['field'=>'bank_name','title'=>'Bank','width'=>150],
        ['field'=>'bank_card_number','title'=>'Bank Account','width'=>250],
        ['field'=>'verified_name','title'=>'Verified Name','width'=>250],
        ['field'=>'id_card_number','title'=>'ID Number','width'=>150],
        ['field'=>'mobile_number','title'=>'Mobile','width'=>150],
        ['field'=>'settlement_status','title'=>'Settlement Status','width'=>150],
    ];

    protected $columns = [];

    protected $service = null;

    protected $pc_api = null;

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

        // Loading Models
        $this->loadModel('MerchantGroup');


        // Initialize

        $this->pc_api = new \PayConnectorAPI(false);

        $this->connection = ConnectionManager::get('default');

        $logger = CakeLogger::shared();

        // Preparing database adapter to QueryHelper.
        CakeDbConnector::setShared($this->connection);

        $this->service = new SettlementService();
        $this->service->setLogger($logger);
        $this->merchantService = new MerchantService();
        $this->merchantService->setLogger($logger);

        $this->loadModel('MerchantUnsettled');
        $this->loadModel('SettlementBatch');
        $this->loadModel('TransactionLog');
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
        //$this->log("beforeFilter", 'debug');
        //$this->log($event, 'debug');
        // Allow users to register and logout.
        // You should not add the "login" action to allow list. Doing so would
        // cause problems with normal functioning of AuthComponent.

        // Danger: allowing below line make all actions access without credential
        //$this->Auth->allow();
        //

        if (isset($this->request->query['debug']) && $this->request->query['debug'] =='yes') {
            $this->set('debug', true);
            $this->columns = array_merge($this->extra_columns, $this->grid_columns);
        } else {
            $this->columns = array_merge([], $this->grid_columns);
        }

        //
        $user = $this->Auth->user();
        if ($user) {
            $this->Auth->allow();
        }
    }
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {

        // $merchants = $this->paginate($this->Merchants);

        $query =  $this->MerchantGroup
        ->find(
            'list',
            [
                'keyField' => 'id',
                'valueField' => 'name'
            ]
        )
        ->where(['status'=>'1'])
        ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();
        $this->set('merchantgroup_lst', $mercdata);

        $columns = $this->grid_columns;

        if (isset($this->request->query['debug']) && $this->request->query['debug'] == 'yes') {
            $columns = array_merge($this->extra_columns, $this->grid_columns);
            $this->set('debug', 'yes');
        }

        $filter_operator_types = [
        'string'=> [
            'startswith' => 'Start with',
            'endswith' => 'End with',
            'eq' => 'Equal',
            'neq' => 'Not Equal',
            'contains'=> 'Contains',
            'botcontains'=> 'Not Contains',
            'isnull'=>'Is Empty',
            'isnotnull'=>'Not Empty',
        ],
        'number'=> [
            'gte'=> '>=',
            'gt'=> '>',
            'eq' => 'Equal',
            'neq' => 'Not Equal',
            'lte'=> '<=',
            'lt'=> '<',
            'isnull'=>'Is Empty',
            'isnotnull'=>'Not Empty',
        ],
        ];

        foreach ($columns as $idx => $column_info) {
            if (isset($this->service->txFinder->filterable_fields[  $column_info['field'] ])) {

                $filter_info =  $this->service->txFinder-> filterable_fields[  $column_info['field'] ];
                $column_info['type'] = $filter_info['type'];
                $column_info['dataSourceType'] = isset($filter_info['dataSourceType']) ?  $filter_info['dataSourceType'] : [];
                $column_info['filterable'] = [
                'cell'=>[
                    'enabled'=>true,
                ],
                'multi'=> isset($filter_info['multi']) && $filter_info['multi'],
                'checkAll'=> isset($filter_info['checkAll']) ? $filter_info['checkAll'] : true,
                'dataSource'=>isset($filter_info['dataSource']) ?  $filter_info['dataSource'] : [],
                // 'operators'=> [
                // ],
                ];

                // if (isset($filter_operator_types[ $operator_type ])) {
                //     $column_info['filterable']['operators'] = $filter_operator_types[ $operator_type ];
                // }
            } else {
                $column_info['filterable'] = false;
            }

            $columns[ $idx ] = $column_info;
        }


        $this->set('grid_columns', $columns);
    }

    /**
     * Map the object from db to output
     *
     * @param mixed   $entity The data object/array
     * @param integer $index  The index number from the order
     *
     * @return Entity|array
     */
    protected function mappingRow ($entity, $index) {
        $r = $entity;
        if (!isset($r['round_precision'])) {
            $r['round_precision'] = 2;
        }
        $roundp = $r['round_precision'];
        $r['net_amount'] = round($r['net_amount'], $roundp);
        $r['fee'] = round($r['fee'], $roundp);
        $r['amount'] = round($r['amount'], $roundp);
        $r['convert_amount'] = round($r['convert_amount'], $roundp);

        return $r;
    }

    /**
     * Return transaction list in json
     *
     * @param string $format Request format
     *
     * @throws NotFoundException  (description)
     *
     * @return void
     */
    public function search($format = 'json')
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


        // $this->log(__METHOD__, 'debug');
        // $this->log($this->request->query, 'debug');
        // $this->log($this->request->data, 'debug');
        $data = ['status'=>'done'];


        $validator = new Validator();
        $validator
            ->requirePresence('start_date_ts')
            ->date('start_date')
            ->requirePresence('end_date_ts')
            ->date('end_date')
        // ->requirePresence('merchants')
        // ->isArray('merchants')
        // ->requirePresence('states')
        // ->isArray('states')
        ;

        $errors = $validator->errors($this->request->query);
        $errors2 = $validator->errors($this->request->data);
        if (!empty($errors) && !empty($errors2)) {
            $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
            return;
        }

        // Copy all necessary parameters
        $params = [];
        foreach (explode(',', 'txid,settlement_status,start_date,start_date_ts,end_date,end_date_ts,merchants,states,merchantgroups,email,mobile_number,customer_name,transaction_id,merchant_ref,filter') as $field_name) {
            $params[ $field_name ] = null;

            if (isset($this->request->query[$field_name])) {
                $params[ $field_name ] =$this->request->query[$field_name];
            }
            if (isset($this->request->data[$field_name])) {
                $params[ $field_name ] =$this->request->data[$field_name];
            }
        }
        $query = $this->service->txFinder->query($params);

        $selectable_data = [];
        $selectable_filter_columns = [];

        // Provide selectable column data only when requested
        if (isset($this->request->data['columnData']) &&  !empty($this->request->data['columnData'])) {
            $selectable_filter_columns = explode(',', $this->request->data['columnData']);

            foreach ($selectable_filter_columns as $selectable_column_field) {
                $selectable_data[ $selectable_column_field ] = [];


                // if (!isset($this->service->txFinder->filterable_fields [ $selectable_column_field ])) {
                //     continue;
                // }

                // Getting db field name from configuration
                $selectable_db_field = !empty($this->service->txFinder->filterable_fields [ $selectable_column_field ]['db_field']) ? $this->service->txFinder->filterable_fields [ $selectable_column_field ] ['db_field'] : $selectable_column_field;

                // Assign empty array
                $selectable_data[ $selectable_column_field ] = [];

                // $conversion_case = $query
                // ->newExpr($selectable_db_field);

                // Create new search query
                $column_query = $this->service->txFinder->query($params, [ $selectable_db_field => $selectable_column_field ]);
                $column_query->groupBy($selectable_db_field);

                $this->log('Selectable column:'. $selectable_column_field, 'debug');
                // $this->log($column_query->__debugInfo(), 'debug');
                // $this->log($column_query, 'debug');
                //
                $column_res = QueryHelper::map($column_query);

                if (!empty($column_res)) {
                    $selectable_data[ $selectable_column_field ] =  $column_res;
                }
            }
        }

        $result = QueryHelper::create($query);

        // Counting in local memory
        $total = $result->count();

        // Create order by case
        $sorts = [];

        $params2 = [];
        foreach (explode(',', 'sort') as $field_name) {
            $params2[ $field_name ] = null;

            if (isset($this->request->query[$field_name])) {
                $params2[ $field_name ] =$this->request->query[$field_name];
            }
            if (isset($this->request->data[$field_name])) {
                $params2[ $field_name ] =$this->request->data[$field_name];
            }
        }
        if (!empty($params2['sort']) && is_array($params2['sort'])) {
            foreach ($params2['sort'] as $sort_info) {
                $db_field = $sort_info['field'];

                if (isset($this->service->txFinder->sorting_fields[ $db_field ])) {
                    $db_field = $this->service->txFinder->sorting_fields[ $db_field ]['db_field'];
                }
                $sorts[ $db_field ] = strtoupper($sort_info['dir']);
            }
        }

        if (empty($sorts)) {
            $sorts['tx.STATE_TIME'] = 'ASC';
        }

        // $this->log('sorts:'.print_r($sorts, true), 'debug');

        foreach ($sorts as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        // Create data grid content
        if (isset($this->request->query['page']) && isset($this->request->query['pageSize'])) {
            $page_size = intval($this->request->query['pageSize']);
            $start_offset = (intval($this->request->query['page']) -1 ) * $page_size;

            $res = QueryHelper::map($query, [$this, 'mappingRow'], $start_offset, $page_size);

            // $this->log('RequestWithPaging:'.print_r($res, true), 'debug');

        // requested by post for paging
        } elseif (isset($this->request->data['page']) && isset($this->request->data['pageSize'])) {
            $page_size = intval($this->request->data['pageSize']);
            $start_offset = (intval($this->request->data['page']) -1 ) * $page_size;

            $res = QueryHelper::map($query, [$this, 'mappingRow'], $start_offset, $page_size);

            // $this->log('RequestWithPaging:'.print_r($res, true), 'debug');
        } else {
            $res = QueryHelper::map($query, [$this, 'mappingRow']);
        }

        $data = [
           'status'=>'done','msg'=>'Success','total'=>$total, 'data'=>$res ,'selectable_filter_columns'=>$selectable_filter_columns,
        ];
        if (isset($selectable_data)) {
            $data['columnData'] = [];
            foreach ($selectable_data as $selectable_column_field => $available_values) {
                foreach ($available_values as $val) {
                    $data['columnData'][ $selectable_column_field] []= $val;
                }
            }
        }

        return $this->dataResponse($data);
    }

    /**
     * Download all settlement transaction
     *
     * @return void
     */
    public function export()
    {
        $data = ['status'=>'done'];


        $validator = new Validator();
        $validator
        ->requirePresence('start_date_ts')
        ->date('start_date')
        ->requirePresence('end_date_ts')
        ->date('end_date')
        // ->requirePresence('merchants')
        // ->isArray('merchants')
        // ->requirePresence('states')
        // ->isArray('states')
        ;

        $errors = $validator->errors($this->request->query);
        if (!empty($errors)) {
            $this->dataResponse(['status'=>'failure','msg'=>'Missing required fields.']);
            return;
        }

        // Copy all necessary parameters
        $params = [];
        foreach (explode(',', 'txid,settlement_status,start_date,start_date_ts,end_date,end_date_ts,merchants,states,merchantgroups,email,mobile_number,customer_name,transaction_id,merchant_ref,filter,sort') as $field_name) {
            $params[ $field_name ] = isset($this->request->query[$field_name]) ? $this->request->query[$field_name] : null;
        }

        // Export as excel
        $xlsfile = sprintf('xls/SettlementTransaction-%s', time());
        $xlspath = sprintf('%s/%s', TMP, $xlsfile);


        $startDate = new \DateTime( !empty($params['start_date']) ? $params['start_date'] : $params['end_date'] );
        $endDate = new \DateTime( !empty($params['end_date']) ? $params['end_date'] : $params['end_date'] );

        $this->log("Job[$job_id]".'#JobStart:'.self::memoryUsage(), 'debug');
        JobMetaHelper::markStarted($job_id);

        $query = $this->txFinder->query(
            $params,
            null,
            [
                ['field'=>'state_time','dir'=>'ASC'],
            ]
        );

        $total = QueryHelper::$db->count($query);

        $writer = new Writers\TransactionLogWriter(['data'=>compact('params')]);
        $writer->config(QueryHelper::$db, $this->merchantService, $query, $startDate, $endDate);
        if ($writer->save($xlspath)) {

            $xlsurl = Router::url(['action' => 'serveFile', $xlsfile]);
            $data = ['status'=>1, 'msg'=>'Success','path'=>$xlsurl, 'total'=>$total];
        }
        $this->dataResponse($data);
    }

    public function txRelease($format = 'json')
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

        // Get user information
        $user = $this->Auth->user();

        $txids = $this->request->data('txid');
        if (is_string($txids)) {
            $txids = explode(',', trim($txids));
        }
        if (!is_array($txids) || count($txids) < 1) {
            $this->dataResponse(['status'=>'error','type'=>'TransactionIdMissing']);
            return;
        }

        // TODO: Token lock

        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'settlementTransactionChange.lock';

        if (file_exists($tokenLockPath)) {
            $this->dataResponse(['status'=>'error','type'=>'TokenUsed']);
            return;
        }

        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }

        try{
            $query = $this->service->txFinder->query(['txid'=> $txids]);
            $rs = $this->service->resultset($query);

            // TODO: Scan the possible list of transactions
            $updateList = [];
            $masterMerchants = [];
            $rs->all(function($tx) use (&$updateList, &$masterMerchants) {

                $txid = $tx['id'];

                if ($tx['settlement_status'] != TransactionSettlementState::WITHHELD) {
                    throw new ProcessException('DisallowedTransactionSettlementStatus', 'txRelease', 'Transaction settlement status is not allowed.', ['txid'=> $txid]);
                }
                if ($tx['state'] != 'SALE') {
                    throw new ProcessException('DisallowedTransactionState', 'txRelease', 'Transaction state is not allowed.', ['txid'=> $txid]);
                }

                $tx_merchant_id = $tx['merchant_id'];
                if (isset($masterMerchants[ $tx_merchant_id ])) {
                    $masterMerchant = $masterMerchants[ $tx_merchant_id ];
                } else {
                    $masterMerchant = $this->merchantService->getMasterMerchant($tx_merchant_id);
                    if (empty($masterMerchant['id'])) {
                        throw new ProcessException('MasterMerchantNotFound', 'txRelease', 'No master merchant configured for transaction.', ['txid'=> $txid]);
                    }
                    $masterMerchants[ $tx_merchant_id ] = $masterMerchant;
                }


                $currency = $tx['currency'];
                $description = sprintf('Transaction %s is released', $tx['transaction_id']);
                $merchant_id = $masterMerchant['id'];
                $amount = floatval($tx['net_amount']);

                $updateList[] = compact('txid', 'merchant_id', 'description', 'currency', 'amount');
            });

            if (count($updateList) < 1) {
                throw new ProcessException('NoMatchedTransaction', 'txRelease', 'No matched transaction.');
            }

            foreach ($updateList as $info) {
                // TODO: Wallet id
                $wallet = new MerchantWallet($info['merchant_id']);
                $walletId = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $info['currency']);
                if (empty($walletId)) {
                    throw new ProcessException('WalletNotConfigured', 'txRelease', 'No settlement wallet configured for transaction '. $info['txid']);
                }
                if (!$wallet->isWalletExist($walletId)) {
                    throw new ProcessException('WalletNotExist', 'txRelease', 'Unable to find the merchant wallet for transaction '. $info['txid']);
                }

                $newData = [
                    'settlement_status'=>TransactionSettlementState::UNSETTLED,
                    // 'settlement_batch_id'=>null,
                    // 'settle_by'=>null,
                    // 'settle_time'=>null,
                    // 'settlement_rate'=>0,
                ];

                // TODO: Transaction data updates
                $entity = $this->TransactionLog->get($info['txid']);
                $entity = $this->TransactionLog->patchEntity($entity, $newData);

                if (!$this->TransactionLog->save($entity)) {
                    throw new ProcessException('SaveProcessError', 'txRelease', 'Unable to release the transaction '. $info['txid']);
                }
                $wallet->setWallet($walletId);
                $wallet->setUser($user['username']);
                // TODO: Wallet adjustment
                // ($amt, $type, $dsc = '', $refid = '')
                $wallet->addTransaction($info['amount'], MerchantWallet::TYPE_SETTLEMENT_STATUS_ADJUSTMENT, $info['description']);
            }

            $response = ['status'=>'done'];

        }catch(ProcessException $exp){
            $response = ['status'=>'error','type'=>$exp->type, 'msg'=>$exp->getMessage(), 'data'=>$exp->data];

        }catch(Exception $exp) {
            $response = ['status'=>'error','type'=>'Exception', 'msg'=>$exp->getMessage()];
        }

        // TODO: Token unlock
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);


        return $this->dataResponse($response);
    }

    public function txHold($format = 'json')
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

        // Get user information
        $user = $this->Auth->user();

        $txids = $this->request->data('txid');
        if (is_string($txids)) {
            $txids = explode(',', trim($txids));
        }
        if (!is_array($txids) || count($txids) < 1) {
            $this->dataResponse(['status'=>'error','type'=>'TransactionIdMissing']);
            return;
        }

        // TODO: Token lock

        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'settlementTransactionChange.lock';

        if (file_exists($tokenLockPath)) {
            $this->dataResponse(['status'=>'error','type'=>'TokenUsed']);
            return;
        }

        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }

        try{
            $query = $this->service->txFinder->query(['txid'=> $txids]);
            $rs = $this->service->resultset($query);

            // TODO: Scan the possible list of transactions
            $updateList = [];
            $masterMerchants = [];
            $rs->all(function($tx) use (&$updateList, &$masterMerchants) {

                $txid = $tx['id'];

                if ($tx['settlement_status'] != TransactionSettlementState::UNSETTLED) {
                    throw new ProcessException('DisallowedTransactionSettlementStatus', 'txHold', 'Transaction settlement status is not allowed.', ['txid'=> $txid]);
                }
                if ($tx['state'] != 'SALE') {
                    throw new ProcessException('DisallowedTransactionState', 'txHold', 'Transaction state is not allowed.', ['txid'=> $txid]);
                }

                $tx_merchant_id = $tx['merchant_id'];
                if (isset($masterMerchants[ $tx_merchant_id ])) {
                    $masterMerchant = $masterMerchants[ $tx_merchant_id ];
                } else {
                    $masterMerchant = $this->merchantService->getMasterMerchant($tx_merchant_id);
                    if (empty($masterMerchant['id'])) {
                        throw new ProcessException('MasterMerchantNotFound', 'txHold', 'No master merchant configured for transaction.', ['txid'=> $txid]);
                    }
                    $masterMerchants[ $tx_merchant_id ] = $masterMerchant;
                }


                $currency = $tx['currency'];
                $description = sprintf('Transaction %s is released', $tx['transaction_id']);
                $merchant_id = $masterMerchant['id'];
                $amount = floatval($tx['net_amount']) * -1;

                $updateList[] = compact('txid', 'merchant_id', 'description', 'currency', 'amount');
            });

            if (count($updateList) < 1) {
                throw new ProcessException('NoMatchedTransaction', 'txHold', 'No matched transaction.');
            }

            foreach ($updateList as $info) {
                // TODO: Wallet id
                $wallet = new MerchantWallet($info['merchant_id']);
                $walletId = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $info['currency']);
                if (empty($walletId)) {
                    throw new ProcessException('WalletNotConfigured', 'txHold', 'No settlement wallet configured for transaction '. $info['txid']);
                }
                if (!$wallet->isWalletExist($walletId)) {
                    throw new ProcessException('WalletNotExist', 'txHold', 'Unable to find the merchant wallet for transaction '. $info['txid']);
                }

                $newData = [
                    'settlement_status'=>TransactionSettlementState::WITHHELD,
                ];

                // TODO: Transaction data updates
                $entity = $this->TransactionLog->get($info['txid']);
                $entity = $this->TransactionLog->patchEntity($entity, $newData);

                if (!$this->TransactionLog->save($entity)) {
                    throw new ProcessException('SaveProcessError', 'txHold', 'Unable to release the transaction '. $info['txid']);
                }
                $wallet->setWallet($walletId);
                $wallet->setUser($user['username']);
                // TODO: Wallet adjustment
                // ($amt, $type, $dsc = '', $refid = '')
                $wallet->addTransaction($info['amount'], MerchantWallet::TYPE_SETTLEMENT_STATUS_ADJUSTMENT, $info['description']);
            }

            $response = ['status'=>'done'];

        }catch(ProcessException $exp){
            $response = ['status'=>'error','type'=>$exp->type, 'msg'=>$exp->getMessage(), 'data'=>$exp->data];

        }catch(Exception $exp) {
            $response = ['status'=>'error','type'=>'Exception', 'msg'=>$exp->getMessage()];
        }

        // TODO: Token unlock
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);

        return $this->dataResponse($response);
    }

    /**
     * Handling URL Request for download transaction history excel
     *
     * @return void
     */
    public function queueMerchantExport()
    {
        // $this->QueuedJobs = TableRegistry::get('Queue.QueuedJobs');


        $task_name = '\\App\\Tasks\\SettlementTransactionLogExportTask';
        $queue_name = 'excelexport';

        $any_data = false;

        $params = [];
        foreach (explode(',', 'start_date,start_date_ts,end_date,end_date_ts,merchants,states,merchantgroups,settlement_status,email,mobile_number,customer_name,transaction_id,merchant_ref,filter,sort') as $field_name) {
            $params[ $field_name ] = isset($this->request->data[$field_name]) ? $this->request->data[$field_name] : null;
            if (!empty($params[ $field_name ])) {
                $any_data = true;
            }
        }
        $mgids = null;
        if (!empty($this->request->data['merchantgroups'])) {
            if (is_array($this->request->data['merchantgroups'])) {
                $mgids = $this->request->data['merchantgroups'];
            } else {
                $mgids = explode(',', $this->request->data['merchantgroups']);
            }

            $params['merchantgroup_ids'] = $mgids;
        }

        if (!$any_data) {
            return $this->dataResponse(['status'=>'error','error'=>['Not accepted without any parameters.']]);
        }

        if (empty($params['start_date']) && empty($params['start_date_ts'])) {
            return $this->dataResponse(['status'=>'error','error'=>['Not accepted without any parameters.']]);
        }

        if (empty($params['end_date']) && empty($params['end_date_ts'])) {
            return $this->dataResponse(['status'=>'error','error'=>['Not accepted without any parameters.']]);
        }

        $user = $this->Auth->user();
        $type = 'excelexport';

        $job_data = compact('params', 'mgids', 'user', 'type');
        $this->log(__METHOD__.': '. print_r($job_data, true), 'debug');

        // $job = $this->QueuedJobs->createJob('SettlementTransactionLogExport', $job_data);
        // $job_id = $job->id;

        $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

        $this->log("Added Queue Task for SettlementTransactionLogExport. JobID={$job_id}", 'info');

        return $this->dataResponse(['status'=>'added','id'=>$job_id]);
    }
}
