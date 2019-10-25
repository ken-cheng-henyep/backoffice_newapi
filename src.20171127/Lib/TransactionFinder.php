<?php

namespace App\Lib;

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Event\Event ;

use Cake\Log\Log;

// Changed to SQLBuilder for building correct DAO and prevent cakephp unknown bug about ordering.
use SQLBuilder\Universal\Query\SelectQuery;

class TransactionFinder
{
    
    public $filterable_fields = [
        'merchantgroup_id'=>['db_field'=>'mg.id','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [] ],
        'merchant_fx_package'=>['db_field'=>'m.settle_option','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [
            ['merchant_fx_package'=>'1'],
            ['merchant_fx_package'=>'2'],
        ]],
        'processor'=>['db_field'=>'processor','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [] ],
        'acquirer'=>['db_field'=>'acquirer','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [] ],
        'acquirer_mid'=>['db_field'=>'acquirer_mid','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [] ],
        'acquirer_name'=>['db_field'=>'acquirer_name','type'=>'string',  'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [] ],
        'state'=>['db_field'=>'tx.STATE','type'=>'string','multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource'=> [
            ['state'=>'SALE'],
            ['state'=>'REFUNDED'],
            ['state'=>'PARTIAL_REFUND'],
            ['state'=>'REFUND_REVERSED'],
            ['state'=>'PARTIAL_REFUND_REVERSED'],
        ]],
        'customer_name'=>['db_field'=> "CONCAT(tx.FIRST_NAME, ' ', tx.LAST_NAME)", 'db_field_type'=>'complex',  'type'=>'string' , 'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource' => [] ],
        'email'=>['db_field'=>'email','type'=>'string' , 'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource' => [] ],
        'account'=>['db_field'=>'m.name','type'=>'string' , 'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource' => [] ],
        'merchant_ref'=>['db_field'=>'merchant_ref','type'=>'string'],
        'ip_address'=>['db_field'=>'ip_address','type'=>'string'],
        'bank_name'=>['db_field'=>'b.name','type'=>'string' , 'multi'=>true, 'checkAll'=> true, 'dataSourceType'=>'query','dataSource' => [] ],
        'bank_account'=>['db_field'=>'tx.card_number','type'=>'string'],
        'verified_name'=>['db_field'=>'verified_name','type'=>'string'],
        'id_number'=>['db_field'=>'ptx.id_number','type'=>'string'],
        'mobile'=>['db_field'=>'ptx.mobile','type'=>'string'],
    ];

    public $sorting_fields = [
        'tx_id'=> ['db_field'=>'tx.id'],
        'acquirer'=>['db_field'=>'tx.acquirer'],
        'acquirer_mid'=>['db_field'=>'tx.acquirer_mid'],
        'acquirer_name'=>['db_field'=>'tx.acquirer_name'],

        'processor_state_time'=>['db_field'=>'ptx.payment_time'],
        'state_time'=>['db_field'=>'tx.state_time'],
        'merchant_fx_package'=>['db_field'=>'m.settle_option'],
        'account'=>['db_field'=>'m.name'],
        'bank'=>['db_field'=>'b.name'],
        'bank_code'=>['db_field'=>'b.code'],
        'bank_card_number'=>['db_field'=>'bank_card_number'],
        'amount'=>['db_field'=>'amount'],
        'charge'=>['db_field'=>'charge'],
        'email'=>['db_field'=>'tx.email'],
        'state'=>['db_field'=>'tx.STATE'],
        'customer_name'=>['db_field'=>'customer_name'],
        'ip_address'=>['db_field'=>'tx.ip_address'],
        'transaction_id'=>['db_field'=>'ptx.pc_transaction_id'],
        'convert_amount'=>['db_field'=>'tx.convert_amount'],
        'convert_rate'=>['db_field'=>'tx.convert_rate'],
    ];

    protected $conn ;
    public function __construct($conn)
    {
        $this->conn = $conn;

        $this->TransactionLog = TableRegistry::get(
            'tx',
            [
            'connection'=> $conn,
            'className' => 'App\Model\Table\SettlementTransactionLogTable',
            ]
        );
        $this->MerchantGroup = TableRegistry::get(
            'mg',
            [
            'connection'=> $conn,
            'className'=> 'App\Model\Table\MerchantGroupTable',
            ]
        );
        $this->ProcessorAccount = TableRegistry::get(
            'pa',
            [
            'connection'=> $conn,
            'table'=> 'processor_account',
            ]
        );
        $this->Merchants = TableRegistry::get(
            'm',
            [
            'connection'=> $conn,
            'className'=> 'App\Model\Table\MerchantsTable',
            ]
        );
    }
    
    /**
     * Setup filter search in a query object.
     *
     * @param      <type>  $filter       The filter
     * @param      \       $whereClause  The where clause
     *
     * @return     \       ( description_of_the_return_value )
     */
    protected function doSubSearchQuery($filter, $rootClause = null, $whereClause = null)
    {
        $operator = isset($filter['logic']) ? strtolower($filter['logic']) : 'and';

        if (empty($rootClause)) {
            $rootClause = new \SQLBuilder\Universal\Syntax\Conditions();
        }
        if (empty($whereClause)) {
            $whereClause = $rootClause->group();
        }

        Log::write('debug', __METHOD__.'@'.__LINE__.': '.print_r($whereClause, true));

        if (!empty($filter['filters']) && is_array($filter['filters'])) {
            // Create root clause object

            $parentCounter = -1;
                        
            foreach ($filter['filters'] as $offset => $filter_info) {
                // If there are sub filtering
                if (isset($filter_info['filters'])) {
                // Otherwise, we do the query normally
                    // Log::write('debug', 'deeper:'. print_r($filter_info['filters'], true));

                    $this->doSubSearchQuery($filter_info, $rootClause, $whereClause ->group());
                    continue;
                }


                $req_field = $filter_info['field'];

                // If the field does not configure, skip the query.
                if (!isset($this->filterable_fields[ $req_field])) {
                    continue;
                }

                // Otherwise, we do the query normally
                // Log::write('debug', 'filters:'. print_r($filter_info, true));


                $parentCounter ++;

                if ($parentCounter > 0) {
                    if ($operator == 'or') {
                        $parentClause = $whereClause ->or();
                    } else {
                        $parentClause = $whereClause ->and();
                    }
                } else {
                    $parentClause = $whereClause ;
                }

                $groupClause = $parentClause;
                // $groupClause = $parentClause->where();
                // $groupClause = $parentClause ->group();

            

                $all_db_fields = $this->filterable_fields[ $req_field]['db_field'];
                $db_field_type = isset($this->filterable_fields[ $req_field]['db_field_type']) ? $this->filterable_fields[ $req_field]['db_field_type'] : 'normal';

                // List out all fields
                if (!is_array($all_db_fields)) {
                    $all_db_fields = [$all_db_fields];
                }

                $or = $groupClause;

                $counter =-1 ;

                $req_operator = $filter_info['operator'];
                $req_value = $filter_info['value'] ;

                // If passing string 'null', convert it into 'isnull' operator directly.
                if ($req_value == 'null') {
                    $req_operator = 'isnull';
                }
                // List out all acceptable db fields
                foreach ($all_db_fields as $db_field) {
                    $counter ++;

                    // For second parameter, use or conditions for same group filtering
                    if ($counter > 0) {
                        // $or = $groupClause ->or();
                    }
                    // For the requested operator, use correct query method
                    if ($req_operator == 'eq') {
                        $or->equal($db_field, $req_value);
                    } elseif ($req_operator == 'neq') {
                        $or->notEq($db_field, $req_value);
                    } elseif ($req_operator == 'startswith') {
                        $or->like($db_field, $req_value .'%');
                    } elseif ($req_operator == 'endswith') {
                        $or->like($db_field, '%'.$req_value);
                    } elseif ($req_operator == 'contains') {
                            // Replace all space for the name
                        $or->append("LCASE(".$db_field.") LIKE LCASE(".'%'. $this->toLikeSearchString($req_value) .'%'.')');
                    } elseif ($req_operator == 'doesnotcontain') {
                        $or->append("LCASE(".$db_field.") NOT LIKE LCASE(".'%'. $this->toLikeSearchString($req_value) .'%'.')');
                    } elseif ($req_operator == 'gte') {
                        $or->greaterThanOrEqualgte($db_field, $req_value);
                    } elseif ($req_operator == 'gt') {
                        $or->greaterThan($db_field, $req_value);
                    } elseif ($req_operator == 'lower_than') {
                        $or->lessThanOrEquallte($db_field, $req_value);
                    } elseif ($req_operator == 'lower') {
                        $or->lessThan($db_field, $req_value);
                    } elseif ($req_operator == 'isnotnull') {
                        $or->append($db_field.' IS NOT NULL');
                    } elseif ($req_operator == 'isnull') {
                        $or->append($db_field .' IS NULL');
                    } elseif ($req_operator == 'isnotempty') {
                        $or->append($db_field, '');
                    } elseif ($req_operator == 'isempty') {
                        $or->eq($db_field, '');
                    } else {
                        $counter --;
                    }
                }
            }
        }

        
        // Log::write('debug', __METHOD__.':'.print_r(compact('whereClause'), true));

        //$this->log(__METHOD__.':'. print_r($result, true), 'debug');
        return $rootClause;
    }

    /**
     * Reformat the text for wildcard search in database
     *
     * @param  string $text Text for wild card search in database
     * @return string Formatted string
     */
    protected function toLikeSearchString($text)
    {
        $str = preg_replace('#[\s%]+#', '%', $text);

        // Remove all wildcard character at the head and tail
        $str = preg_replace('#^%+#', '', $str);
        $str = preg_replace('#%+$#', '', $str);
        
        return  $str;
    }

    public function getAmountCase()
    {
        return "CAST( (CASE
        WHEN tx.STATE ='SALE' THEN (tx.AMOUNT)
        WHEN tx.STATE ='REFUNDED' THEN (-1 * tx.AMOUNT)
        WHEN tx.STATE ='PARTIAL_REFUND' THEN (tx.ADJUSTMENT)
        WHEN tx.STATE ='REFUND_REVERSED' THEN (tx.AMOUNT)
        WHEN tx.STATE ='PARTIAL_REFUND_REVERSED' THEN (tx.ADJUSTMENT)
        ELSE 0
        END) AS DECIMAL(16,4))";
    }

    public function getFeeCase()
    {
        return "CAST( (CASE
            WHEN tx.STATE = 'SALE' THEN tx.wecollect_fee_usd * -1
            WHEN tx.STATE = 'REFUNDED' THEN tx.wecollect_fee_usd * -1
            WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.wecollect_fee_usd * -1
            WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.wecollect_fee_usd
            WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.wecollect_fee_usd
            ELSE '0'
            END) AS DECIMAL(16,4))";
    }

