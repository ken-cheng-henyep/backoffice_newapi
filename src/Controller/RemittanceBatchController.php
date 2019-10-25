<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use RemittanceReportReader;
use MerchantWallet;

/**
 * RemittanceBatch Controller
 *
 * @property \App\Model\Table\RemittanceBatchTable $RemittanceBatch
 */
class RemittanceBatchController extends AppController
{
    const API_BASE_URL = 'http://test.hk.wecollect.com/backoffice/api/remittance/' ;    //Dev
    //const API_BASE_URL = 'http://backoffice.hk.wecollect.com/admin/api/remittance/';  //Production, internal only
    var $excel_api_url;

    // RemittanceReportReader
    var $reader, $username, $role;
    private $wallet;

    public function initialize() {
        parent::initialize();

        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->reader = new RemittanceReportReader($db_name);
        //$this->log("initialize() $db_name", 'debug');
        $usrs = $this->request->session()->read('Auth.User');
        $this->username = $usrs['username'];
        if (isset($usrs['role']))
            $this->role = $usrs['role'];

        $urlHp = new \Cake\View\Helper\UrlHelper(new \Cake\View\View());
        $this->excel_api_url = $urlHp->build([
            "controller" => "RemittanceApi",
            "action" => "batchRequestExcel",],
            ['fullBase' => true]);

        //hot fix
        $this->excel_api_url = str_replace('backoffice.hk.wecollect.com', '127.0.0.1', $this->excel_api_url);
        $this->log("API URL:".$this->excel_api_url, 'debug');
    }
    //display index of links
    public function index(){

    }

