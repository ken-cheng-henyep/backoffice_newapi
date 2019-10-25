<?php

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Event\Event ;

class TransactionSearchTool
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

    public function __construct($conn)
    {

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
     * @param <type> $exp    The exponent
     * @param array  $filter The filter array
     *
     * @return <type>  ( description_of_the_return_value)
     */
    protected function doSubSearchQuery($exp, $filter)
    {
        $operator = isset($filter['logic']) ? strtolower($filter['logic']) : 'and';
        $result = null;

        if (!empty($filter['filters']) && is_array($filter['filters'])) {
            $subs = [];
            foreach ($filter['filters'] as $offset => $filter_info) {
                // If there are sub filtering
                if (isset($filter_info['filters'])) {
                    $_subs = $this->doSubSearchQuery($exp, $filter_info);

                    if (!empty($_subs)) {
                        $subs[] = $_subs;
                    }
                    continue;
                }


                // Otherwise, we do the query normally
                //$this->log('filters:'. print_r($filter_info, true), 'debug');


                $req_field = $filter_info['field'];

                // If the field does not configure, skip the query.
                if (!isset($this->filterable_fields[ $req_field])) {
                    continue;
                }

                $all_db_fields = $this->filterable_fields[ $req_field]['db_field'];

                // List out all fields
                if (!is_array($all_db_fields)) {
                    $all_db_fields = [$all_db_fields];
                }


                $sub = $exp->or_(
                    function ($or) use ($all_db_fields, $filter_info) {

                        $req_operator = $filter_info['operator'];
                        $req_value = $filter_info['value'] ;

                        // If passing string 'null', convert it into 'isnull' operator directly.
                        if ($req_value == 'null') {
                            $req_operator = 'isnull';
                        }

                        // List out all acceptable db fields
                        foreach ($all_db_fields as $db_field) {
                            // For the requested operator, use correct query method
                            if ($req_operator == 'eq') {
                                $or->eq($db_field, $req_value);
                            } elseif ($req_operator == 'neq') {
                                $or->notEq($db_field, $req_value);
                            } elseif ($req_operator == 'startswith') {
                                $or->like($db_field, $req_value .'%');
                            } elseif ($req_operator == 'endswith') {
                                $or->like($db_field, '%'.$req_value);
                            } elseif ($req_operator == 'contains') {
                                // Replace all space for the name
                                $or->like("LCASE(".$db_field.")", "LCASE(".'%'. $this->toLikeSearchString($req_value) .'%'.')');
                            } elseif ($req_operator == 'doesnotcontain') {
                                $or->notLike("LCASE(".$db_field.")", "LCASE(".'%'. $this->toLikeSearchString($req_value) .'%'.')');
                            } elseif ($req_operator == 'gte') {
                                $or->gte($db_field, $req_value);
                            } elseif ($req_operator == 'gt') {
                                $or->gt($db_field, $req_value);
                            } elseif ($req_operator == 'lower_than') {
                                $or->lte($db_field, $req_value);
                            } elseif ($req_operator == 'lower') {
                                $or->lt($db_field, $req_value);
                            } elseif ($req_operator == 'isnotnull') {
                                $or->isNotNull($db_field);
                            } elseif ($req_operator == 'isnull') {
                                $or->isNull($db_field);
                            } elseif ($req_operator == 'isnotempty') {
                                $or->notEq($db_field, '');
                            } elseif ($req_operator == 'isempty') {
                                $or->eq($db_field, '');
                            }
                        }

                        return $or;
                    }
                );

                $subs[] = $sub;
            }

            if ($operator == 'or') {
                $result = $exp->or_($subs);
            }

            if ($operator == 'and') {
                $result = $exp->and_($subs);
            }
        }
        //$this->log(__METHOD__.':'. print_r($result, true), 'debug');
        return $result;
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

    /**
     * Create search query by given parameters and selecting fields
     *
     * @param  Array $params The parameters of the query
     * @param  Array $select The list of fields when select data
     * @param  Array $order  The list of fields for sorting.
     *                       No action will be required if passing FALSE
     * @return Query         Result object for the query
     */
    public function query($params = [], $select = null, $order = null)
    {

        $query = $this->TransactionLog->find('all');


        $query->join(
            [
            'm' => [
                'table' => 'merchants',
                'type' => 'INNER',
                'conditions' => ['m.id = tx.merchant_id'],
                ],
            'pa' => [
                'table' => 'processor_account',
                'type' => 'LEFT',
                'conditions' => ['pa.account = tx.acquirer_mid'],
                ],
            'mgi' => [
                'table' => 'merchants_group_id',
                'type' => 'INNER',
                'conditions' => ['mgi.merchant_id = tx.merchant_id'],
                ],
            'mg' => [
                'table' => 'merchants_group',
                'type' => 'INNER',
                'conditions' => ['mg.id = mgi.id'],
                ],
            'ptx' => [
                'table' => 'processor_transaction_log',
                'type' => 'LEFT',
                'conditions' => ['(ptx.pc_log_id = tx.id)'],
                ],
            // 'gpay' => [
            // 'table' => 'gpay_transaction_log',
            // 'type' => 'LEFT',
            // 'conditions' => ['gpay.merchant_order_no = tx.internal_id','tx.internal_id IS NOT NULL'],
            // ],
            // 'ght' => [
            // 'table' => 'ght_transaction_log',
            // 'type' => 'LEFT',
            // 'conditions' => ['ght.transaction_id = tx.transaction_id'],
            // ],
            'b' => [
                'table' => 'banks',
                'type' => 'LEFT',
                'conditions' => [
                        ['( (tx.bank_code = b.code AND tx.bank_code IS NOT NULL) OR (ptx.bank_code = b.code AND tx.bank_code IS NULL AND ptx.bank_code IS NOT NULL))']
                    ],
                ],
            ]
        );

        // Filter: Transaction ID
        $txn_id = isset($params['transaction_id']) ? $params['transaction_id']: null;
        if (!empty($txn_id)) {
            $query->andWhere(['tx.TRANSACTION_ID'=> $txn_id]);
        }

        // Filter: Customer Name
        $customer_name = isset($params['customer_name']) ? $params['customer_name']: null;
        if (!empty($customer_name)) {
            $query->andWhere(
                function ($exp, $q) use ($customer_name) {
                    return $exp->add(
                        ["LCASE(CONCAT(tx.`FIRST_NAME`, ' ', tx.`LAST_NAME`)) = LCASE('".addslashes($customer_name)."')"]
                    );
                }
            );
        }

        // Filter: Email
        $email = isset($params['email']) ? $params['email']:null;
        if (!empty($email)) {
            $query->andWhere(['tx.email'=> $email])
            // $query->andWhere(['tx.email LIKE '=> '%'.addslashes($this->toLikeSearchString($email)).'%'])
            ;
        }


        // Filter: acquirer_mid
        $acquirer_mid = isset($params['acquirer_mid']) ? $params['acquirer_mid']:null;
        if (!empty($acquirer_mid)) {
            if (is_string($acquirer_mid)) {
                $acquirer_mid = explode(',', trim($acquirer_mid));
            }
            $query->andWhere(['acquirer_mid IN'=> $acquirer_mid ])
            ;
        }

        // Filter: tx.id
        $txid = isset($params['txid']) ? $params['txid']:null;
        if (!empty($txid)) {
            if (is_string($txid)) {
                $txid = explode(',', trim($txid));
            }
            $query->andWhere(['tx.id IN'=> $txid ])
            ;
        }

        // Filter: tx.id
        $exclude_txid = isset($params['exclude_txid']) ? $params['exclude_txid']:null;
        if (!empty($exclude_txid)) {
            if (is_string($exclude_txid)) {
                $exclude_txid = explode(',', trim($exclude_txid));
            }
            $query->andWhere(['tx.id NOT IN'=> $exclude_txid ])
            ;
        }

        // Filter: tx.id
        $settlement_status = isset($params['settlement_status']) ? $params['settlement_status']:null;
        if (!empty($settlement_status)) {
            if (is_string($settlement_status)) {
                $query->andWhere(['tx.settlement_status'=> $settlement_status]);
            }
            if (is_array($settlement_status)) {
                $query->andWhere(['tx.settlement_status IN'=> $settlement_status]);
            }
            // $query->andWhere(['tx.email LIKE '=> '%'.addslashes($this->toLikeSearchString($email)).'%'])
            ;
        }

        // Filter: Merchant Ref
        $merchant_ref = isset($params['merchant_ref']) ? $params['merchant_ref']:null;
        if (!empty($merchant_ref)) {
            $query->andWhere(['tx.MERCHANT_REF'=> $merchant_ref]);
            // $query->andWhere ( function($exp, $q) use($merchant_ref) {
            //     return $exp->add(["LCASE(tx.MERCHANT_REF) = LCASE('".addslashes($merchant_ref)."')"]);
            //     // return $exp->add(["LCASE(tx.MERCHANT_REF) LIKE LCASE('%".addslashes($this->toLikeSearchString($merchant_ref))."%')"]);
            // });
        }

        // Filter: MerchantsGroups
        $merchantgroups = isset($params['merchantgroups']) ? $params['merchantgroups']:null;
        if (!empty($merchantgroups)) {
            $query->andWhere(['mgi.id IN'=>$merchantgroups]);
        }


        // Filter: Merchants  (merchant_id)
        $merchants = isset($params['merchants']) ? $params['merchants']:null;
        if (!empty($merchants)) {
            $query->andWhere(['tx.merchant_id IN' => $merchants]);
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
            $query->andWhere(['tx.STATE IN' => $_vals]);
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

            // Setup query time.
            $query->andWhere(
                function ($exp) use ($start_time_str, $end_time_str, $shifted_start_time_str, $shifted_end_time_str) {
                    return $exp->or_(
                        [
                        // If processor_state_time is T day
                        // but tx_state_time is the T+1 day (within 15 mins)
                        [
                            'tx.search_state_time >='=>$start_time_str,
                            'tx.search_state_time <'=>$end_time_str,
                            // 'tx.state'=>'SALE',
                        ],
                        // [
                            // 'tx.state_time >='=>$shifted_start_time_str,
                            // 'tx.state_time <'=>$shifted_end_time_str,
                            // 'tx.state IN'=>['REFUND_REVERSED','REFUNDED','PARTIAL_REFUND','PARTIAL_REFUND_REVERSED'],
                        // ],
                        // [
                        //     $case_processor_time.' IS NULL',
                        ]
                    );
                }
            );
        }

        // Filter: reconciliation_batch_id
        $reconciliation_batch_id = isset($params['reconciliation_batch_id']) ? $params['reconciliation_batch_id']:null;
        if (!empty($reconciliation_batch_id)) {
            $query->andWhere(['tx.reconciliation_batch_id'=> $reconciliation_batch_id])
            ;
        }


        // Caculating amount
        $amount_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['STATE' => 'SALE']),
            $query->newExpr()->add(['STATE' => 'REFUNDED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND']),
            $query->newExpr()->add(['STATE' => 'REFUND_REVERSED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND_REVERSED']),
            ],
            [
            $query->newExpr()->add(['tx.AMOUNT']),
            $query->newExpr()->add(['(-1 * tx.AMOUNT)']),
            $query->newExpr()->add(['tx.ADJUSTMENT']),
            $query->newExpr()->add(['tx.AMOUNT']),
            $query->newExpr()->add(['tx.ADJUSTMENT']),
            0,
            ],
            [
            'float',
            'float',
            'float',
            'float',
            'float',

            ]
        );


        // Caculating charge
        $charge_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['STATE' => 'SALE']),
            $query->newExpr()->add(['STATE' => 'REFUNDED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND']),
            $query->newExpr()->add(['STATE' => 'REFUND_REVERSED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND_REVERSED']),
            ],
            [
            $query->newExpr()->add(['tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['tx.wecollect_fee_cny']),
            $query->newExpr()->add(['tx.wecollect_fee_cny']),
            0,
            ],
            [
            'float',
            'float',
            'float',
            'float',
            'float',

            ]
        );



        // Caculating charge
        $net_amount_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['STATE' => 'SALE']),
            $query->newExpr()->add(['STATE' => 'REFUNDED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND']),
            $query->newExpr()->add(['STATE' => 'REFUND_REVERSED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND_REVERSED']),
            ],
            [
            $query->newExpr()->add(['tx.AMOUNT + tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['(-1 * tx.AMOUNT) + tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['tx.ADJUSTMENT + tx.wecollect_fee_cny * -1']),
            $query->newExpr()->add(['tx.AMOUNT + tx.wecollect_fee_cny']),
            $query->newExpr()->add(['tx.ADJUSTMENT + tx.wecollect_fee_cny']),
            0,
            ],
            [
            'float',
            'float',
            'float',
            'float',
            'float',

            ]
        );




        // Caculating charge
        $net_amount_processor_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['STATE' => 'SALE']),
            $query->newExpr()->add(['STATE' => 'REFUNDED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND']),
            $query->newExpr()->add(['STATE' => 'REFUND_REVERSED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND_REVERSED']),
            ],
            [
            $query->newExpr()->add(['tx.AMOUNT + ptx.fee * -1']),
            $query->newExpr()->add(['(-1 * tx.AMOUNT)']),
            $query->newExpr()->add(['tx.ADJUSTMENT']),
            $query->newExpr()->add(['tx.AMOUNT']),
            $query->newExpr()->add(['tx.ADJUSTMENT']),
            0,
            ],
            [
            'float',
            'float',
            'float',
            'float',
            'float',

            ]
        );
        // Caculating amount
        $convert_rate_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['m.settle_option' => '1']),
            $query->newExpr()->add(['m.settle_option' => '2']),
            ],
            [
            $query->newExpr()->add(['tx.CONVERT_RATE']),
            0,
            ],
            [
            'float',
            'float',

            ]
        );


        $convert_currency_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['m.settle_option' => '1']),
            $query->newExpr()->add(['m.settle_option' => '2']),
            ],
            [
            $query->newExpr()->add(['tx.CONVERT_CURRENCY']),
            $query->newExpr()->add(['m.settle_currency']),
            ]
        );

        // Caculating amount
        $convert_amount_case = $query->newExpr()->addCase(
            [
            $query->newExpr()->add(['m.settle_option' => '2']),
            $query->newExpr()->add(['STATE' => 'SALE']),
            $query->newExpr()->add(['STATE' => 'REFUNDED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND']),
            $query->newExpr()->add(['STATE' => 'REFUND_REVERSED']),
            $query->newExpr()->add(['STATE' => 'PARTIAL_REFUND_REVERSED']),
            ],
            [
            0,
            $query->newExpr()->add(['tx.CONVERT_AMOUNT']),
            $query->newExpr()->add(['(-1 * tx.CONVERT_AMOUNT)']),
            $query->newExpr()->add(['(tx.ADJUSTMENT / tx.CONVERT_RATE)']),
            $query->newExpr()->add(['tx.CONVERT_AMOUNT']),
            $query->newExpr()->add(['(tx.ADJUSTMENT / tx.CONVERT_RATE)']),
            0,
            ],
            [
            'float',
            'float',
            'float',
            'float',
            'float',
            'float',

            ]
        );

        // Added for supporting KendoUI.Grid.columns.filterable
        $filter = isset($params['filter']) ? $params['filter'] : null;
        if (!empty($filter['filters']) && is_array($filter['filters'])) {
            $query->andWhere(
                function ($exp, $q) use ($filter) {
                    $conds = $this->doSubSearchQuery($exp, $filter);

                    return $conds;
                }
            );
        }


        // Selecting fields (According settlement_system_spec_v1.0.2)
        if (empty($select)) {
            $select =  [
            'id'=>'tx.id',
            'merchantgroup_id'=>'mgi.id',
            'merchantgroup_name'=>'mg.name',
            'tx_id'=>'tx.id',
            'ptx_id'=>'ptx.id',
            'state_time'=>'tx.STATE_TIME',
            'state'=>'tx.STATE',
            'search_state_time'=>'search_state_time',
            'processor'=>'ptx.processor',
            'processor_fee'=>'ptx.fee',
            'processor_state_time'=>'ptx.payment_time',
            'processor_name'=>'pa.name',
            'processor_type'=>'pa.type',
            'acquirer',
            'acquirer_mid',
            'acquirer_name',
            'amount'=>$amount_case,
            'charge'=>$charge_case,
            'net_amount'=>$net_amount_case,
            'net_amount_processor'=>$net_amount_processor_case,
            'customer_first_name'=> 'tx.FIRST_NAME',
            'customer_last_name'=>'tx.LAST_NAME',
            'customer_name' => $query->func()->concat(['tx.FIRST_NAME' => 'identifier',' ','tx.LAST_NAME' => 'identifier']),
            'email',
            'internal_id',
            'transaction_id'=>'tx.TRANSACTION_ID',
            'transaction_amount'=>'tx.AMOUNT',
            'transaction_adjustment'=>'tx.ADJUSTMENT',
            'transaction_time'=>'tx.TRANSACTION_TIME',
            'transaction_state'=>'tx.TRANSACTION_STATE',
            'transaction_code'=>'tx.TRANSACTION_CODE',

            'currency'=>'tx.CURRENCY',
            'convert_rate'=>$convert_rate_case,
            'convert_amount'=>$convert_amount_case,
            'convert_currency'=>$convert_currency_case,
            'merchant_id',
            'merchant'=>'m.name',
            'merchantgroup_name'=>'mg.name',
            'merchant_fx_package'=>'m.settle_option',
            'merchant_ref'=>'tx.MERCHANT_REF',
            // 'merchant_mdr_rate'=>'m.settle_fee',
            // 'merchant_refund_fee'=>'m.refund_fee_cny',
            'round_precision'=>'m.round_precision',
            'wecollect_fee',
            'wecollect_fee_usd',
            'wecollect_fee_cny',
            'product',
            'ip_address',
            'user_agent',
            'bank_code'=>'tx.bank_code',
            'ptx_bank_code'=>'ptx.bank_code',
            'bank_name'=>'b.name',
            'bank_card_number'=>'tx.card_number',
            'verified_name'=>'verified_name',
            'id_card_number'=>'tx.id_card_number',
            'mobile_number'=>'tx.mobile_number',
            'settlement_status',
            'settle_by',
            'site_id'=>'SITE_ID',
            'site_name'=>'SITE_NAME',
            'reconciled_state_time',
            'reconciliation_batch_id',
            ];
        }
        $query ->select($select);

        // Handle Grid Ordering
        if ($order !== false && !empty($order)) {
            $sorts = [];

            // If the call passing with a list of ordering, use it directly.
            //
            if (!empty($order) && is_array($order)) {
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
            } elseif (!empty($params['sort']) && is_array($params['sort'])) {
                foreach ($params['sort'] as $sort_info) {
                    $db_field = $sort_info['field'];

                    if (isset($this->sorting_fields[ $db_field ])) {
                        $db_field = $this->sorting_fields[ $db_field ]['db_field'];
                    }
                    $sorts[ $db_field ] = strtoupper($sort_info['dir']);
                }
            }

            // Backup
            $sorts['tx.STATE_TIME'] = 'DESC';
            $query->order($sorts);
        }


        return compact('query', 'start_date', 'end_date');
    }
}
