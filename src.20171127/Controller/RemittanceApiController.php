<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

use RemittanceReportReader;
use MerchantWallet;
/**
 * Remittance API for merchant Controller
 *
 * @property \App\Model\Table\MerchantsTable $Merchants
 */
class RemittanceApiController extends AppController
{
    private $no_param_responses = ["code"=> -10, "msg"=> "Incomplete parameter", "data"=> null];
    private $no_auth_responses = ["code"=> -1, "msg"=> "Merchant ID invalid", "data"=> null];
    //private $no_auth_responses = ["code"=> -2, "msg"=> "unauthorized", "data"=> null];
    private $merchantid;
    private $reader;
    private $wallet, $remittance_symbol;
    // remittance_preauthorized in table
    private $pre_authorized = false;

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->log("beforeFilter", 'debug');
        //$this->log($event, 'debug');
        // Allow users to register and logout.
        // You should not add the "login" action to allow list. Doing so would
        // cause problems with normal functioning of AuthComponent.
        // Allow all actions
        $this->Auth->allow();
        $this->request->allowMethod(['get','post']);
        $this->RequestHandler->ext = 'json';
/*
        if (! $this->checkAuth())
            return $this->output_json($this->no_auth_responses);
*/
        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->reader = new RemittanceReportReader($db_name);
        //$this->reader->username = 'API';
        $this->reader->ip = $this->request->clientIp();