    /**
     * Batch Search page
     *
     * @return \Cake\Network\Response|null
     */
    public function search()
    {
        //Merchants drop down list
        $merchants = TableRegistry::get('Merchants');
        $query = $merchants->find('list',[
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->where(['processor_account_type'=>1])  //online bank
            ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();
        $this->set('merchant_lst', $mercdata);
        $this->set('status_lst',RemittanceReportReader::$status_mappings);

        $this->paginate = [
            'limit' => 25,
            'order' => ['RemittanceBatch.upload_time' => 'desc'],
            'contain' => ['Merchants']
        ];

        //initial default query
        $query = $this->RemittanceBatch->find('all')
            ->where(['status' => RemittanceReportReader::BATCH_STATUS_QUEUED])
            //->where(['RemittanceBatch.upload_time >=' => date('Y/m/d 00:00:00', strtotime('-14 days'))])
            ->contain(['Merchants']);

        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');
            $query = $this->RemittanceBatch->find('all')
                    ->contain(['Merchants']);

            if (!empty($this->request->data['batch_id']))
                $query->where(['RemittanceBatch.id' => $this->request->data['batch_id']]);
            if (!empty($this->request->data['merchant']))
                $query->where(['merchant_id' => $this->request->data['merchant']]);
            if (!empty($this->request->data['start']))
                $query->where(['RemittanceBatch.upload_time >=' => $this->request->data['start'].' 00:00:00']);
            if (!empty($this->request->data['end'])) {
                $enddate = date('Y/m/d 00:00:00',strtotime('+1 day',strtotime($this->request->data['end'])));
                $query->where(['RemittanceBatch.upload_time <=' => $enddate]);
            }
            if (isset($this->request->data['status']) && trim($this->request->data['status'])!='')
                $query->where(['RemittanceBatch.status' => $this->request->data['status']]);

            $this->log($query,'debug');
        }

        //$remittanceBatch = $this->paginate($query);

        $json_url = Router::url(['action' => 'jsonList']);
        $default_status = RemittanceReportReader::BATCH_STATUS_QUEUED;
        //$this->set('json_url', $json_url);
        $this->set(compact('json_url', 'default_status'));
        /*
        $this->set(compact('remittanceBatch'));
        $this->set('_serialize', ['remittanceBatch']);
        */
    }

    public function searchTx() {
        //Merchants drop down list
        $merchants = TableRegistry::get('Merchants');
        $query = $merchants->find('list',[
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->where(['processor_account_type'=>1])  //online bank
            ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();
        $this->set('merchant_lst', $mercdata);
        $this->set('status_lst',RemittanceReportReader::$status_mappings);

    }

    public function txJson()
    {
        //$this->log($this->request->query, 'debug');
        $this->log('txJson: query, data, role:'.$this->role, 'debug');

        $data = ['status'=>-1, 'msg'=>'Failed'];
        $total = 0;

        if ($this->request->is(['ajax','get'])) {
            /*
            $subquery = $this->RemittanceBatch->find('all')
                ->contain(['Merchants']);
            $merchants = TableRegistry::get('Merchants');
*/
            $rmlog = TableRegistry::get('RemittanceLog');

            $query = $rmlog->find();
            /*
                ->matching('RemittanceBatch', function ($q) {
                    //return $q->where(['RemittanceBatch.merchant_id' => $this->merchant_id]);
                    return $q->where(['1' => 1]);
                });
            */

                            //$query->hydrate(false)
                                $query->join([
                                    'b'=>[
                                        'table' => 'remittance_batch',
                                        //'alias' => 'm',
                                        'type' => 'INNER',
                                        'conditions' => 'b.id = batch_id',
                                    ],
                                    'm'=>[
                                    'table' => 'merchants',
                                    //'alias' => 'm',
                                    'type' => 'LEFT',
                                    'conditions' => 'm.id = merchant_id',
                                ],
                                ]);
            //$query->contain(['RemittanceBatch']);

            /*
                ->matching('Merchants', function ($q) {
                    return $q->where(['RemittanceBatch.merchant_id' => 'Merchants.id']);
                    //return $q->where(['1' => 1]);
                });
*/
            if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
                foreach ($this->request->query['filter']['filters'] as $filter) {
                    $val = (isset($filter['value'])?trim($filter['value']):'');
                    if (!empty($filter['field']) && $val!='')
                        switch ($filter['field']) {
                            case 'merchant':
                                //$query->where(['RemittanceBatch.merchant_id' => $val]);
                                $query->where(['b.merchant_id' => $val]);
                                break;
                            case 'status':
                                $query->where(['b.status' => $val]);
                                //$query->where(['RemittanceBatch.status' => $val]);
                                break;
                            case 'name':
                                //$query->where(['beneficiary_name' => $val]);
                                $val = strtolower($val);
                                $query->where(['LOWER(beneficiary_name) LIKE' => "%$val%"]);
                                /*
                                // ignore other criteria if id exists
                                $query->orWhere(['RemittanceBatch.id' => $val])
                                    ->andWhere(['merchant_id' => $this->merchant_id]) ;
                                */
                                break;
                            case 'start':
                                $query->where(['create_time >='=> date('Y/m/d', strtotime($val)).' 00:00:00']);
                                break;
                            case 'end':
                                $enddate = date('Y/m/d 00:00:00',strtotime('+1 day',strtotime($val)));
                                $query->where(['create_time <='=> $enddate]);
                                break;
                            default:
                                // case 'account':
                                $query->where([$filter['field'] => $val]);
                        }   //switch
                }
            }

            /*
            $exprs = array();
            // select CASE WHEN ...
            foreach (RemittanceReportReader::$status_mappings as $st=>$name) {
                $exprs[] = $query->newExpr()->eq('RemittanceBatch.status', $st);
            }

            $statusCase = $query->newExpr()
                ->addCase(
                    $exprs,
                    array_values(RemittanceReportReader::$status_mappings),
                    array_fill(0,count(RemittanceReportReader::$status_mappings),'string')
                );

            $query//->select(['batch_status_text'=>$statusCase,])
                ->select($rmlog)
                ->select($this->RemittanceBatch);
*/
            $query->select($rmlog)
                ->select(['batch_status'=>'b.status','merchant_id'=>'m.id','merchant_name'=>'m.name']);

            /*
                            ->select($this->RemittanceBatch)
                            ->select($merchants);
            */
            if (isset($this->request->query['sort']) && is_array($this->request->query['sort'])) {
                foreach ($this->request->query['sort'] as $sort) {
                    //$field = preg_replace(['/^id$/i','/^merchants_name$/i'],['RemittanceBatch.id','Merchants.name'], $sort['field']);
                    $field = $sort['field'];
                    $query->order([$field => $sort['dir']]);
                }
            } else {
                //remittance_log time
                $query->order(['create_time' => 'desc']);
            }

            $total = $query->count();
            if (isset($this->request->query['page']) && isset($this->request->query['pageSize']))
                $query->limit($this->request->query['pageSize'])
                    ->page($this->request->query['page']);

            $this->log($query, 'debug');
            $res = $query->hydrate(false)
                ->toArray();
            //Add data to Array
            //$this->log($res[0], 'debug');

            foreach ($res as $k=>$r) {
                $converted = (strtoupper($r['currency'])!='CNY');
                if ($converted) {
                    $res[$k]['amount'] = $r['convert_amount'];
                    $res[$k]['currency'] = $r['convert_currency'];
                    $res[$k]['convert_amount'] = $r['amount'];
                    $res[$k]['convert_currency'] = $r['currency'];
                    $res[$k]['convert_rate'] = ($r['convert_rate']>0?1/$r['convert_rate']:null);
                }

                $res[$k]['tx_status_name'] = $this->reader->getLogStatus($r['status']);
                $res[$k]['action_url'] = Router::url(['action' => 'view', $r['batch_id']]);
                $res[$k]['batch_status_text'] = RemittanceReportReader::getStatus($r['batch_status']);

                if (!empty($r['validation'])) {
                    //$this->log($r, 'debug');
                    $jsons = json_decode($r['validation'], true);
                    //$this->log($jsons, 'debug');
                    if (isset($jsons['msg'])) {
                        $msg = trim($jsons['msg']);
                        //default is flagged
                        if (!isset($jsons['flag']) || $jsons['flag'])
                            $res[$k]['flagged'] = $msg;
                        else
                            $res[$k]['blocked'] = $msg;
                    }
                }
            }
            $data = ['status'=>1, 'msg'=>'Success', 'data'=>$res, 'total'=>$total];

            //excel download
            if ($total>0 && isset($this->request->query['type']) && $this->request->query['type']=='excel') {
                $path = $this->saveTxJsonFile($res);
                $xlsurl = Router::url(['action' => 'serveStaticFile', $path]);
                $data = ['status'=>1, 'msg'=>'Success', 'path'=>$xlsurl, 'total'=>$total];
            }
        } //if ajax


        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    private function saveTxJsonFile($data) {
        if (!is_array($data) || count($data)<1)
            return false;

        set_time_limit(180);
                $maps = [
                    'Upload Time'=>'create_time',
                    'Merchant'=>'merchant_name',
                    'Batch ID'=>'batch_id',
                    'Batch Status'=>'batch_status_text',
                    'Tx Status'=>'tx_status_name',
                    'Name'=>'beneficiary_name',
                    'Account No.'=>'account',
                    'Bank Name'=>'bank_name',
                    'Bank Branch'=>'bank_branch',
                    'Province'=>'province',
                    'City'=>'city',
                    'ID Card No.'=>'id_number',
                    'CNY'=>'amount',
                    'Currency'=>'convert_currency',
                    'Converted Amount'=>'convert_amount',
                    'Rate'=>'convert_rate',
                    'Merchant Ref.'=>'merchant_ref',
                    'Gross Amount for Remittance'=>'gross_amount_cny',
                    'Service Charge'=>'fee_cny',
                ];
                $xlsdata = array();
                foreach ($data as $k=>$r) {
                    foreach($maps as $title=>$idx) {
                        //if (isset($r[$idx]))
                        $xlsdata[$k][$title] = (isset($r[$idx])?$r[$idx]:'');
                    }
                }
/*
        $this->log($data[0], 'debug');
        $this->log($xlsdata[0], 'debug');
*/
        //$this->log(array_keys($data[0]), 'debug');

        $path = Configure::read('WC.data_path');

        $f = sprintf("%s/%s/%s_%s", $path, 'xls', 'batch_transaction', md5($this->username.time()));
        //$f = sprintf("%s/%s_%s", 'xls', preg_replace('/\W/', '', strtolower($this->merchant_name)), md5($this->username.time()));

        $f = fromArrayToExcelFile(['BatchTransaction'=>$xlsdata], $f);
        //$f = $this->reader->writeExcelBatchSearchResult($data, "$path$f");
        $this->log("saveTxJsonFile: $f", 'debug');

        return str_replace($path, '', $f);

        /*
        $rtn = fromArrayToExcelFile($xlsdata, "$path$f");
        $this->log("fromArrayToExcelFile: $rtn", 'debug');
        return str_replace($path, '', $rtn);
        */
    }

    public function searchInstant($id=null) {
        $this->log("searchInstant($id)", 'debug');
        //Merchants drop down list
        $merchants = TableRegistry::get('Merchants');
        $query = $merchants->find('list',[
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->where(['remittance_preauthorized >'=>0])  //
            ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();

        $this->set('merchant_lst', $mercdata);
        $this->set('status_lst', RemittanceReportReader::$ir_status_mappings);
        $this->set('target_lst', [3=>'ChinaGPay API', 7=>'GHT API', 14=>'GeoSwift API']);
        $this->set('update_url', Router::url(['action' => 'updateInstantStatus',]));
        $this->set('tx_id', $id);
    }

    /*
     * json of searchInstant
     */
    public function instantJson()
    {
        $this->log('instantJson: query, data, role:'.$this->role, 'debug');
        $this->log($this->request->query, 'debug');

        $data = ['status'=>-1, 'msg'=>'Failed'];
        $total = 0;

        if ($this->request->is(['ajax','get'])) {
            $irlog = TableRegistry::get('InstantRequest');
            $query = $irlog->find()
                ->contain(['Merchants']);
                //->contain(['RemittanceApiLog']);
            /*
                ->matching('RemittanceBatch', function ($q) {
                    //return $q->where(['RemittanceBatch.merchant_id' => $this->merchant_id]);
                    return $q->where(['1' => 1]);
                });
            */

            $test_trans = false;
            //Transaction ID override other criteria
            if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
                foreach ($this->request->query['filter']['filters'] as $filter) {
                    $val = (isset($filter['value'])?trim($filter['value']):'');
                    if (!empty($filter['field']) && $val!='')
                        switch ($filter['field']) {
                            case 'merchant':
                                //$query->where(['RemittanceBatch.merchant_id' => $val]);
                                $query->where(['InstantRequest.merchant_id' => $val]);
                                break;
                            case 'status':
                                $query->where(['InstantRequest.status' => $val]);
                                //$query->where(['RemittanceBatch.status' => $val]);
                                break;
                            case 'name':
                                //$query->where(['InstantRequest.name LIKE' => "%$val%"]);
                                $val = strtolower($val);
                                $query->where(['LOWER(InstantRequest.name) LIKE' => "%$val%"]);
                                break;
                                // ignore other criteria if id exists
                            case 'txid':
                                $query->andWhere(['FALSE'])
                                    ->orWhere(['InstantRequest.id' => $val]) ;
                                break 2;
                            case 'start':
                                $query->where(['InstantRequest.create_time >='=> date('Y/m/d', strtotime($val)).' 00:00:00']);
                                break;
                            case 'end':
                                $enddate = date('Y/m/d 00:00:00',strtotime('+1 day',strtotime($val)));
                                $query->where(['InstantRequest.create_time <='=> $enddate]);
                                break;
                            case 'test_trans':
                                $test_trans = $val;
                                break;
                            default:
                                // case 'account':
                                $query->where([$filter['field'] => $val]);
                        }   //switch
                }
            }

            //$test_trans = ($test_trans>0?1:0);
            $this->log("test_trans: ".($test_trans>0?1:0), 'debug');
            $query->where(['InstantRequest.test_trans'=> ($test_trans>0?1:0)]);

            /*
            $exprs = array();
            // select CASE WHEN ...
            foreach (RemittanceReportReader::$status_mappings as $st=>$name) {
                $exprs[] = $query->newExpr()->eq('RemittanceBatch.status', $st);
            }

            $statusCase = $query->newExpr()
                ->addCase(
                    $exprs,
                    array_values(RemittanceReportReader::$status_mappings),
                    array_fill(0,count(RemittanceReportReader::$status_mappings),'string')
                );

            $query//->select(['batch_status_text'=>$statusCase,])
                ->select($rmlog)
                ->select($this->RemittanceBatch);
            //->select($merchants);
*/
            if (isset($this->request->query['sort']) && is_array($this->request->query['sort'])) {
                foreach ($this->request->query['sort'] as $sort) {
                    //$field = preg_replace(['/^id$/i','/^merchants_name$/i'],['RemittanceBatch.id','Merchants.name'], $sort['field']);
                    $field = $sort['field'];
                    $query->order([$field => $sort['dir']]);
                }
            } else {
                //remittance_log time
                $query->order(['create_time' => 'desc']);
            }

            $total = $query->count();
            if (isset($this->request->query['page']) && isset($this->request->query['pageSize']))
                $query->limit($this->request->query['pageSize'])
                    ->page($this->request->query['page']);

            $this->log($query, 'debug');
            $res = $query->hydrate(false)
                ->toArray();
            //Add data to Array
            //$this->log($res[0], 'debug');

            foreach ($res as $k=>$r) {
                $txid = $r['id'];
                $res[$k]['merchant_name'] = $r['merchant']['name'];
                $res[$k]['status_name'] = RemittanceReportReader::getInsReqStatus($r['status']);

                $res[$k]['target_name'] = RemittanceReportReader::getTargetName($r['target']);
                //$res[$k]['remarks'] = $this->reader->getProcessorApiResponseMessage($txid);
                $remarks = $this->reader->getProcessorApiResponseMessage($txid);
                if (empty($remarks) && !empty($r['filter_remarks'])) {
                    $jsons = json_decode($r['filter_remarks'], true);
                    if (isset($jsons['msg']))
                        $remarks = $jsons['msg'];
                } elseif (! empty($r['validation'])) {
                    //{"trans_id":"","error_code":500,"error_msg":"Insufficient fund"}
                    $verrors = @json_decode($r['validation'], true);
                    if (!is_null($verrors) && isset($verrors['error_msg']))
                        $remarks = $verrors['error_msg'];
                }
                $res[$k]['remarks'] = trim($remarks);
                //flag , not block
                if (!empty($r['filter_flag'])) {
                    //$this->log($r, 'debug');
                    $jsons = json_decode($r['filter_remarks'], true);
                    //$this->log($jsons, 'debug');
                    if (isset($jsons[0]['msg']))
                        $res[$k]['flagged'] = trim($jsons[0]['msg']);
                }

                $res[$k]['action_text'] =  null;
                // Set status to OK or Failed if the status is in Processing, OK or Failed state.
                if (in_array($r['status'], [RemittanceReportReader::IR_STATUS_PROCESSING, RemittanceReportReader::IR_STATUS_OK, RemittanceReportReader::IR_STATUS_FAILED])) {
                    //[TODO] add func to update
                    if ($this->role == 'admin') {
                        switch ($r['status']) {
                            case RemittanceReportReader::IR_STATUS_FAILED:
                                $res[$k]['action_text'] = 'OK';
                                $res[$k]['next_status'] = RemittanceReportReader::IR_STATUS_OK;
                                break;
                            case RemittanceReportReader::IR_STATUS_OK:
                                $res[$k]['action_text'] = 'Failed';
                                $res[$k]['next_status'] = RemittanceReportReader::IR_STATUS_FAILED;
                                break;
                            case RemittanceReportReader::IR_STATUS_PROCESSING:
                                $res[$k]['action_text'] = 'OK';
                                $res[$k]['next_status'] = RemittanceReportReader::IR_STATUS_OK;
                                $res[$k]['action_text2'] = 'Failed';
                                $res[$k]['next_status2'] = RemittanceReportReader::IR_STATUS_FAILED;
                                break;
                            //Router::url(['action' => 'update_instant', $txid]);
                        }
                    }
                } else {

                }
                //adminUpdateInstantRequestStatus($id, $status)
            }
            $data = ['status'=>1, 'msg'=>'Success', 'data'=>$res, 'total'=>$total];

            //excel download
            if ($total>0 && isset($this->request->query['type']) && $this->request->query['type']=='excel') {
                $path = $this->saveInstantJsonFile($res);
                $xlsurl = Router::url(['action' => 'serveStaticFile', $path]);
                $data = ['status'=>1, 'msg'=>'Success', 'path'=>$xlsurl, 'total'=>$total];
            }
        } //if ajax


        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    private function saveInstantJsonFile($data) {
        if (!is_array($data) || count($data)<1)
            return false;

        $maps = [
            'Time'=>'create_time',
            'Merchant'=>'merchant_name',
            'Beneficiary Name'=>'name',
            'Beneficiary Account No.'=>'account',
            'Bank Name'=>'bank_name',
            'Bank Branch'=>'bank_branch',
            'Province'=>'province',
            'City'=>'city',
            'ID Card No.'=>'id_number',
            //'CNY'=>'amount',
            'Transaction Amount Received'=>'amount',
            'Transaction Amount Client Received'=>'gross_amount_cny',
            'Gross Amount for Remittance'=>'gross_amount_cny',
            'Service Charge'=>'fee_cny',
            'Amount paid by Merchant'=>'paid_amount',
            'Currency'=>'convert_currency',
            'Converted Amount paid by Merchant'=>'convert_paid_amount',
            'Exchange Rate'=>'convert_rate',
            'Merchant Reference'=>'merchant_ref',
            'ID Card Type'=>'id_type',
            'Status'=>'status_name',
            'Trans ID'=>'id',
        ];
        $xlsdata = array();
        foreach ($data as $k=>$r) {
            foreach($maps as $title=>$idx) {
                $xlsdata[$k][$title] = (isset($r[$idx])?$r[$idx]:'');
            }
        }

        //$this->log($xlsdata[0], 'debug');

        $path = Configure::read('WC.data_path');

        $f = sprintf("%s/%s/%s_%s", $path, 'xls', 'instant_transaction', md5($this->username.time()));
        //$f = sprintf("%s/%s_%s", 'xls', preg_replace('/\W/', '', strtolower($this->merchant_name)), md5($this->username.time()));

        //[TODO] fix number format
        //$f = fromArrayToExcelFile(['InstantTransaction'=>$xlsdata], $f);
        $f = $this->reader->writeExcelInstantSearchResult($data, $f);
        $this->log("saveTxJsonFile: $f", 'debug');

        return str_replace($path, '', $f);
    }

    public function updateInstantStatus() {
        $this->log('updateInstantStatus', 'debug');
        $this->log($this->request->data, 'debug');

        $data = ['status'=>-1, 'msg'=>'Failed'];

        if ($this->request->is('ajax')) {
            $id = $this->request->data['id'];
            $status = $this->request->data['status'];

            if ($this->reader->adminUpdateInstantRequestStatus($id, $status))
                $data = ['status'=>1, 'msg'=>'Success'];
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function indexDev()
    {
        //Merchants drop down list
        $merchants = TableRegistry::get('Merchants');
        $query = $merchants->find('list',[
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();
        $this->set('merchant_lst', $mercdata);
        $this->set('status_lst',RemittanceReportReader::$status_mappings);
    }

    /*
     * Batch search json
     */
    public function jsonList()
    {
        $this->log('jsonList: query, data', 'debug');
        //$this->log($this->request->query, 'debug');
        $merchants = TableRegistry::get('Merchants');

        $data = ['status'=>-1, 'msg'=>'Failed'];
        if ($this->request->is(['ajax','get'])) {
            $query = $this->RemittanceBatch->find('all')
                ->where(['status' => RemittanceReportReader::BATCH_STATUS_QUEUED])
                ->where(['RemittanceBatch.upload_time >=' => date('Y/m/d 00:00:00', strtotime('-14 days'))])
                ->contain(['Merchants']);

            if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
                $query = $this->RemittanceBatch->find('all')
                    //->order(['upload_time' => 'desc'])
                    ->contain(['Merchants']);

                $timetype = 'upload_time';  //default time field
                foreach ($this->request->query['filter']['filters'] as $filter) {
                    $val = trim($filter['value']);
                    if ($val!='')
                    switch ($filter['field']) {
                        case 'status':
                            $query->where(['RemittanceBatch.status' => $val]);
                            break;
                        case 'merchant':
                            $query->where(['merchant_id' => $val]);
                            break;
                        case 'batch_id':
                            $query->where(['RemittanceBatch.id' => $val]);
                            break;
                        case 'timetype':
                            $timetype = $val;
                            break;
                        case 'start':
                            //$query->where(['RemittanceBatch.upload_time >='=> date('Y/m/d', strtotime($val)).' 00:00:00']);
                            $startdate = date('Y/m/d 00:00:00', strtotime($val));
                            break;
                        case 'end':
                            $enddate = date('Y/m/d 00:00:00',strtotime('+1 day',strtotime($val)));
                            //$query->where(['RemittanceBatch.upload_time <='=> $enddate]);
                            break;

                    }   //switch
                }
                $this->log("timetype=$timetype", 'debug');
                if (isset($startdate))
                    $query->where(["RemittanceBatch.$timetype >="=> $startdate]);
                if (isset($enddate))
                    $query->where(["RemittanceBatch.$timetype <="=> $enddate]);

            }
            /*
            $query->map(function ($row) { // map() is a collection method, it executes the query
                $row->statusname = 'test:'.$row->status;
                return $row;
            });
*/

            $exprs = array();
            foreach (RemittanceReportReader::$status_mappings as $st=>$name) {
                $exprs[] = $query->newExpr()->eq('status', $st);
            }

            $statusCase = $query->newExpr()
            ->addCase(
                    $exprs,
                    array_values(RemittanceReportReader::$status_mappings),
                    array_fill(0,count(RemittanceReportReader::$status_mappings),'string')
                );
            //$target_mappings
            $exprs = array();
            foreach (RemittanceReportReader::$target_mappings as $st=>$name) {
                $exprs[] = $query->newExpr()->eq('target', $st);
            }
            $targetCase = $query->newExpr()
                ->addCase(
                    $exprs,
                    array_merge(array_values(RemittanceReportReader::$target_mappings), ['N/A']),
                    array_fill(0,count(RemittanceReportReader::$target_mappings)+1,'string')
                );

            //select case & all fields
            $query->select(['status_text'=>$statusCase,'target_name'=>$targetCase])
                ->select($this->RemittanceBatch)
                ->select($merchants);
                //->contain(['Merchants']);
            //$query->order(['status_name' => 'desc']);
            //sorting

            if (isset($this->request->query['sort']) && is_array($this->request->query['sort'])) {
                foreach ($this->request->query['sort'] as $sort) {
                    //$field = str_replace(['id','merchants_name'],['RemittanceBatch.id','Merchants.name'], $sort['field']);
                    $field = preg_replace(['/^id$/i','/^merchants_name$/i'],['RemittanceBatch.id','Merchants.name'], $sort['field']);
                    $query->order([$field => $sort['dir']]);
                }
            } else {
                $query->order(['upload_time' => 'desc']);
            }

            $total = $query->count();
            if (isset($this->request->query['page']) && isset($this->request->query['pageSize']))
                $query->limit($this->request->query['pageSize'])
                    ->page($this->request->query['page']);

            //$this->log($query->__debugInfo(), 'debug');
            $this->log($query, 'debug');
            $res = $query->toArray();
            //Add data to Array
            foreach ($res as $k=>$r) {
                if (isset($r['merchant']->name))
                    $res[$k]['merchants_name'] = $r['merchant']->name;

                //$res[$k]['status_name'] = RemittanceReportReader::getStatus($r['status']);
                //$res[$k]['target_name'] = RemittanceReportReader::getTargetName($r['target']);
                switch ($r['status']) {
                    case RemittanceReportReader::BATCH_STATUS_QUEUED :
                        $res[$k]['action'] = 'Process';
                        break;
                    case RemittanceReportReader::BATCH_STATUS_PROCESS :
                        $res[$k]['action'] = 'Update';
                        break;
                    default: $res[$k]['action'] = 'View';
                }
                $res[$k]['action_url'] = Router::url(['action' => 'view', $r['id']]);

/*
                $currencys = $this->reader->getBatchCurrencys($r['id']);
                if (is_array($currencys)) {
                    //$res[$k] = array_merge($res[$k], $currencys);
                    if ($currencys['currency']=='CNY') {
                        $res[$k]['currency'] = $currencys['currency'];  //CNY
                        $res[$k]['convert_currency'] = $currencys['convert_currency'];
                    } else {
                        $res[$k]['currency'] = $currencys['convert_currency'];  //CNY
                        $res[$k]['convert_currency'] = $currencys['currency'];
                    }
                }
                //$this->log($currencys, 'debug');
*/
                // new fields of batch: currency, convert_currency
                if ($r['currency']!='CNY') {
                    $res[$k]['non_cny'] = $r['currency'];
                } else {
                    $res[$k]['non_cny'] = $r['convert_currency'];
                }
                //to fix old records
                if (empty($res[$k]['non_cny']) && isset($r['total_usd'])) {
                    $res[$k]['non_cny'] = 'USD';
                }
          }

            $data = ['status'=>1, 'msg'=>'Success', 'data'=>$res, 'total'=>$total];

            //excel download
            if ($total>0 && isset($this->request->query['type']) && $this->request->query['type']=='excel') {
                $path = $this->saveBatchJsonFile($res);
                $xlsurl = Router::url(['action' => 'serveStaticFile', $path]);
                $data = ['status'=>1, 'msg'=>'Success', 'path'=>$xlsurl, 'total'=>$total];
            }
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    private function saveBatchJsonFile($data) {
        if (!is_array($data) || count($data)<1)
            return false;

        set_time_limit(180);
        $maps = [
            'Batch ID'=>'id',
            'Merchant'=>'merchants_name',
            'Upload Time'=>'upload_time',
            //'Approval Time'=>'approve_time',
            'Complete Time'=>'complete_time',
            'Count'=>'count',
            'Currency'=>'convert_currency',
            'Converted Amount'=>'total_usd',
            'CNY Amount'=>'total_cny',
            'Rate'=>'complete_convert_rate',
            'Total CNY fee'=>'fee_cny',
            'Total CNY Paid by Merchant'=>'total_paid_amount_cny',
            'Status'=>'status_text',
            'Channel'=>'target_name',
        ];
        $nums = ['total_usd', 'total_cny', 'fee_cny', 'total_paid_amount_cny'];
        $xlsdata = array();
        foreach ($data as $k=>$r) {
            /*
            $batches = $this->reader->getBatchDetails($r['id']);
            $this->log($batches[0], 'debug');
*/
            if (isset($r['total_cny']))
                $r['total_paid_amount_cny'] = $r['total_cny'] + $r['fee_cny'];
            //not Completed
            if ($r['status'] != RemittanceReportReader::BATCH_STATUS_COMPLETED) {
                unset($r['fee_cny']);
                unset($r['total_paid_amount_cny']);
            }

            foreach($maps as $title=>$idx) {
                //if (isset($r[$idx]))
                /*
                if (in_array($idx, $nums))
                    $r[$idx] = number_format($r[$idx], 2);
                */
                $xlsdata[$k][$title] = (isset($r[$idx])?$r[$idx]:'');
            }
        }
        /*
                $this->log($data[0], 'debug');
                $this->log($xlsdata[0], 'debug');
        */
        //$this->log(array_keys($data[0]), 'debug');

        $path = Configure::read('WC.data_path');

        $f = sprintf("%s/%s/%s_%s", $path, 'xls', 'batch_search', md5($this->username.time()));

        $f = fromArrayToExcelFile(['BatchSearch'=>$xlsdata], $f);
        //$f = $this->reader->writeExcelBatchSearchResult($data, "$path$f");
        $this->log("saveTxJsonFile: $f", 'debug');

        return str_replace($path, '', $f);
    }

    /*
     * Display upload form
     */
    public function upload()
    {
        //Merchants drop down list
        $merchants = TableRegistry::get('Merchants');
        $query = $merchants->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])
            ->where(['processor_account_type'=>1])  //online bank
            ->order(['name' => 'ASC']);
        $mercdata = $query->toArray();
        $this->set('merchant_lst', $mercdata);
        //$this->log($mercdata,'debug');

        if (!empty($this->request->data)) {
            $this->log('handle form:' . var_export($this->request->data, TRUE), 'info');
            //check uploaded file
            $file = $this->request->data['upfile'];
            $pdfile = $this->request->data['pdfile'];

            if (empty($file['name'])) {
                $this->Flash->error('Records not be saved. Please try again.');
                return null;
            }
            //file upload error
            if (is_array($pdfile) && $pdfile['error']!='0') {
                $this->Flash->error('PDF file upload error. Please try again.');
                return null;
            }

            $this->post_api_excel();
        }
        //replace with API call
        /*
        if (!empty($this->request->data)) {
            $this->log('handle form:' . var_export($this->request->data, TRUE), 'info');
            $this->handle_form(!empty($this->request->data['confirm']));
        }
*/

    }

/*
 * Post excel & pdf file to remittance API
 */
    public function post_api_excel($isConfirm=FALSE)
    {
        $authfile = null;
        $file = $this->request->data['upfile'];

        //Allow no pdf for remittance_preauthorized merchant
        if (isset($this->request->data['pdfile'])) {
            $pdfile = $this->request->data['pdfile'];
            //error in upload
            if (!isset($pdfile['tmp_name']))
                return false;
            $authfile = new \CurlFile($pdfile['tmp_name'], $pdfile['type'], $pdfile['name']);
        }
        /*
        $this->log($file, 'info');
        $this->log($pdfile, 'info');
*/
        if (!isset($file['tmp_name']))
            return false;

        $data = [
            'username' => $this->username,
            //'merchant_id' => $this->merchant_id,
            'merchant_id' => $this->request->data['merchant'],
            //override check merchant (remittance_api_enabled)
            'bypass_api_checkauth' => 'true',
            'excel_file' => new \CurlFile($file['tmp_name'], $file['type'], $file['name']),
            'authorization_pdf' => $authfile,
            //'excel_file' => '@'.$file['tmp_name'].';filename='.$file['name'],
            //'authorization_pdf' => '@'.$pdfile['tmp_name'].';filename='.$pdfile['name'],
        ];

        //$url = self::API_BASE_URL . 'batch_request/excel';
        $url = $this->excel_api_url;
        $this->log("upload to URL now:$url", 'debug');
        $return = simpleCallURL($url, $data); //POST

        $this->log($data, 'debug');
        $this->log("URL:$url\nResult:$return", 'debug');
        if (empty($return))
            return FALSE;

        $jsons = json_decode($return, true);

        $this->log($jsons, 'debug');
        $this->set(compact('jsons'));
    }

    public function handle_form($isConfirm=FALSE) {
        $this->log("handle_form($isConfirm)",'debug');
        $dataKey = 'WC.formData';
        if ($isConfirm && !empty($this->request->session()->read($dataKey))) {
            $this->request->data = $this->request->session()->read($dataKey);
        }
        $this->log($this->request->data,'debug');

        if (empty($this->request->data['upfile']['name'])) {
            $this->Flash->error('Records not be saved. Please try again.');
            return FALSE;
        }
        $file = $this->request->data['upfile'];
        $pdfile = $this->request->data['pdfile'];
        $merchant = $this->request->data['merchant'];
        $currency = '';
        if (isset($this->request->data['currency']))
            $currency = strtoupper($this->request->data['currency']);
        $timestamp = date('YmdHis');
        //$isConfirm = !empty($this->request->data['confirm']);

        $ext = substr(strtolower(strrchr($file['name'], '.')), 1); //get the extension
        $ok_exts = array('xls', 'xlsx'); //set allowed extensions
        //drivewealth_20160714114411_a899a92bc593888954f7e0faaff81c4c.xlsx
        $basepath = Configure::read('WC.data_path');

        //only process if the extension is valid
        if (in_array($ext, $ok_exts)) {
            //$basepath = WWW_ROOT;
            if ($isConfirm) {
                $newFileName = $file['tmp_name'];
            } else {
                $newFileName = sprintf("%s_%s_%s_%s.%s", 'remittance', $merchant, $timestamp, md5_file($file['tmp_name']), $ext);
                $this->log('move_uploaded_file:'."{$file['tmp_name']} \r\n to $basepath$newFileName", 'info');
                move_uploaded_file($file['tmp_name'], $basepath.$newFileName);
                $this->request->data['upfile']['tmp_name'] = $newFileName;
            }
            //move_uploaded_file($file['tmp_name'], WWW_ROOT.DS.'data'.DS.$newFileName);
            $datafile = "$basepath$newFileName";
            $md5sum = md5_file($datafile);

            //handle pdf file
            if ($isConfirm && !empty($pdfile['tmp_name'])) {
                //use existing file
                $pdf_path = $pdfile['tmp_name'];
            } elseif (is_array($pdfile) && !empty($pdfile['tmp_name'])) {
                $ext2 = substr(strtolower(strrchr($pdfile['name'], '.')), 1); //get the extension
                $newFileName = sprintf("%s_%s_%s_%s.%s", 'remittance', $merchant, $timestamp, md5_file($pdfile['tmp_name']), $ext2);
                $pdf_path = $basepath.$newFileName;
                $this->log('move_uploaded_file pdf:'."{$pdfile['tmp_name']} \r\n to $pdf_path", 'info');
                move_uploaded_file($pdfile['tmp_name'], $pdf_path);
                $this->request->data['pdfile']['tmp_name'] = $pdf_path;
            } else {
                $pdf_path = '';
            }
            //$uac = new UploadActivityController();

            $auths = $this->request->session()->read('Auth.User');
            $username = $auths['username'];

            try {
                $db_name = ConnectionManager::get('default')->config()['database'];
                $reader = new RemittanceReportReader($db_name);
                $reader->username = $username;
                $reader->ip = $this->request->clientIp();

                $batchid = $reader->isBatchExist($md5sum);
                //not required id card no for back office
                $reader->setExcelSkippedFields(['id card no']);

                //confirm if overwrite
                if (!$isConfirm && $batchid!==false) {
                    $this->request->session()->write($dataKey, $this->request->data);
                    $this->log("Flash to confirm overwrite batch", 'info');

                    $this->Flash->confirm(sprintf("Batch %s exists already, Confirm to insert new?", $batchid));
                    return FALSE;
                }

                $jsons = $reader->handleExcelFile($merchant, $datafile, $pdf_path);
                //remove invalid file
                if ($jsons['code']!==0) {
                    unlink($datafile);
                    if (!empty($pdf_path) && is_file($pdf_path))
                        unlink($pdf_path);
                } else {
                    //batch created
                    $reader->addNotificationLogs($merchant, $jsons['batch_id']);
            		//return ['code' => 0, 'msg' => 'Remittance validation ok', 'data' => NULL, 'batch_id' => $batch_id];

		        }

            } catch (\Exception $e) {
                $this->log("Exception: $e", 'debug');
                //$this->Flash->error("Error in file: ".$e->getMessage());
                //return FALSE;
                //$jsons = ['code'=>2, 'msg'=>'Excel file unreadable'];
                $jsons = ['code'=>-2, 'msg'=>'Excel file invalid'];
            }


            /*
            $output_file=sprintf("%s/json/%s_%s_%s.json",dirname(__FILE__), $reader->merchant, $reader->date_tx, date('Ymd_His'));
            $reader->saveJSON($output_file);
            */
            $this->log("merchant:".$reader->merchant_id, 'info');

            //$this->log("json:".$output_file, 'info');
            $this->request->session()->delete($dataKey);
        } else {
            $this->log("handle_form: $ext not supported", 'info');
            //$this->Flash->error('File type not supported.');
            //$jsons = ['code'=>2, 'msg'=>'Excel file unreadable'];
            $jsons = ['code'=>-2, 'msg'=>'Excel file invalid'];
            //return FALSE;
        }

        $this->log($jsons, 'debug');
        $this->set(compact('jsons'));

        /*
        if ($isConfirm) {
            $this->Flash->success("Record has been updated with {$file['name']}.");
        } else {
            $this->Flash->success($file['name']." has been uploaded.");
        }
        */
    }
    /**
     * View batch details method
     *
     * @param string|null $id Remittance Batch id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        /*
        $remittanceBatch = $this->RemittanceBatch->get($id, [
            'contain' => ['Merchants']
        ]);
*/
        $db_name = ConnectionManager::get('default')->config()['database'];
        $reader = new RemittanceReportReader($db_name);
        $remittanceBatch = $reader->getBatchDetails($id);

        //$this->log("view ($id, $json)", 'debug');
        if ($this->role == 'admin' ) {
            $remittanceBatch[0]['admin_role'] = true;
            if (isset($remittanceBatch[0]['status']) && $remittanceBatch[0]['status']==RemittanceReportReader::BATCH_STATUS_COMPLETED)
                $remittanceBatch[0]['admin_action']= true;  //show action col
        }

        $this->set('remittanceBatch', $remittanceBatch);
        $this->set('_serialize', ['remittanceBatch']);

    }

    public function viewJSON($id = null) {
        $this->log("viewJSON ($id), role:".$this->role, 'debug');

        if (!$this->request->is('ajax')) {
            return false;
        }
        /*
        $db_name = ConnectionManager::get('default')->config()['database'];
        $reader = new RemittanceReportReader($db_name);
        */
        $remittanceBatch = $this->reader->getBatchDetails($id, true);
        //sort($remittanceBatch);
        $batchs = array();  //ordered data

        if (is_array($remittanceBatch)) {
            $idx=1;
            foreach ($remittanceBatch as $k=>$batch) {
                $remittanceBatch[$k]['index'] = $idx;
                // switch value if currency is not CNY
                if ($batch['currency']=='CNY') {
                    $convert_rate = $batch['convert_rate'];
                    $amount = floatval($batch['amount']);
                    $amountc = floatval($batch['convert_amount']);
                    $remittanceBatch[$k]['non_cny'] = $batch['convert_currency'];
                } else {
                    $floatrate = floatval($batch['convert_rate']);
                    $convert_rate = ($floatrate>0?1/$floatrate:null);
                    $amount = floatval($batch['convert_amount']);
                    $amountc = floatval($batch['amount']);
                    $remittanceBatch[$k]['non_cny'] = $batch['currency'];
                }
                $remittanceBatch[$k]['convert_rate'] = $convert_rate;
                $remittanceBatch[$k]['amount'] = $amount;
                $remittanceBatch[$k]['convert_amount'] = $amountc;
                $remittanceBatch[$k]['action_class']= 'act-fail';

                if ($batch['tx_status_name']!='OK') {
                    $remittanceBatch[$k]['action']=$remittanceBatch[$k]['action_txt']='OK';
                    $remittanceBatch[$k]['action_class']= 'act-ok';
                    $remittanceBatch[$k]['action_val']= RemittanceReportReader::RM_STATUS_OK;
                   // $remittanceBatch[$k]['action_url'] = $this->Html->link(__('OK'),'javascript:void(0)',['class'=>'act-ok','data-id'=>$batch['id'], 'data-bid'=>$batch['batch_id']] );
                } else {
                    $remittanceBatch[$k]['action']='Failed';
                    $remittanceBatch[$k]['action_txt']='Failed';
                    $remittanceBatch[$k]['action_val']= RemittanceReportReader::RM_STATUS_FAILED;
                    //$remittanceBatch[$k]['action_class']= 'act-fail';
                   // $remittanceBatch[$k]['action_url'] = $this->Html->link(__('Failed'),'javascript:void(0)',['class'=>'act-fail','data-id'=>$batch['id'], 'data-bid'=>$batch['batch_id']]);
                }
                // System Admin action
                if ($this->role == 'admin') {

                    if ($remittanceBatch[0]['status']==RemittanceReportReader::BATCH_STATUS_COMPLETED) {
                        if (strpos($remittanceBatch[$k]['tx_status_name'],'OK')!==false) {
                            //$remittanceBatch[0]['admin_action']= true;  //show action col
                            $remittanceBatch[$k]['action'] = 'Failed';
                            $remittanceBatch[$k]['action_txt'] = 'Failed';
                            $remittanceBatch[$k]['action_val'] = RemittanceReportReader::RM_STATUS_FAILED_AMENDED;
                        } else {
                            //failed to ok
                            $remittanceBatch[$k]['action'] = 'OK';
                            $remittanceBatch[$k]['action_txt'] = 'OK';
                            $remittanceBatch[$k]['action_val'] = RemittanceReportReader::RM_STATUS_OK_AMENDED;
                        }
                    }
                }

                if (!empty($batch['validation'])) {
                    $jsons = json_decode($batch['validation'], true);
                    //$this->log($jsons, 'debug');
                    if (isset($jsons['msg'])) {
                        $msg = trim($jsons['msg']);
                        //default is flagged
                        if (!isset($jsons['flag']) || $jsons['flag'])
                            $remittanceBatch[$k]['flagged'] = $msg;
                        else
                            $remittanceBatch[$k]['blocked'] = $msg;
                    }
                }

                $batchs[] = $remittanceBatch[$k];
                $idx++;
            }


        }

        $this->log("viewJSON ttl:".count($remittanceBatch), 'debug');

        $this->response->type('json');
        //$data = ['data'=>$remittanceBatch, 'total'=>count($remittanceBatch)];
        $data = ['data'=>$batchs, 'total'=>count($batchs)];
        $this->response->body(json_encode($data));

        return $this->response;
    }

    /*
     * return json for Target drop down menu
     */
    public function targetJSON() {
        $usrs = $this->request->session()->read('Auth.User');
        $role = $usrs['role'];

        $this->log("targetJSON: role=$role", 'debug');

        $this->response->type('json');
        //$data = [1=>'Payment Asia Excel', 2=>'ChinaGPay Excel', 6=>'GHT Excel',];
        $data = [10=>'Payment Asia Excel (Local)', 2=>'ChinaGPay Excel', 11=>'JoinPay Excel', 6=>'GHT Excel'];
        // Manager Approve
        if ($role=='manager')  //  if (true)
            $data = $data + [3=>'ChinaGPay API', 7=>'GHT API'];

        foreach ($data as $k=>$v) {
            $json[] = ['text'=>$v, 'value'=>$k];
        }

        //$data = ['data'=>$batchs, 'total'=>count($batchs)];
        $this->response->body(json_encode($json));
        return $this->response;
    }

    public function apiLogJson()
    {
        $bid = $this->request->data['batch_id'];
        $id = $this->request->data['id'];

        $this->log("apiLogJSON ($bid, $id), role:" . $this->role, 'debug');

        if (!$this->request->is('ajax') || empty($bid) || empty($id)) {
            return false;
        }

        $table = TableRegistry::get('RemittanceApiLog');
        $query = $table->find()
                ->where(['log_id' => $id, 'batch_id' => $bid])
                ->order(['complete_time'=>'desc']);
        $count = $query->count();
        $row = $query->first();

        if (!empty($row->return_msg)) {
            //table new column
            $data = trim($row->return_msg);
        } else {
            $return = (empty($row->callback) ? $row->response : $row->callback);
            $this->log("response:" . $return, 'debug');
            //$this->log("callback:" . $row->callback, 'debug');

            $responses = $this->reader->getProcessorApiResponse($row->processor, $return);
            $this->log($responses, 'debug');
            if (isset($responses['decodeMsg']))
                $data = $responses['decodeMsg'];
            elseif (isset($responses['ERR_MSG']))
                $data = $responses['ERR_MSG'];

        }
        $this->response->type('json');
        if ($count>0)
            $data = ['msg'=>$data , 'total'=>$count, 'status'=>0];
        else
            $data = ['total'=>$count, 'status'=>-1];
        $this->response->body(json_encode($data));

        return $this->response;
    }

    //serve excel, pdf file
    public function serveStaticFile($f) {
        $path = Configure::read('WC.data_path').$f;
        if (!is_readable($path))
            $this->response->body(NULL);
        else
            $this->response->file($path,['download' => true, 'name' => basename($f)]);

        return $this->response;
    }

    public function updateStatus()
    {
        $this->log('updateStatus: query, data', 'debug');
        $this->log($this->request->data, 'debug');
        $data = ['status'=>-1, 'msg'=>'Failed'];

        if ($this->request->is('ajax')) {
            //update target only
            if (isset($this->request->data['set_target'])) {
                return $this->updateBatchTarget();
            }
            /*
            $db_name = ConnectionManager::get('default')->config()['database'];
            $reader = new RemittanceReportReader($db_name);
            */
            $id = $this->request->data['batch_id'];
            $status_int = RemittanceReportReader::getStatusVal($this->request->data['status']);
            $now = date('Y-m-d H:i:s');
            $quote_rate = $this->request->data['q_rate'];
            $complete_rate = $this->request->data['c_rate'];
            $target = $this->request->data['target'];

            $remittanceBatch = $this->RemittanceBatch->get($id, [
            'contain' => [] ]);

            $mid = $remittanceBatch['merchant_id'];
            $dbrate = $this->reader->getBatchRate($remittanceBatch);
            $this->wallet = new MerchantWallet($mid);
            $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
            $wallet_sym = $this->wallet->getWalletCurrency();
            $this->log("Wallet ID:$wallet_id, Currency: $wallet_sym", 'debug');

            $this->log("status: $status_int, db rate: $dbrate", 'debug');
            //$this->log($remittanceBatch, 'debug');
            $updates = ['status'=>$status_int, 'update_time'=>$now];

            //check balance before approval
            $paid_amount = $this->reader->getBatchPaidAmount($id, $success=true, $wallet_sym);
            if ($status_int==RemittanceReportReader::BATCH_STATUS_PROCESS) {
                $balance_ok = $this->wallet->checkBalance($paid_amount);
                $balance = $this->wallet->getBalance();
                $this->log("ID:$id\nbalance OK: $balance_ok\npaid_amount: ($wallet_sym)$paid_amount\nbalance: ($wallet_sym)$balance", 'debug');

                if ($paid_amount === false || !$balance_ok) {
                    //$data = ["status" => -3, "msg" => "Insufficient balance"];
                    $data = ["status" => -3, "msg" => sprintf("Merchant has insufficient account balance, current balance at %s %.2f", $wallet_sym, $balance)];
                } elseif ($this->reader->isProcessorTargetApi($target)) {
                //check API banks when Approve
                //if ($status_int == RemittanceReportReader::BATCH_STATUS_PROCESS && $this->reader->isProcessorTargetApi($target)) {
                    // Manager Approve
                    if ($this->role == 'manager') {
                        $targetname = RemittanceReportReader::getTargetName($target);
                        $batchs = $this->reader->getBatchDetails($id);
                        $this->log("target: $targetname", 'debug');
                    } else {
                        //not allowed
                        $batchs = null;
                        $data = ['status' => -3, 'msg' => 'Channel not allowed'];
                    }

                    if (is_array($batchs)) {
                        foreach ($batchs as $batch) {
                            $code = $this->reader->getProcessorApiBankCode($batch['bank_code'], $targetname);
                            if (!$code) {
                                //“Please select another channel, <Processor> API cannot process remittance for <Bank name>”
                                $data = ['status' => -2, 'msg' => 'Failed', 'processor' => $targetname, 'bank' => $batch['bank_name']];
                                break;
                            }
                        }
                        // All banks good
                        if ($code) {
                            //save API requests
                            $this->loadModel('Queue.QueuedJobs');
                            foreach ($batchs as $batch)
                                if ($this->reader->isValidRemittance($batch)) {   //not rejected
                                    $job = $this->QueuedJobs->createJob('RemittanceApi', ['api' => $target, 'batch_id' => $id, 'id' => $batch['id']]);
                                    $this->log("remittance_log ID: {$batch['id']}, QueuedJobs ID:" . $job->id, 'debug');
                                }
                        }
                    }
                }
            }   //END approval

            if ($data['status']==-1 && RemittanceReportReader::isValidStateChange($remittanceBatch['status'], $status_int)) {
                //update batch details
                switch($status_int) {
                    //updateBatch($id, $rate_update=TRUE, $quote_rate=0, $complete_rate=0)
                    //Approve
                    case RemittanceReportReader::BATCH_STATUS_PROCESS:
                        //set default rate
                        // update target first
                        //$this->reader->updateBatch($id, TRUE, $quote_rate);

                        $updates['target'] = $this->request->data['target'];
                        $updates['approve_time'] = $now;
                        $updates['approve_by'] = $this->username;
                        $remittanceBatch = $this->RemittanceBatch->patchEntity($remittanceBatch, $updates);
                        if ($this->RemittanceBatch->save($remittanceBatch)) {
                            //return
                            $data = ['status' => 0, 'msg' => 'Success'];
                            $updates = null;
                            $this->reader->updateBatch($id, TRUE, $quote_rate);
                            //check if fx rate updated
                            if ($quote_rate != $dbrate) {
                                /*
                                $paid_amount = $this->reader->getBatchPaidAmount($id, $success=true);
                                $this->log("new paid amount: $paid_amount", 'debug');

                                //$this->wallet->revokeTransaction(\MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT, $id);
                                $this->wallet->revokeTransaction(\MerchantWallet::TYPE_BATCH_REMITTANCE, $id);
                                $wallet_status = $this->wallet->addTransaction("-$paid_amount", \MerchantWallet::TYPE_BATCH_REMITTANCE, $dsc = '', $id);
                                */
                            }
                            //deduct batch balance
                            $paid_amount = $this->reader->getBatchPaidAmount($id, $success=true, $wallet_sym);
                            $wallet_status = $this->wallet->addTransaction("-$paid_amount", MerchantWallet::TYPE_BATCH_REMITTANCE, $dsc = '', $id);
                            if (!$wallet_status) {
                                //$this->reader->updateBatchLogStatus($id, RemittanceReportReader::RM_STATUS_REJECTED);
                                $this->reader->setBatchStatus($id, RemittanceReportReader::BATCH_STATUS_DECLINED);

                                $data = ["status"=> -3, "msg"=> "Failed to deduct balance"];
                            }
                            $this->reader->addNotificationLogs($mid, $id);
                        }

                        break;
                    case RemittanceReportReader::BATCH_STATUS_COMPLETED:
                        //set default rate
                        if (empty($complete_rate) && $remittanceBatch['quote_convert_rate']>0)
                            $complete_rate = $remittanceBatch['quote_convert_rate'];
                        $this->reader->updateBatch($id, FALSE, 0, $complete_rate);

                        $updates['complete_time'] = $now;
                        //check if fx rate updated
                        /*
                        if ($complete_rate != $dbrate) {
                            $paid_amount = $this->reader->getBatchPaidAmount($id, $success=true);
                            $this->log("new paid amount: $paid_amount", 'debug');

                            $this->wallet->revokeTransaction(\MerchantWallet::TYPE_BATCH_REMITTANCE, $id);
                            $wallet_status = $this->wallet->addTransaction("-$paid_amount", \MerchantWallet::TYPE_BATCH_REMITTANCE, $dsc = '', $id);
                        }
                        */
                        //compare new batch amount with deducted amount
                        $deducted_amount = $this->wallet->getLastTransaction(\MerchantWallet::TYPE_BATCH_REMITTANCE, $id);
                        $paid_amount = $this->reader->getBatchPaidAmount($id, $success=true, $wallet_sym);
                        $this->log("deducted amount=$deducted_amount\nnew paid amount=$paid_amount", 'debug');
                        $this->log("$deducted_amount", 'debug');

                        if ($deducted_amount!==false && $deducted_amount != $paid_amount) {
                            $diff_amount = $deducted_amount - $paid_amount;
                            $diff_amount = round($diff_amount, 2);
                            $this->log("diff=$diff_amount", 'debug');

                            if ($diff_amount != 0)  // +/-, can be minus value
                                $wallet_status = $this->wallet->addTransaction($diff_amount, \MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT, $dsc = '', $id);
                        }
                        break;
                    default:
                        unset($updates['target']);
                }

                //All status except Approval
                if (is_array($updates)) {
                    $remittanceBatch = $this->RemittanceBatch->patchEntity($remittanceBatch, $updates);
                    if ($this->RemittanceBatch->save($remittanceBatch)) {
                        //$this->Flash->success(__('The remittance batch has been saved.'));
                        $data = ['status' => 0, 'msg' => 'Success'];

                        $this->reader->addNotificationLogs($mid, $id);
                        if ($status_int == RemittanceReportReader::BATCH_STATUS_COMPLETED) {
                            $output = $this->reader->exportMerchantReport($id, $final_report = true);
                        } elseif ($status_int == RemittanceReportReader::BATCH_STATUS_DECLINED) {
                            //Replenish the total transaction amount and fee if batch Failed
                            //$this->wallet->revokeTransaction(\MerchantWallet::TYPE_BATCH_REMITTANCE, $id);
                        }
                    } else {
                        //$this->Flash->error(__('The remittance batch could not be saved. Please, try again.'));
                    }
                }
            }
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function updateLogStatus()
    {
        $this->log($this->request->data, 'debug');

        $data = ['status' => -1, 'msg' => 'Failed'];
        if ($this->request->is('ajax')) {
            $bid = $this->request->data['batch_id'];
            $id = $this->request->data['id']; //maybe 'all'
            //ok , failed, fail
            $status_int = (strtolower($this->request->data['status'])=='ok'?1:-1);
            if (isset($this->request->data['value']) && is_numeric($this->request->data['value']))   //status int
                $status_int = $this->request->data['value'];

            $this->log("status_int: $status_int", 'debug');

            $now = date('Y-m-d H:i:s');

            $table = TableRegistry::get('RemittanceLog');
            if ($id=='all') //all logs of a batch
                $query = $table->find()
                    ->where(['batch_id' => $bid]);
            else
                $query = $table->find()
                    ->where(['id' => $id, 'batch_id' => $bid]);
                //->first();
            $count = $query->count();
            if ($count>0) {
                $lists = $query->all();
                //$this->log($lists, 'debug');
                //[TODO] $wallet->setUser
                $batchs = $this->reader->getBatchDetails($bid);
                $mid = $batchs[0]['merchant_id'];
                $this->wallet = new MerchantWallet($mid);
                $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
                $wallet_sym = $this->wallet->getWalletCurrency();
                $this->log("wallet: $wallet_id, $wallet_sym", 'debug');
                //$dsc = 'Update TX status';

                foreach ($lists as $list) {
                    $last_status = $list->status;
                    $list->status = $status_int;
                    $list->update_time = $now;
                    $logid = $list->id;
                    //$this->log($list, 'debug');
                    if ($table->save($list)) {
                        $data = ['status' => 0, 'msg' => 'Success'];
                        //update balance only after batch completed
                        if ($last_status == $status_int)    //no change
                            continue;
                        $paid_amount = $this->reader->getBatchLogPaidAmountCny($bid, $logid, $wallet_sym);
                        $wallet_status = false;

                        switch ($last_status) {
                            case RemittanceReportReader::RM_STATUS_OK:
                            case RemittanceReportReader::RM_STATUS_OK_AMENDED:
                                //ok to failed, only for admin
                                if ($status_int==RemittanceReportReader::RM_STATUS_FAILED_AMENDED)
                                //if (in_array($status_int, [RemittanceReportReader::RM_STATUS_FAILED, RemittanceReportReader::RM_STATUS_FAILED_AMENDED]))
                                    //refund
                                    $wallet_status = $this->wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT, MerchantWallet::DSC_BATCH_TX_UPDATE, $bid);
                                break;
                            case RemittanceReportReader::RM_STATUS_FAILED:
                            case RemittanceReportReader::RM_STATUS_FAILED_AMENDED:
                                $paid_amount = "-$paid_amount";
                                //failed to ok, only for admin
                                if ($status_int==RemittanceReportReader::RM_STATUS_OK_AMENDED)
                                //if (in_array($status_int, [RemittanceReportReader::RM_STATUS_OK, RemittanceReportReader::RM_STATUS_OK_AMENDED]))
                                    //deduct
                                    $wallet_status = $this->wallet->addTransaction("$paid_amount", MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT, MerchantWallet::DSC_BATCH_TX_UPDATE, $bid);
                                break;
                        }
                        $this->log("update balance [$logid] w/ $paid_amount, done status = $wallet_status", 'debug');
                        //update balance END
                    }
                }   //end for
                //update batch count & total
                $this->reader->updateBatch($bid, $rate_update=FALSE, $quote_rate=0, $complete_rate=0, $updateBatchTotalOnly=true);
            }
        }
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function updateBatchTarget()
    {
        $this->log('updateBatchTarget:'.var_export($this->request->data, true), 'debug');

        $data = ['status' => -1, 'msg' => 'Failed'];
        if ($this->request->is('ajax')) {
            $bid = $this->request->data['batch_id'];
            $tid = $this->request->data['target'];

            if ($this->role == 'admin') {
                $batch = $this->RemittanceBatch->get($bid, ['contain' => [] ]);
                $targets = array_keys(RemittanceReportReader::$target_mappings);
                $this->log($targets, 'debug');

                if (in_array($tid, $targets)) {
                    $this->log("updateBatchTarget: batch=$bid, target=$tid", 'debug');
                    $batch->target = $tid;
                    $this->RemittanceBatch->save($batch);
                    $data = ['status' => 0, 'msg' => 'Success'];
                }
            }
        }
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function downloadExcel()
    {
        $this->log('downloadExcel: query, data', 'debug');
        $this->log($this->request->query, 'debug');

        if ($this->request->is('get')) {
            $id = $this->request->query['batch_id'];
            $target = $this->request->query['target'];

            $db_name = ConnectionManager::get('default')->config()['database'];
            $reader = new RemittanceReportReader($db_name);
            $output = $reader->exportExcel($id, $target);

            $this->log("exportExcel($id, $target): $output", 'debug');
            if (is_readable($output) && is_file($output))
            {
                $this->response->file($output,['download' => true, 'name' => basename($output)]);
                 // Return response object to prevent controller from trying to render
                // a view.
                return $this->response;

            }
            $this->log("No output: exportExcel($id, $target)",'error');
        }
        //$this->response->type('json');
        $this->response->body(NULL);
        return $this->response;
    }

    public function downloadReport()
    {
        //todo: check valid request
        $this->log('downloadReport: query, data', 'debug');
        $this->log($this->request->query, 'debug');

        if ($this->request->is('get')) {
            $id = $this->request->query['batch_id'];
            $status = $this->request->query['status'];
            $final_report = ($status=='completed');

            $db_name = ConnectionManager::get('default')->config()['database'];
            //$reader = new RemittanceReportReader($db_name);
            $output = $this->reader->exportMerchantReport($id, $final_report);

            $this->log("exportMerchantReport($id, $status): $output", 'debug');
            if (is_readable($output) && is_file($output))
            {
                $this->response->file($output,['download' => true, 'name' => basename($output)]);
                // Return response object to prevent controller from trying to render
                // a view.
                return $this->response;

            }
            $this->log("No output: exportMerchantReport($id)",'error');
        }
        //$this->response->type('json');
        $this->response->body(NULL);
        return $this->response;
    }
    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $remittanceBatch = $this->RemittanceBatch->newEntity();
        if ($this->request->is('post')) {
            $remittanceBatch = $this->RemittanceBatch->patchEntity($remittanceBatch, $this->request->data);
            if ($this->RemittanceBatch->save($remittanceBatch)) {
                $this->Flash->success(__('The remittance batch has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The remittance batch could not be saved. Please, try again.'));
            }
        }
        $merchants = $this->RemittanceBatch->Merchants->find('list', ['limit' => 200]);
        $this->set(compact('remittanceBatch', 'merchants'));
        $this->set('_serialize', ['remittanceBatch']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Remittance Batch id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $remittanceBatch = $this->RemittanceBatch->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $remittanceBatch = $this->RemittanceBatch->patchEntity($remittanceBatch, $this->request->data);
            if ($this->RemittanceBatch->save($remittanceBatch)) {
                $this->Flash->success(__('The remittance batch has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The remittance batch could not be saved. Please, try again.'));
            }
        }
        $merchants = $this->RemittanceBatch->Merchants->find('list', ['limit' => 200]);
        $this->set(compact('remittanceBatch', 'merchants'));
        $this->set('_serialize', ['remittanceBatch']);
    }

}
