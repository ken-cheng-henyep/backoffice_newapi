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


use App\Lib\TransactionFinder;
use App\Lib\TransactionSearchQuery;
use App\Lib\JobMetaHelper;

use App\Lib\Query\QueryHelper;
use App\Lib\Query\QueryIterator;
use App\Lib\Query\CBDataDriver;

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
    var $extra_columns = [
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

    var $grid_columns = [
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

    var $columns = [];

    var $searchTool = null;

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



        $this->pc_api = new \PayConnectorAPI(false);

        $connection = ConnectionManager::get('default');

        // Initialize
        QueryHelper::$driver = CBDataDriver::shared($connection);

        $this->searchTool = new TransactionFinder($connection);
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

        $query =  $this->searchTool->MerchantGroup
        ->find(
            'list',
            [
                'keyField' => 'id',
                'valueField' => 'name'
            ]
        )
        // ->where(['status'=>'1'])
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
            if (isset($this->searchTool->filterable_fields[  $column_info['field'] ])) {
                /*
                filterable: {
                        extra: false,
                        operators: {
                            string: {
                                startswith: "Starts with",
                                eq: "Is equal to",
                                neq: "Is not equal to"
                            }
                        }
                    },
                */
                $filter_info =  $this->searchTool-> filterable_fields[  $column_info['field'] ];
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
     * Return transaction list in json
     *
     * @param      string             $format  Request format
     *
     * @throws     NotFoundException  (description)
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


        $this->log(__METHOD__, 'debug');
        $this->log($this->request->query, 'debug');
        $this->log($this->request->data, 'debug');
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
        $result = $this->searchTool->query($params);
        $query = $result->query;

        $selectable_data = [];
        $selectable_filter_columns = [];

        // Provide selectable column data only when requested
        if (isset($this->request->data['columnData']) &&  !empty($this->request->data['columnData'])) {
            $selectable_filter_columns = explode(',', $this->request->data['columnData']);

            foreach ($selectable_filter_columns as $selectable_column_field) {
                $selectable_data[ $selectable_column_field ] = [];


                // if (!isset($this->searchTool->filterable_fields [ $selectable_column_field ])) {
                //     continue;
                // }

                // Getting db field name from configuration
                $selectable_db_field = !empty($this->searchTool->filterable_fields [ $selectable_column_field ]['db_field']) ? $this->searchTool->filterable_fields [ $selectable_column_field ] ['db_field'] : $selectable_column_field;

                // Assign empty array
                $selectable_data[ $selectable_column_field ] = [];

                // $conversion_case = $query
                // ->newExpr($selectable_db_field);

                // Create new search query
                $column_result = $this->searchTool->query($params, [ $selectable_db_field => $selectable_column_field ]);

                $column_query = $column_result->query;
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

        $iterator = QueryHelper::iterator($query);

        // Counting in local memory
        $total = $iterator->totalRecord();

        // if (isset($this->request->query['page']) && isset($this->request->query['pageSize'])) {
        //     $query->limit($this->request->query['pageSize'])
        //     ->page($this->request->query['page']);
        // }



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

                if (isset($this->searchTool->sorting_fields[ $db_field ])) {
                    $db_field = $this->searchTool->sorting_fields[ $db_field ]['db_field'];
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

            $res = QueryHelper::map($query, null, $start_offset, $page_size);

            // $this->log('RequestWithPaging:'.print_r($res, true), 'debug');

        // requested by post for paging
        } elseif (isset($this->request->data['page']) && isset($this->request->data['pageSize'])) {
            $page_size = intval($this->request->data['pageSize']);
            $start_offset = (intval($this->request->data['page']) -1 ) * $page_size;

            $res = QueryHelper::map($query, null, $start_offset, $page_size);

            // $this->log('RequestWithPaging:'.print_r($res, true), 'debug');
        } else {
            $res = QueryHelper::map($query, null);
        }

        if (is_array($res)) {
            //Add data to Array
            foreach ($res as $k => $r) {
                if (!isset($r['round_precision'])) {
                    continue;
                }
                $roundp = ((isset($r['round_precision']) && $r['round_precision']>=0)?$r['round_precision']:2);

                $r['net_amount'] = round($r['net_amount'], $roundp);
                $r['fee'] = round($r['fee'], $roundp);
                $r['amount'] = round($r['amount'], $roundp);
                $r['convert_amount'] = round($r['convert_amount'], $roundp);
                $res[$k] = $r;
            }
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
    public function download()
    {

        $this->log(__METHOD__, 'debug');
        $this->log($this->request->query, 'debug');
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
        $result = $this->searchTool->query($params, null, ['state_time'=>'asc']);
        $query = $result->query;

        // $this->log($query->__debugInfo(), 'debug');
        // $this->log($query, 'debug');


        // Count total record
        // $total = $query->count();

        // Create data grid content
        $res = $query->toArray();

        $total = count($res);
        if ($total > 0) {
            // Header
            $rows = [];



            // Data Grid
            foreach ($res as $k => $r) {
                $roundp = ((isset($r['round_precision']) && $r['round_precision']>=0)?$r['round_precision']:2);

                $r['net_amount'] = round($r['net_amount'], $roundp);
                $r['fee'] = round($r['fee'], $roundp);
                $r['amount'] = round($r['amount'], $roundp);
                $r['convert_amount'] = round($r['convert_amount'], $roundp);

                $row = [];
                foreach ($this->columns as $column_info) {
                    $column_field = $column_info['field'];
                    $column_label = $column_info['title'];

                    $row[$column_label] = (string) ( isset($r [ $column_field ]) ? $r [ $column_field ] : '');
                }


                $rows [] = $row;
            }

            // Export as excel
            $xlsfile = sprintf('xls/SettlementTransaction-%s', time());
            $xlspath = sprintf('%s/%s', TMP, $xlsfile);

            $sheet_name = 'Settlement Transaction';
            $xlspath = fromArrayToSpoutExcel([$sheet_name => $rows], $xlspath);
            $xlsfile = str_replace(TMP, '', $xlspath);
            $this->log("xlsx: $xlsfile", 'debug');

            $xlsurl = Router::url(['action' => 'serveFile', $xlsfile]);
            $data = ['status'=>1, 'msg'=>'Success','path'=>$xlsurl, 'total'=>$total];
        }

        $this->dataResponse($data);
    }


    /**
     * Serve file in tmp folder
     *
     * @param  string $f File path
     * @return Resposne The response object
     */
    public function serveFile($f)
    {
        //$path = Configure::read('WC.data_path').$f;
        $path = TMP.$f;
        $this->log("serveFile($path)", 'debug');

        if (!is_readable($path)) {
            throw new \Exception('Requested file is not exist or has been removed.');
        } else {
            $this->response->file($path, ['download' => true, 'name' => basename($f)]);
        }

        return $this->response;
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


        // $validator = new Validator();
        // $validator
        //     ->requirePresence('mgid')
        //     // ->requirePresence('merchants')
        //     // ->isArray('merchants')
        //     // ->requirePresence('states')
        //     // ->isArray('states')
        // ;

        // $errors = $validator->errors($this->request->query);
        // if (!empty($errors)) {

        //     $this->set([
        //         'response'=>['status'=>'failure','msg'=>'Missing required fields.'],
        //         '_serialize'=>'response'
        //         ]);
        //     return;
        // }

        // $mgid = $this->request->query['mgid'];

        // $start_date = isset( $this->request->query['start_date'])  ?  $this->request->query['start_date'] : null;
        // $end_date = isset($this->request->query['end_date']) ? $this->request->query['end_date']: null;

        // if (empty($start_date)) $start_date = date('Y-m-d');
        // if (empty($end_date)) $end_date = date('Y-m-d');


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

            $params['merchantgroups'] = $mgids;
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
        $this->log(__METHOD__.': '. print_r($job_data, true));

        // $job = $this->QueuedJobs->createJob('SettlementTransactionLogExport', $job_data);
        // $job_id = $job->id;

        $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

        $this->log("Added Queue Task for SettlementTransactionLogExport. JobID={$job_id}", 'info');

        return $this->dataResponse(['status'=>'added','id'=>$job_id]);
    }
}