        if (! $this->checkAuth())
            return $this->output_json($this->no_auth_responses);
    }
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        return $this->output_json($this->no_param_responses);
    }

    public function checkAuth() {
        //check valid Merchant ID
        //$this->log($this->request->query, 'debug');
        $reqkey = 'merchant_id';
        //GET
        $id = $this->request->query($reqkey);
        //POST
        if (empty($id) && isset($this->request->data[$reqkey]))
            $id = $this->request->data[$reqkey];
        $this->log("checkAuth now:$id", 'debug');
        if (empty($id))
            return FALSE;
        //check DB
        $merchants = TableRegistry::get('Merchants');
        // $merchant = $this->Merchants->get($id);
        // only for Remittance API enabled merchant
        $query = $merchants->find()
            ->where(['id' => $id,]);
        //for internal API requests
        $override_key='bypass_api_checkauth';
        if (! isset($this->request->data[$override_key]) || $this->request->data[$override_key]!='true')
            $query->where(['remittance_api_enabled >'=>0]);
        else
            $this->log("checkAuth bypass_api", 'info');
                    //->where(['id' => $id, 'remittance_api_enabled >'=>0]);

        if ($query->count()!=1)
            return FALSE;
        $data = $query->toArray();
        //$this->log($data[0], 'debug');

        $this->merchantid = $id;
        //$this->reader->merchant_id = $id;
        $this->reader->setMerchant($id);

        $this->wallet = new MerchantWallet($id);
        $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $this->log("RM Wallet ID:$wallet_id", 'debug');
        //Check if no wallet set for remittance, in Table merchant_wallet_service
        if (! $wallet_id)
            return FALSE;

        if ($data[0]->remittance_preauthorized > 0)
            $this->pre_authorized = true;
        //HKDR01
        if (!empty($data[0]->remittance_symbol)) {
            $this->log("DB remittance_symbol:".$data[0]->remittance_symbol, 'debug');
            $this->remittance_symbol = substr($data[0]->remittance_symbol, 0, 3);
        }


        $this->log("pre_authorized:".$this->pre_authorized, 'debug');

        return TRUE;
    }

    public function checkParameter($para=NULL, $post = true){
        //Allow POST only
        /*
        if (!$this->request->is('post'))
            return FALSE;
        */
        if (!is_array($para) || count($para)<1)
            return true;

        if ($post) {
            // POST data
            $this->log($this->request->data, 'debug');
            foreach ($para as $p) {
                if (!isset($this->request->data[$p]))
                    return FALSE;
                //$v = $this->request->query($p);
                $v = $this->request->data[$p];
                if (empty($v)) {
                    $this->log("checkParameter: MISS $p", 'debug');
                    return FALSE;
                }
            }
        } else {
            foreach ($para as $p) {
                $v = $this->request->query($p);
                if (empty($v)) {
                    $this->log("checkParameter: MISS $p", 'debug');
                    return FALSE;
                }
            }
        }

        return true;
    }

    // GET: 2.3	Remittance FX Rate Query
    public function fxrate(){
        $this->log("function fxrate", 'debug');
        if (! $this->checkParameter())
            return $this->output_json($this->no_param_responses);

        $symbol = 'USDCNY';
        $toCurrency = 'USD';
        //dev
        //if ($this->merchantid=='9dd5a398-c897-11e4-a1b7-0211eb00a4cc')
        if (isset($this->remittance_symbol))
        {
            $toCurrency = $this->remittance_symbol;
            $symbol = "{$toCurrency}CNY";
        }
        //$rate = $this->reader->getRmRate($this->merchantid);
        $rates = $this->reader->getRmRate($this->merchantid, '', 'CNY', $toCurrency, true);
        $this->log($this->merchantid." = ".var_export($rates, true), 'debug');

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> [
                "symbol"=>"$symbol",
                "rate"=> $rates['rate'],
                'timestamp'=> $rates['timestamp'],
            ]
        ];

        //return $this->output_json($data);
        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
    }

    public function batchRequest($req) {
        $this->log("function batchRequest()", 'debug');
        $this->log($req, 'debug');

        switch ($req){
            case 'status':
                return $this->batchRequestStatus();
            // http://test.hk.wecollect.com/backoffice/api/batch_request/status_update/
            case 'status_update':
                return $this->batchRequestStatusUpdate();
            case 'excel':
                return $this->batchRequestExcel();
        }
        return $this->index();
    }

    //POST: 2.1	Batch Request by Excel
    public function batchRequestExcel() {
        $this->log("function batchRequestExcel", 'debug');
        if (!$this->checkParameter(['excel_file']) )
            return $this->output_json(["code"=> -2, "msg"=> "Excel file invalid", "data"=> null ]);

        $pdfile = false;    //false = not required pdf auth file
        if (!$this->pre_authorized) {
            if (!$this->checkParameter(['authorization_pdf']))
                return $this->output_json(["code" => -3, "msg" => "Signature PDF file invalid", "data" => null]);
            //return $this->output_json(["code"=> -10, "msg"=> "Incomplete parameter", "data"=> ["batch_id"=> '', "validation_errors"=>[]] ]);
            $pdfile = $this->request->data['authorization_pdf'];
        }

        $xlsfile = $this->request->data['excel_file'];

        $timestamp = date('YmdHis');
        $xls_ext = substr(strtolower(strrchr($xlsfile['name'], '.')), 1); //get the extension
        $good_exts = array('xls', 'xlsx'); //set allowed extensions
        $basepath = Configure::read('WC.data_path');
        //not xls file
        if (!in_array($xls_ext, $good_exts))
            return $this->output_json(["code"=> -2, "msg"=> "Excel file invalid ", "data"=> null]);
            //return $this->output_json(["code"=> -2, "msg"=> "Excel file invalid ", "data"=> ["batch_id"=> '', "validation_errors"=>[]]]);

        $newFileName = sprintf("%s_%s_%s_%s.%s", 'remittance', $this->merchantid, $timestamp, md5_file($xlsfile['tmp_name']), $xls_ext);
        $datapath = "$basepath$newFileName";
        $this->log('move_uploaded_file:'."{$xlsfile['tmp_name']} \r\n to $datapath", 'info');
        move_uploaded_file($xlsfile['tmp_name'], $datapath);

        //handle pdf file
        $pdf_path='';
        if ($pdfile && is_array($pdfile) && isset($pdfile['tmp_name']) && is_readable($pdfile['tmp_name'])) {
            $pdf_ext = substr(strtolower(strrchr($pdfile['name'], '.')), 1); //get the extension
            $newFileName = sprintf("%s_%s_%s_%s.%s", 'remittance', $this->merchantid, $timestamp, md5_file($pdfile['tmp_name']), $pdf_ext);
            $pdf_path = $basepath.$newFileName;
            $this->log('move_uploaded_file pdf:'."{$pdfile['tmp_name']} \r\n to $pdf_path", 'info');
            move_uploaded_file($pdfile['tmp_name'], $pdf_path);
        } elseif ($pdfile !==false ) {
            return $this->output_json(["code"=> -3, "msg"=> "Signature PDF file invalid", "data"=> null]);
            //return $this->output_json(["code"=> -3, "msg"=> "Signature PDF file invalid", "data"=> ["batch_id"=> '', "validation_errors"=>[]]]);
        }

        if (isset($this->request->data['username']) && !empty($this->request->data['username'])) {
            $username = $this->request->data['username'];
            $this->log("set username:".$username, 'debug');
        } else {
            $username = 'API';
            if (isset($this->request->data['token']))
                $username.=':'.$this->request->data['token'];
        }
        $this->reader->username= $username;

        $jsons = $this->reader->handleExcelFile($this->merchantid, $datapath, $pdf_path);

        if ($jsons['code']!==0) {
            //remove invalid file
            /*
            if (is_file($datapath))
                unlink($datapath);
            if (is_file($pdf_path))
                unlink($pdf_path);
*/
            $jdata = (is_array($jsons['data']['validation_errors'])?["batch_id"=> '',"validation_errors"=> $jsons['data']['validation_errors']]:null);
            $data = ["code"=> $jsons['code'],
                "msg"=> $jsons['msg'],
                "data"=> $jdata
            ];
        } else {
            $bid = $jsons['batch_id'];
            //Get wallet of remittance
            /*
            $wallet_id = $this->wallet->getServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
            $this->wallet->setWallet($wallet_id);
            */
            $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
            $wallet_sym = $this->wallet->getWalletCurrency();
            $this->log("Wallet ID:$wallet_id, Currency: $wallet_sym", 'debug');
            // Balance check: Check if the merchant account CNY balance can cover the batch amount and fee
            // [TODO] create balance account for all rm merchants
            $this->reader->setBatchFee($bid);
            //$paid_amount = $this->reader->getBatchPaidAmount($bid);
            $paid_amount = $this->reader->getBatchPaidAmount($bid, $logstatus=true, $wallet_sym);
            $balance_ok = $this->wallet->checkBalance($paid_amount);

            $this->log("ID:$bid\nbalance OK?: $balance_ok\npaid_amount: ($wallet_sym)$paid_amount", 'debug');
            if (!$wallet_id || $paid_amount===false || ! $balance_ok) {
                //$verrors = ['trans_id'=>'','error_code'=>500, 'error_msg'=>'Insufficient fund'];
                //$this->reader->updateBatchLogStatus($bid, RemittanceReportReader::RM_STATUS_REJECTED);
                $this->reader->setBatchStatus($bid, RemittanceReportReader::BATCH_STATUS_DECLINED);

                return $this->output_json(["code"=> -4, "msg"=> "Insufficient balance", "data"=> ["batch_id"=> $bid, "validation_errors"=>null] ]);
            }
            //Subtract batch remittance amount plus fee from balance when approval
            /*
            $wallet_status = $this->wallet->addTransaction("-$paid_amount", \MerchantWallet::TYPE_BATCH_REMITTANCE, $dsc = '', $bid);
            if (!$wallet_status) {
                $this->reader->updateBatchLogStatus($bid, RemittanceReportReader::RM_STATUS_REJECTED);
                $this->reader->setBatchStatus($bid, RemittanceReportReader::BATCH_STATUS_DECLINED);

                return $this->output_json(["code"=> -4, "msg"=> "Insufficient balance", "data"=> ["batch_id"=> $bid, "validation_errors"=>null] ]);
            }
            */

            //Batch OK
            $data = ["code"=>0,
                "msg"=>"OK",
                "data"=> ["batch_id"=> $jsons['batch_id'],
                            "validation_errors"=>null,
                ]
            ];
            $this->reader->addNotificationLogs($this->merchantid, $jsons['batch_id']);
        }
        return $this->output_json($data);
    }

    // POST: 2.2	Batch Request Status Query
    public function batchRequestStatus(){
        $this->log("function batchRequestStatus", 'debug');

        if (! $this->checkParameter(['batch_id']) && !$this->checkParameter(['start_date','end_date']) )
            return $this->output_json(["code"=> -2, "msg"=> "Incomplete parameter", "data"=> null]);

        $this->log($this->request->data,'debug');
        $rb = TableRegistry::get('RemittanceBatch');
        $query = $rb->find('all')
            ->contain(['Merchants','RemittanceLog'])
            ->where(['merchant_id' => $this->merchantid]);

        if (!empty($this->request->data['batch_id'])) {
            $query->where(['RemittanceBatch.id' => $this->request->data['batch_id']]);
        } else {    //check date if no id
            $start = $this->request->data['start_date'];
            $start_tm = strtotime($start);
            $end = $this->request->data['end_date'];
            $end_tm = strtotime($end);
            //check valid date format & range
            if (!checkValidDate($start) || !checkValidDate($end) || $start_tm>$end_tm) {
                return $this->output_json(["code"=> -4, "msg"=> "Date range invalid", "data"=> null]);
            }

            $query->where(['RemittanceBatch.upload_time >=' => date('Y/m/d 00:00:00', $start_tm)] );
            $query->where(['RemittanceBatch.upload_time <=' => date('Y/m/d 00:00:00', strtotime('+1 day', $end_tm))] );
        }
        $query->order(['RemittanceBatch.upload_time' => 'DESC']);
        $this->log($query,'debug');
        //no record
        if (($total=$query->count())==0)
            return $this->output_json(["code"=> -3, "msg"=> "Batch not found", "data"=> null]);

        $this->log("count:".count($total),'debug');
        $batchs = array();
        foreach ($query as $row) {
            $batch = array();
            $id = $row->id;
            $data = $this->reader->getBatchDetails($id);
            //$batch['status'] = strtolower($row->status_name);
            if (!is_array($data))
                continue;
            $summary = $data[0];
            $batch['batch_id'] = $id;
            $batch['status'] = strtolower($summary['status_name']);
            $batch['upload_time'] = $summary['upload_time'];
            $batch['count'] = $summary['count'];
            $batch['total_amount_CNY'] = $summary['total_cny'];
            $batch['total_amount_converted'] = $summary['total_usd'];
            //todo: check fx rate of usd to cny, "batch_id": "57df89daf1267",
            //$batch['fxrate'] = $summary['final_convert_rate'];
            $batch['fxrate'] = $summary['final_convert_rate_display'];
            wcSetNumberFormat($batch);

            foreach ($data as $tx) {
                //$api_msg = $tx['api_log_intid'];
                $msgs = $this->reader->getProcessorApiLog($tx['id']);

                $transaction = ['name'=>$tx['beneficiary_name'], 'account_no'=>$tx['account'], 'bank_name'=>$tx['bank_name'], 'bank_branch'=>$tx['bank_branch'],
                            'province'=>$tx['province'], 'city'=>$tx['city'], 'id_card'=>$tx['id_number'], 'amount_CNY'=>$tx['amount'], 'amount_converted'=>$tx['convert_amount'],
                            'fxrate'=>$tx['convert_rate_display'], 'status'=>strtolower($tx['tx_status_name']),
                            'merchant_ref'=>$tx['merchant_ref'],
                            'remarks'=> $msgs['return_msg_en'],
                            //'remarks'=> $this->reader->getProcessorReturnMessageEnglish($api_msg),
                ];
                wcSetNumberFormat($transaction);
                $batch['transactions'][] = $transaction;
            }

            $batchs[]=$batch;
        }

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> $batchs
        ];

        return $this->output_json($data);
    }

    // POST: 2.7	Batch Status Update
    public function batchRequestStatusUpdate() {
        $this->log("function batchRequestStatusUpdate", 'debug');

        if (! $this->checkParameter(['action']) )
            return $this->output_json(["code"=> -5, "msg"=> "Action invalid", "data"=> null]);

        $action  = strtolower($this->request->data['action']);

        // Batch ID (Optional if action is process)
        if (! $this->checkParameter(['batch_id'])) {
            if ($action=='process')
                $bid = $this->reader->getExistOpenBatchId($this->merchantid);
            if (!$bid)
                return $this->output_json(["code" => -2, "msg" => "Batch ID invalid", "data" => null]);

            $this->log("getExistOpenBatchId: $bid",'debug');
        } else {
            $bid = $this->request->data['batch_id'];
        }

        //check action
        if (!in_array($action, ['process','cancel']))
            return $this->output_json(["code"=> -5, "msg"=> "Action invalid", "data"=> null]);
        //find batch with merchant id
        $rb = TableRegistry::get('RemittanceBatch');
        $query = $rb->find('all')
            //->contain(['Merchants','RemittanceLog'])
            ->where(['merchant_id' => $this->merchantid])
            ->where(['RemittanceBatch.id' => $bid]);
        if (($total=$query->count())==0)
            return $this->output_json(["code"=> -2, "msg"=> "Batch ID invalid", "data"=> null]);

        $batch = $query->first();
        $this->log("status:".$batch->status.' '.$batch->statusName,'debug');

        switch ($action) {
            case 'process':
                //check remittance_preauthorized
                if ($this->pre_authorized) {
                    if (!RemittanceReportReader::isValidStateChange($batch->status, RemittanceReportReader::BATCH_STATUS_QUEUED))
                        return $this->output_json(["code" => -3, "msg" => "Batch has started processing", "data" => null]);
                    $updates = ['status' => RemittanceReportReader::BATCH_STATUS_QUEUED];
                } else {
                    if (!RemittanceReportReader::isValidStateChange($batch->status, RemittanceReportReader::BATCH_STATUS_SIGNING))
                        return $this->output_json(["code" => -3, "msg" => "Batch has started processing", "data" => null]);
                    $updates = ['status' => RemittanceReportReader::BATCH_STATUS_SIGNING];
                }
                break;
            case 'cancel':
                if (! RemittanceReportReader::isValidStateChange($batch->status, RemittanceReportReader::BATCH_STATUS_CANCELLED))
                    return $this->output_json(["code"=> -4, "msg"=> "Batch cannot be cancelled", "data"=> null]);
                $updates = ['status'=>RemittanceReportReader::BATCH_STATUS_CANCELLED];
                break;
            default:
                return $this->output_json(["code"=> -5, "msg"=> "Action invalid", "data"=> null]);
        }

        $data = $this->reader->getBatchDetails($bid);
        if (count($data)<1) {
            return $this->output_json(["code"=> -6, "msg"=> "Batch is empty", "data"=> null]);
        }

        $updates['update_time']=date('Y-m-d H:i:s');
        $batch = $rb->patchEntity($batch, $updates);
//        $this->log($batch, 'debug');
        if ($rb->save($batch)) {
            $this->log("Batch saved:", 'debug');
            $this->log($updates, 'debug');
            //prepare for Authorization email
            if ($action=='process') {
                if ($this->pre_authorized) {
                    // No Authorization
                    $this->reader->updateBatchLogStatus($bid, RemittanceReportReader::RM_STATUS_ACCEPTED);
                } else {
                    $this->reader->saveAuthorization($this->merchantid, $bid, Configure::read('WC.Security.salt'));
                }
            }
            //save notification
            $this->reader->addNotificationLogs($this->merchantid, $bid);
        } else {
            return $this->output_json(["code"=> -10, "msg"=> "Database error occurred", "data"=> null]);
        }

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> ['batch_id'=>$bid, 'error_code'=>'', 'error_msg'=>'']
            //"data"=> null
        ];

        return $this->output_json($data);
    }

    // 3.5	Single Remittance Request
    public function singleRequest($skipped_fields=null) {
        $this->log("function singleRequest: ".var_export($skipped_fields,true), 'debug');

        //check remittance, e.g. return ['code'=>104, 'msg'=>'Currency invalid'];
        $request_mappings = [
                'name'=> ['name'=>'Beneficiary Name','required'=>true, 'length'=>50],
                'account_no'=> ['name'=>'Beneficiary Account Number','required'=>true, 'tags'=>['Beneficiary Account']],
                'bank_name'=> ['name'=>'Bank Name','required'=>true, 'length'=>50],
                'bank_branch'=> ['name'=>'Bank Branch','required'=>false, 'length'=>50],
                'province'=> ['name'=>'Province','required'=>false, 'length'=>10],
                'city'=> ['name'=>'City','required'=>false, 'length'=>10],
                'amount'=> ['name'=>'Transaction Amount','required'=>true, 'tags'=>['Transaction Amount Received']],
                //not required
                'currency'=> ['name'=>'Currency'],
                'id_card'=> ['name'=>'ID Card No', 'length'=>50],
                'id_card_type'=> ['name'=>'ID Card Remarks'],
                'merchant_ref'=> ['name'=>'Merchant Reference', 'length'=>50],
            ];
        $reqs = $this->request->data;
        $this->log("before process", 'debug');
        $this->log($reqs, 'debug');

        foreach ($request_mappings as $key=>$maps) {
            $col = $maps['name'];
            $col = strtolower($col);
            if (isset($maps['length']) && $maps['length']>0 && isset($reqs[$key])) {
                $reqs[$key] = mb_substr($reqs[$key], 0, $maps['length']);
            }

            if (!isset($reqs[$col]) && isset($reqs[$key]))
                $reqs[$col] = $reqs[$key];
        }

        $this->reader->merchant_id = $this->merchantid;
        //$this->log("mid=".$this->reader->merchant_id, 'debug');

        //$errors = $this->reader->validateRecord($reqs, $skipped=['id card no']);
        $errors = $this->reader->validateRecord($reqs, $skipped_fields);
        $this->log($reqs, 'debug');

        if ($errors['code']!=0)
            return $this->output_json(["code"=> -2, "msg"=> "Validation failed", "data"=> ['batch_id'=>'','error_code'=>$errors['code'], 'error_msg'=>$errors['msg']]]);
        //find Open batch with merchant id & insert
        $bid = $this->reader->getOpenBatchId($this->merchantid);
        if (! $this->reader->validateBatchCurrency($bid, $reqs['currency']))
            return $this->output_json(["code"=> -2, "msg"=> "Validation failed", "data"=> ['batch_id'=>'','error_code'=>109, 'error_msg'=>"All transactions must be specified in the same currency" ]]);
        //check duplicated, done in validateRecord
        /*
        if (! $this->reader->validateBatchDuplicate($reqs, $this->merchantid))
            return $this->output_json(["code"=> -2, "msg"=> "Validation failed", "data"=> ['batch_id'=>'','error_code'=>111, 'error_msg'=>"Duplicated transaction" ]]);
*/
	//todo: check id
	// return ['code'=>108, 'msg'=>'Same ID Card No. used for different Beneficiary Name'];
            //return $this->output_json(["code"=> -3, "msg"=> "Currency must be consistent within same batch", "data"=> NULL]);
        //update db
        $bid = $this->reader->insertSingleRemittance($bid, $reqs);

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> [
                "batch_id"=>"$bid",
                "error_code"=>NULL,
                "error_msg"=>''
            ]
        ];

        return $this->output_json($data);
    }

    // Instant Remittance Request API
    public function instantRequest($skipped_fields=null) {
        $this->log("function instantRequest: ".var_export($skipped_fields,true), 'debug');
        //table: instant_request
        $reqs = $this->request->data;
        $this->log("before process", 'debug');
        $this->log($reqs, 'debug');

        //disabled for all
        if (! in_array($this->merchantid, ['testonly', '9dd5a398-c897-11e4-a1b7-0211eb00a4cc'])) {
            $this->log("Merchant disabled:".$this->merchantid, 'debug');
            return $this->output_json(["code" => -5, "msg" => "Authorization failed", "data" => null]);
        }

        if (!$this->pre_authorized)
            return $this->output_json(["code"=> -5, "msg"=> "Authorization failed", "data"=> null]);

        $this->reader->merchant_id = $this->merchantid;
        $errors = $this->reader->validateInstantReq($reqs, $skipped_fields);
        $this->log($errors, 'debug');

        $test_trans = ($reqs['test_trans']=='1');
        $notify_type = 2;

        if ($errors['code']!=0)
            return $this->output_json(["code"=> -2, "msg"=> "Validation failed", "data"=> ['trans_id'=>'','error_code'=>$errors['code'], 'error_msg'=>$errors['msg']]]);

        //insert to database
        $reqs['status'] = RemittanceReportReader::IR_STATUS_PENDING;

        // add notify in reader
        $txid = $this->reader->insertInstantRequest($reqs);

        // SQL error
        if (! $txid) {
            return $this->output_json(["code"=> -2, "msg"=> "Validation failed", "data"=> ['trans_id'=>'','error_code'=>100, 'error_msg'=>'Database error']]);
        }
        //$wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $wallet_sym = $this->wallet->getWalletCurrency();
        // Balance check: Check if the merchant account CNY balance can cover the transaction amount and fee.
        //$paid_amount = $this->reader->setInstantRequestFee($txid);
        //setInstantRequestFee($txid, $toCurrency, $feeCurrency)
        $toCurrency = (isset($reqs['convert_currency'])?$reqs['convert_currency']:'');  //empty for CNY transaction
        $this->log("[$txid] Convert to: $toCurrency Wallet: $wallet_sym", 'debug');
        $paid_amount = $this->reader->setInstantRequestFee($txid, $toCurrency, $wallet_sym);
        $balance_ok = $this->wallet->checkBalance($paid_amount);

        $this->log("balance: $balance_ok\npaid_amount: $paid_amount", 'debug');
        //skip for test_trans
        if (!$test_trans)
            if ($paid_amount===false || ! $balance_ok) {
                $verrors = ['trans_id'=>'','error_code'=>500, 'error_msg'=>'Insufficient fund'];
                $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_REJECTED, ['validation'=>json_encode($verrors)]);
                //reset fee to 0, Done in setInstantRequestStatus
                //$this->reader->setInstantRequestFee($txid, $toCurrency='USD', true);

                return $this->output_json(["code"=> -4, "msg"=> "Insufficient balance", "data"=> $verrors]);
            }
        //check filter
        $ferrors = $this->reader->validateInstantRequestFilters($txid);
        if (is_array($ferrors)) {
            $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_BLOCKED, ['filter_remarks'=>json_encode($ferrors)]);
            //reset fee to 0, Done in setInstantRequestStatus
            //$this->reader->setInstantRequestFee($txid, $toCurrency='USD', true);

            return $this->output_json(["code"=> -3, "msg"=> "Transaction blocked", "data"=> ['trans_id'=>'','error_code'=>$ferrors['code'], 'error_msg'=>$ferrors['msg']]]);
        }

        // deduct balance in queue task, NOT here
        /*
        if (!$test_trans) {
            $wallet_status = $this->wallet->addTransaction("-$paid_amount", \MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc = '', $txid);
            if (!$wallet_status) {
                $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_REJECTED);
                return $this->output_json(["code" => -4, "msg" => "Insufficient balance", "data" => ['trans_id' => '', 'error_code' => 500, 'error_msg' => 'Insufficient fund']]);
            }
        }
        */
        //mark for processing after all checks ok
        $target = $this->reader->getPreferredApiProcessor($reqs['bank_code']);

        // add to RM API queue for processing, w/ hi priority = 1
        if (!$test_trans && $target !==false) {
            $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_PROCESSING, ['target'=>$target]);
            //save API requests to Queue
            $this->loadModel('Queue.QueuedJobs');
            $configs = ['priority'=>1];
            if (! $this->reader->isServiceHour())   //not service hour
                $configs['notBefore'] = date('Y-m-d '.(RemittanceReportReader::SERVICE_CUTOFF_END+1));
            $job = $this->QueuedJobs->createJob('InstantRemittance', ['api' => $target, 'merchant_id' => $this->merchantid, 'id' => $txid], $configs);
            $this->log("InstantRemittance ID: {$txid}, QueuedJobs ID:" . $job->id, 'debug');
        } elseif ($test_trans) {
            //set to OK & Test API
            $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_OK, ['target'=>RemittanceReportReader::TEST_API_TARGET]);
            $this->log("InstantRemittance ID: {$txid}, is test_trans:", 'debug');
        }

        $data = [
            "code"=>0,
            "msg"=>"OK",
            "data"=> [
                "trans_id"=>"$txid",
                "error_code"=>'',
                "error_msg"=>''
            ]
        ];

        return $this->output_json($data);
    }

    // Instant Remittance Request Status Query
    public function instantRequestStatus()
    {
        $this->log("function instantRequestStatus", 'debug');

        if (!$this->pre_authorized)
            return $this->output_json(["code"=> -7, "msg"=> "Authorization failed", "data"=> null]);

        if (!$this->checkParameter(['trans_id']) && !$this->checkParameter(['start_date', 'end_date']))
            return $this->output_json(["code" => -2, "msg" => "Incomplete parameter", "data" => null]);

        //$this->log($this->request->data, 'debug');
        $table = RemittanceReportReader::DATABASE_TABLE_INSTANTREQ;
        $ir = TableRegistry::get($table);
        $query = $ir->find('all')
            ->contain(['Merchants'])
            ->where(['merchant_id' => $this->merchantid]);

        if (!empty($this->request->data['trans_id'])) {
            $query->where(["$table.id" => $this->request->data['trans_id']]);
        } else {    //check date if no id
            $start = $this->request->data['start_date'];
            $start_tm = strtotime($start);
            $end = $this->request->data['end_date'];
            $end_tm = strtotime($end);
            //check valid date format & range
            if (!checkValidDate($start) || !checkValidDate($end) || $start_tm > $end_tm) {
                return $this->output_json(["code" => -4, "msg" => "Date range invalid", "data" => null]);
            }

            $query->where(["$table.create_time >=" => date('Y/m/d 00:00:00', $start_tm)]);
            $query->where(["$table.create_time <=" => date('Y/m/d 00:00:00', strtotime('+1 day', $end_tm))]);

            if (isset($this->request->data['status']) && !empty($this->request->data['status'])) {
                $status = strtolower($this->request->data['status']);
                if (!empty($status)) {
                    $statusVal = RemittanceReportReader::getInsReqStatusVal($status);
                    if ($statusVal === false)
                        return $this->output_json(["code" => -5, "msg" => "Status invalid", "data" => null]);
                    $query->where(['status' => $statusVal]);
                }
            }

            if (isset($this->request->data['test_trans']) && !empty($this->request->data['test_trans'])) {
                $test_trans = $this->request->data['test_trans'];
                if (!in_array($test_trans, ['0', '1']))
                    return $this->output_json(["code" => -6, "msg" => "test_trans value invalid", "data" => null]);
                $query->where(['test_trans' => $test_trans]);
            } else {
                // Return live transaction only (Default)
                $query->where(['test_trans' => 0]);
            }

            //request to db mapping
            $maps = ['name'=>'name', 'account_no'=>'account', 'id_card'=>'id_number', 'amount'=>'amount', 'merchant_ref'=>'merchant_ref'];
            foreach ($maps as $req=>$v)
            if (isset($this->request->data[$req]) && !empty($this->request->data[$req])) {
                // case insensitive
                $query->where(["$table.$v COLLATE UTF8_GENERAL_CI like" => trim($this->request->data[$req])]);
            }


        }   //end check date

        $query->order(["$table.create_time" => 'ASC']);
        //$this->log($query->count(),'debug');
        $this->log($query,'debug');
        $this->log("count:".count($query),'debug');

        //no record
        if (($total=$query->count())==0)
            return $this->output_json(["code"=> -3, "msg"=> "Transaction not found", "data"=> null]);

        $txs = array();
        foreach ($query as $row) {
            $tx = array();
            $id = $row->id;

            $tx['trans_id'] = $id;
            $tx['time'] = $row->create_time->i18nFormat('yyyy-MM-dd HH:mm:ss');
            //$this->log("create_time:".$row->create_time->i18nFormat('yyyy-MM-dd HH:mm:ss'),'debug');
            $tx['name'] = $row->name;
            $tx['account_no'] = $row->account;
            $tx['bank_name'] = $row->bank_name;
            $tx['bank_branch'] = $row->bank_branch;
            $tx['province'] = $row->province;
            $tx['city'] = $row->city;
            $tx['id_card'] = $row->id_number;
            $tx['amount_CNY'] = $row->amount;
            $tx['amount_converted'] = $row->convert_amount;
            $tx['currency'] = $row->convert_currency;
            $tx['fxrate'] = $row->convert_rate;
            $tx['status'] = strtolower($row->status_name);
            $tx['merchant_ref'] = $row->merchant_ref;
            /*
            $msg = $this->reader->getProcessorApiResponseMessage($id);
            $tx['remarks'] = $this->reader->getProcessorReturnMessageEnglish($msg);
*/
            $msgs = $this->reader->getProcessorApiLog('', $id);
            $tx['remarks'] = $msgs['return_msg_en'];

            wcSetNumberFormat($tx);
            $txs[]=$tx;
        }

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> ['transactions'=>$txs]
        ];

        return $this->output_json($data);
    }

    // Handle Instant Remittance processor (GPAY) callback
    public function instantRequestCallback()
    {
        $this->log("function instantRequestCallback", 'debug');
        $this->log($this->request->data, 'debug');

        // Success: status=1
        if ( !$this->checkParameter(['id', 'status']))
            return $this->output_json(["code" => -2, "msg" => "Incomplete parameter", "data" => null]);

        $data = ["code" => -1, "msg" => "Failed", "data" => null];
        $txid = $this->request->data['id'];
        $status = ($this->request->data['status']==1);
        $rm = $this->reader->getInstantRequest($txid);

        $this->log($rm, 'debug');
        if (!is_array($rm)){
            return $this->output_json($data);
        }

        //$paid_amount = $rm['paid_amount'];
        $amount = 0;
        $wallet_sym = $this->wallet->getWalletCurrency();
        $log_amt = ($wallet_sym=='CNY'?$rm['paid_amount']:$rm['convert_paid_amount']);

        $this->log("Currency: $wallet_sym, $log_amt", 'debug');
        //current status
        switch ($rm['status']) {
            case RemittanceReportReader::IR_STATUS_PROCESSING:
                //check balance record?
                if ($status) {
                    $this->log("InstantRequest callback success (" . $this->merchantid . ", $txid)", 'debug');
                    $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_OK);
                } else {
                    $this->log("InstantRequest callback failed (".$this->merchantid.", $txid)", 'debug');
                    $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_FAILED);
                    // Undo the transaction balance update if API failed
                    $amount = $log_amt;
                    $this->log("add ($wallet_sym)$amount (".$this->merchantid.", $txid)", 'debug');

                    $this->wallet->addTransaction($amount, MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc='Revert balance of failed InstantRequest', $txid);
                }
                break;
            case RemittanceReportReader::IR_STATUS_OK:
                if (! $status) {
                    $this->log("InstantRequest callback failed (".$this->merchantid.", $txid)", 'debug');
                    $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_FAILED);
                    // Undo the transaction balance update if API failed
                    $amount = $log_amt;
                    $this->log("add $amount (".$this->merchantid.", $txid)", 'debug');
                    $this->wallet->addTransaction($amount, MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc='Revert balance of failed InstantRequest', $txid);
                }
                break;
            case RemittanceReportReader::IR_STATUS_FAILED:
                if ($status) {
                    $this->log("InstantRequest callback success (".$this->merchantid.", $txid)", 'debug');
                    $this->reader->setInstantRequestStatus($txid, RemittanceReportReader::IR_STATUS_OK);
                    // deduct balance
                    //$amount = "-{$rm['paid_amount']}";
                    $amount = "-{$log_amt}";
                    $this->log("add $amount (".$this->merchantid.", $txid)", 'debug');
                    $this->wallet->addTransaction($amount, MerchantWallet::TYPE_INSTANT_REMITTANCE, $dsc='Deduct balance in callback', $txid);
                }
                break;
        }   //switch

        //Query API for Avoda & set callback
        if ($rm['target']=='13') {
            $this->reader->updateAvodaApiCallback($txid);
        }
        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> ['amount'=>$amount]
        ];

        return $this->output_json($data);
    }

    // Handle balance update of processor (GPAY) callback
    public function adjustBatchLogBalance() {
        $this->log("function adjustBatchLogBalance", 'debug');
        $this->log($this->request->data, 'debug');

        if ( !$this->checkParameter(['id', 'amount']))
            return $this->output_json(["code" => -2, "msg" => "Incomplete parameter", "data" => null]);

        $id = $this->request->data['id'];
        //$amount = $this->request->data['amount'];
        $dsc = $this->request->data['desc'];

        $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $wallet_sym = $this->wallet->getWalletCurrency();

        $ir = $this->reader->getInstantRequest($id);
        if (!is_array($ir)) {
            $amount = 0;
        } else {
            $amount = ($wallet_sym=='CNY'?$ir['paid_amount']:$ir['convert_paid_amount']);
        }
        $this->log("Wallet ID:$wallet_id, Currency: $wallet_sym, $amount", 'debug');

        if ($wallet_id) {
            $wallet_status = $this->wallet->addTransaction($amount, MerchantWallet::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT, $dsc, $id);
        }

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> ['amount'=>$amount]
        ];

        return $this->output_json($data);
    }

    /*
     * Serve JSON file
     */
    public function output_json($data) {
        if (!is_array($data)) {
            //return FALSE;
            $data = $this->no_param_responses;
        }

        //$output = json_encode($data, JSON_UNESCAPED_SLASHES );
        $output = json_encode($data);
        //$this->log("output:\n".$output, 'debug');
        //$this->log("json_encode output:\n".json_encode($data), 'debug');

        //$this->response->header(array("Content-Type: $mime",'Pragma: no-cache'));
        $this->response->disableCache();
        $this->response->modified('now');
        $this->response->checkNotModified($this->request);

        $this->response->type('json');
        $this->response->body($output);
        // Optionally force file download
        //$this->response->download($output_name);
        // Return response object to prevent controller from trying to render a view.
        return $this->response;
    }

}