    public function getWecollectFeeCase()
    {
        return "CAST( (CASE
            WHEN tx.STATE = 'SALE' THEN tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'REFUNDED' THEN tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.wecollect_fee_cny
            WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.wecollect_fee_cny
            ELSE '0'
            END) AS DECIMAL(16,4))";
    }

    public function getNetAmountCase()
    {
        return "CAST( (CASE

            WHEN tx.STATE = 'SALE' THEN tx.AMOUNT + tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT) + tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT + tx.wecollect_fee_cny * -1
            WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT + tx.wecollect_fee_cny
            WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT + tx.wecollect_fee_cny
            ELSE '0'
        END) AS DECIMAL(16,4))";
    }

    public function getProcessorNetAmountCase()
    {
        return "CAST( (CASE
        WHEN tx.STATE = 'SALE' AND ptx.id IS NULL THEN tx.AMOUNT 
        WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN tx.AMOUNT + ptx.fee * -1
        WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.AMOUNT)
        WHEN tx.STATE = 'PARTIAL_REFUND' THEN tx.ADJUSTMENT
        WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.AMOUNT
        WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN tx.ADJUSTMENT
        ELSE '0'
        END) AS DECIMAL(16,4))";
    }

    public function getCustomerNameCase()
    {
        return "(CONCAT(tx.FIRST_NAME, ' ', tx.LAST_NAME))";
    }

    public function getConvertCurrencyCase()
    {
        return "(CASE
        WHEN m.settle_option = '2' THEN m.settle_currency
        ELSE tx.CONVERT_CURRENCY
        END)";
    }

    public function getConvertRateCase()
    {
        return "CAST((CASE
        WHEN m.settle_option = '2' THEN 0
        ELSE tx.CONVERT_RATE
        END) AS DECIMAL(16,4))";
    }

    public function getConvertAmountCase()
    {
        return "CAST( (CASE
        WHEN m.settle_option = '2' THEN 0
        WHEN tx.STATE = 'SALE' THEN tx.CONVERT_AMOUNT
        WHEN tx.STATE = 'REFUNDED' THEN (-1 * tx.CONVERT_AMOUNT)
        WHEN tx.STATE = 'PARTIAL_REFUND' THEN (tx.ADJUSTMENT / tx.CONVERT_RATE)
        WHEN tx.STATE = 'REFUND_REVERSED' THEN tx.CONVERT_AMOUNT
        WHEN tx.STATE = 'PARTIAL_REFUND_REVERSED' THEN (tx.ADJUSTMENT / tx.CONVERT_RATE)
        ELSE '0'
        END) AS DECIMAL(16,4))";
    }

    public function getProcessorFeeCase()
    {
        return "CAST( (CASE
        WHEN tx.STATE = 'SALE' AND ptx.id IS NOT NULL THEN ptx.fee * -1
        ELSE '0'
        END) AS DECIMAL(16,4))";
    }

    /**
     * Create search query by given parameters and selecting fields
     *
     * @param  Array $params The parameters of the query
     * @param  Function|Array $select The list of fields when select data
     * @param  Function|Array $order  The list of fields for sorting.
     *                       No action will be required if passing FALSE
     * @return SelectQuery         Result object for the query
     */
    public function query($params = [], $select = null, $order = null)
    {
        $query = new SelectQuery;
        
        $case_amount = $this->getAmountCase();
        $case_fee = $this->getWecollectFeeCase();
        $case_fee_processor = $this->getFeeCase();
        $case_net_amount = $this->getNetAmountCase();
        $case_processor_net_amount = $this->getProcessorNetAmountCase();
        $case_ptx_fee = $this->getProcessorFeeCase();
        
        $case_customer_name =  $this->getCustomerNameCase();
        $case_convert_rate = $this->getConvertRateCase();
        $case_convert_amount = $this->getConvertAmountCase();
        
        $case_convert_currency = $this->getConvertCurrencyCase();
        
        $query
            ->from('transaction_log', 'tx')
            // ->partitions('u1', 'u2', 'u3')
            ;
        $query
            ->join('merchants')
                ->as('m')
                ->on('tx.merchant_id = m.id')
            ;
        $query
            ->leftJoin('processor_account')
                ->as('pa')
                ->on('pa.account = tx.acquirer_mid')
        ;
        
        $query->join('merchants_group_id')
                ->as('mgi')
                ->on('mgi.merchant_id = tx.merchant_id')
        ;
        $query->join('merchants_group')
                ->as('mg')
                ->on('mg.id = mgi.id')
        ;
        
        $query->leftJoin('processor_transaction_log')
                ->as('ptx')
                ->on('ptx.pc_log_id = tx.id')
        ;
        
        $query->leftJoin('banks')
                ->as('b')
                ->on('(tx.bank_code = b.code AND tx.bank_code IS NOT NULL) OR (ptx.bank_code = b.code AND tx.bank_code IS NULL AND ptx.bank_code IS NOT NULL)')
                ;
        

        
        $whereClause = $query->where();

        // Filter: Transaction ID
        $txn_id = isset($params['transaction_id']) ? $params['transaction_id']: null;
        if (!empty($txn_id)) {
            if (is_string($txn_id)) {
                $txn_id = explode(',', trim($txn_id));
            }
            $whereClause->in('tx.TRANSACTION_ID', $txn_id);
        }

        // Filter: Customer Name
        $customer_name = isset($params['customer_name']) ? $params['customer_name']: null;
        if (!empty($customer_name)) {
            $whereClause->where("LCASE(CONCAT(tx.`FIRST_NAME`, ' ', tx.`LAST_NAME`)) = LCASE('".addslashes($customer_name)."')");
        }

        // Filter: Email
        $email = isset($params['email']) ? $params['email']:null;
        if (!empty($email)) {
            if (is_string($email)) {
                $email = explode(',', trim($email));
            }
            $whereClause->in('tx.email', $email);
            // $query->andWhere(['tx.email LIKE '=> '%'.addslashes($this->toLikeSearchString($email)).'%'])
            ;
        }


        // Filter: acquirer_mid
        $acquirer_mid = isset($params['acquirer_mid']) ? $params['acquirer_mid']:null;
        if (!empty($acquirer_mid)) {
            if (is_string($acquirer_mid)) {
                $acquirer_mid = explode(',', trim($acquirer_mid));
            }
            $whereClause->in('acquirer_mid', $acquirer_mid);
        }

        // Filter: tx.id
        $txid = isset($params['txid']) ? $params['txid']:null;
        if (!empty($txid)) {
            if (is_string($txid)) {
                $txid = explode(',', trim($txid));
            }
            $whereClause->in('tx.id', $txid);
        }

        // Filter: tx.id
        $exclude_txid = isset($params['exclude_txid']) ? $params['exclude_txid']:null;
        if (!empty($exclude_txid)) {
            if (is_string($exclude_txid)) {
                $exclude_txid = explode(',', trim($exclude_txid));
            }
            $whereClause->notIn('tx.id', $exclude_txid);
        }

        // Filter: tx.id
        $settlement_status = isset($params['settlement_status']) ? $params['settlement_status']:null;
        if (!empty($settlement_status)) {
            if (is_string($settlement_status)) {
                $settlement_status = explode(',', trim($settlement_status));
            }
            $whereClause->in('tx.settlement_status', $settlement_status);
            // $query->andWhere(['tx.email LIKE '=> '%'.addslashes($this->toLikeSearchString($email)).'%'])
        }

        // Filter: Merchant Ref
        $merchant_ref = isset($params['merchant_ref']) ? $params['merchant_ref']:null;
        if (!empty($merchant_ref)) {
            if (is_string($merchant_ref)) {
                $merchant_ref = explode(',', trim($merchant_ref));
            }
            $whereClause->in('tx.MERCHANT_REF', $merchant_ref);
            // $query->andWhere ( function($exp, $q) use($merchant_ref) {
            //     return $exp->add(["LCASE(tx.MERCHANT_REF) = LCASE('".addslashes($merchant_ref)."')"]);
            //     // return $exp->add(["LCASE(tx.MERCHANT_REF) LIKE LCASE('%".addslashes($this->toLikeSearchString($merchant_ref))."%')"]);
            // });
        }

        // Filter: MerchantsGroups
        $merchantgroups = isset($params['merchantgroups']) ? $params['merchantgroups']:null;
        if (!empty($merchantgroups)) {
            if (is_string($merchantgroups)) {
                $merchantgroups = explode(',', trim($merchantgroups));
            }
            $whereClause->in('mgi.id', $merchantgroups);
        }


        // Filter: Merchants  (merchant_id)
        $merchants = isset($params['merchants']) ? $params['merchants']:null;
        if (!empty($merchants)) {
            if (is_string($merchants)) {
                $merchants = explode(',', trim($merchants));
            }
            $whereClause->in('tx.merchant_id', $merchants);
        }

        // Filter: reconciliation_batch_id
        $reconciliation_batch_id = isset($params['reconciliation_batch_id']) ? $params['reconciliation_batch_id']:null;
        if (!empty($reconciliation_batch_id)) {
            if (is_string($reconciliation_batch_id)) {
                $reconciliation_batch_id = explode(',', trim($reconciliation_batch_id));
            }
            $whereClause->in('tx.reconciliation_batch_id', $reconciliation_batch_id);
        }

        // Filter: States
        $accepted_states = ['SALE','REFUNDED','REFUND_REVERSED','PARTIAL_REFUND','PARTIAL_REFUND_REVERSED'];
        $states = isset($params['states']) ? $params['states']: $accepted_states;
        if (!empty($states)) {
            $_vals = [];
            foreach ($states as $_state) {
                if (in_array($_state, $accepted_states)) {
                    $_vals[] = $_state;
                }
            }
            $whereClause->in('tx.STATE', $_vals);
        }

        // Filter: Settlement Statuses
        // $accepted_settlement_statuses =  ['UNSETTLED','SETTLED','SETTLING','WITHIELD'];
        // $settlement_statuses = isset($params['settlement_statuses']) ? $params['settlement_statuses']: null;
        // if (!empty($settlement_statuses)) {
        //     $_vals = [];
        //     foreach ($settlement_statuses as $_val) {
        //         if (in_array($_val, $accepted_settlement_statuses))
        //             $_vals[] = $_val;
        //     }
        //     $query->andWhere(['tx.settlement_status IN' => $_vals]);
        // }

        // Filter: Date Range (processor_state_time)
        $start_date = null;
        $end_date = null;

        $start_date_str = isset($params['start_date']) ? $params['start_date']:null;
        if (!empty($start_date_str)) {
            $start_date = is_object($start_date_str) && is_subclass_of($start_date_str, 'DateTime') ? $start_date_str : new \DateTime($start_date_str);
        }

        $start_date_ts_str = isset($params['start_date_ts']) ? $params['start_date_ts']:null;
        if (!empty($start_date_ts_str)) {
            $start_date = new \DateTime(date('Y-m-d H:i:s', intval($start_date_ts_str) / 1000));
        }

        $end_date_str = isset($params['end_date']) ? $params['end_date']:null;
        if (!empty($end_date_str)) {
            $end_date = is_object($end_date_str) && is_subclass_of($end_date_str, 'DateTime') ? $end_date_str :new \DateTime($end_date_str);
        }

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
            
            $shifted_start_date = clone $start_date;
            $shifted_end_date = clone $end_date;
            
            // 15 mins later
            $shifted_start_date->add(new \DateInterval('PT15M'));
            $shifted_end_date->add(new \DateInterval('PT15M'));


            // Re-format date into string format for database query
            $start_time_str = $start_date->format('Y-m-d H:i:s');
            $end_time_str = $end_date->format('Y-m-d H:i:s');


            $shifted_start_time_str = $shifted_start_date->format('Y-m-d H:i:s');
            $shifted_end_time_str = $shifted_end_date->format('Y-m-d H:i:s');

            $shifted_start_time_str = $shifted_start_date->format('Y-m-d H:i:s');

           
            // $case_processor_time = '(CASE WHEN ght.payment_time IS NOT NULL THEN ght.payment_time WHEN gpay.transaction_time IS NOT NULL THEN gpay.transaction_time ELSE NULL END)';

            $whereClause->greaterThanOrEqual('tx.search_state_time', $start_time_str);
            $whereClause->lessThan('tx.search_state_time', $end_time_str);
        }

        // Added for supporting KendoUI.Grid.columns.filterable
        $filter = isset($params['filter']) ? $params['filter'] : null;
        if (!empty($filter['filters']) && is_array($filter['filters'])) {
            $subExprs = $this->doSubSearchQuery($filter, $whereClause);
            Log::write('debug', __METHOD__.'@'.__LINE__.'# filters: '.print_r($filter['filters'], true));
            Log::write('debug', __METHOD__.'@'.__LINE__.'# subExprs: '.print_r($subExprs, true));
        }

        // Selecting fields (According settlement_system_spec_v1.0.2)
        if (is_callable($select)) {
            $select = call_user_func_array($select, [$query]);
        }
        if (empty($select)) {
            $select = [
                'tx.id'=>'id',
                'mg.id'=>'merchantgroup_id',
                'mg.name'=>'merchantgroup_name',
                'ptx.id'=>'ptx_id',
                'tx.STATE' =>'state',
                'tx.STATE_TIME' =>'state_time',
                'search_state_time',
                'ptx.processor'=>'processor',
                $case_ptx_fee=>'processor_fee',
                'ptx.payment_time'=>'processor_state_time',
                'pa.name'=>'processor_name',
                'pa.type'=>'procsesor_type',
                'tx.acquirer'=>'acquirer',
                'tx.acquirer_mid'=>'acquirer_mid',
                'tx.acquirer_name'=>'acquirer_name',
                $case_amount=>'amount',
                $case_fee=>'fee',
                $case_fee_processor=>'fee_processor',
                $case_net_amount=>'net_amount',
                $case_processor_net_amount=>'processor_net_amount',
                "tx.FIRST_NAME"=>'customer_first_name',
                "tx.LAST_NAME"=>'customer_last_name',
                $case_customer_name=>'customer_name',
                'tx.email'=>'email',
                'tx.internal_id'=>'internal_id',
                'tx.TRANSACTION_ID'=>'transaction_id',
                'tx.AMOUNT'=>'transaction_amount',
                'tx.ADJUSTMENT'=>'transaction_adjustment',
                'tx.TRANSACTION_TIME'=>'transaction_time',
                'tx.TRANSACTION_CODE'=>'transaction_code',
                'tx.CURRENCY'=>'currency',
                $case_convert_rate=>'convert_rate',
                $case_convert_amount=>'convert_amount',
                $case_convert_currency=>'convert_currency',
                'tx.merchant_id'=>'merchant_id',
                'm.name'=>'merchant',
                'm.settle_option'=>'merchant_fx_package',
                'tx.MERCHANT_REF'=>'merchant_ref',
                'm.round_precision'=>'round_precision',
                'tx.wecollect_fee'=>'wecollect_fee',
                'tx.wecollect_fee_usd'=>'wecollect_fee_usd',
                'tx.wecollect_fee_cny'=>'wecollect_fee_cny',
                'tx.product' => 'product',
                'tx.ip_address' => 'ip_address',
                'tx.user_agent' => 'user_agent',
                'tx.bank_code' => 'bank_code',
                'ptx.bank_code' => 'ptx_bank_code',
                'b.name' => 'bank_name',
                'tx.card_number' => 'bank_card_number',
                'verified_name' => 'verified_name',
                'tx.id_card_number' => 'id_card_number',
                'tx.mobile_number' => 'mobile_number',
                'tx.settlement_status' => 'settlement_status',
                'tx.settle_by' => 'settle_by',
                'SITE_ID' => 'site_id',
                'SITE_NAME' => 'site_name',
                'tx.reconciled_state_time' => 'reconciled_state_time',
                'tx.reconciliation_batch_id'=> 'reconciliation_batch_id',
            ];
        }

        $query ->select($select);
        
        // Handle Grid Ordering
        //
        if (is_callable($order)) {
            $order = call_user_func_array($order, [$query]);
        }
        $sorts = [];
        if (!empty($params['sort']) && is_array($params['sort'])) {
            foreach ($params['sort'] as $sort_info) {
                $db_field = $sort_info['field'];

                if (isset($this->sorting_fields[ $db_field ])) {
                    $db_field = $this->sorting_fields[ $db_field ]['db_field'];
                }
                $sorts[ $db_field ] = strtoupper($sort_info['dir']);
            }
        } elseif ($order !== false && !empty($order)) {
            // If the call passing with a list of ordering, use it directly.
            //
            foreach ($order as $sort_info) {
                if (isset($sort_info['field'])) {
                    $db_field = $sort_info['field'];

                    if (isset($this->sorting_fields[ $db_field ])) {
                        $db_field = $this->sorting_fields[ $db_field ]['db_field'];
                    }
                    $sorts[ $db_field ] = strtoupper($sort_info['dir']);
                }
            }

        // If the parameter providing sort list, then use it
        }

        if (!empty($sorts)) {
            foreach ($sorts as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        }

        $output = new TransactionSearchQuery();
        $output->query = $query;
        $output->startDate = $start_date;
        $output->endDate = $end_date;

        return $output;
    }
}
