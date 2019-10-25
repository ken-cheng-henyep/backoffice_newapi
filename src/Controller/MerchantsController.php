<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Merchants Controller
 *
 * @property \App\Model\Table\MerchantsTable $Merchants
 */
class MerchantsController extends AppController
{

	public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
		//$this->log("beforeFilter", 'debug');
		//$this->log($event, 'debug');
        // Allow users to register and logout.
        // You should not add the "login" action to allow list. Doing so would
        // cause problems with normal functioning of AuthComponent.
		// Allow all actions
        //$this->Auth->allow();	//(['download']);
    }
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        $merchants = $this->paginate($this->Merchants);

        $this->set(compact('merchants'));
        $this->set('_serialize', ['merchants']);
    }

    /**
     * View method
     *
     * @param string|null $id Merchant id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        if (is_null($id))
            $id = $this->request->data['id'];
        $this->log(__METHOD__." ($id)", 'debug');

        $merchant = $this->Merchants->get($id, [
                'contain' => []
            ]);

        $this->set('merchant', $merchant);
        $this->set('_serialize', ['merchant']);
    }

    public function viewJson() {
        //$id = $this->request->data['id'];
        $id = $this->request->query('id');
        $data = null;
        $this->log(__METHOD__." ($id)", 'debug');
        //sleep(5);

        if ($this->request->is(['ajax','get']))
        try {
            if (!empty($id)) {
                //$merchant = $this->Merchants->get($id);
                $query = $this->Merchants->find()
                    ->select(['id','name'])->where(['id'=>$id]);
                if ($query->count()>0)
                    $data = $query->first();//toArray();
            }
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

	/*
	 * Serve JSON file
	 */
	public function download($id = null)
	{
		// merchant_id
		//if (is_null($id))
		$id = $this->request->query('merchant_id');
		
		$date = $this->request->query('date');
		$currency = strtoupper($this->request->query('currency'));
		
		$basepath = Configure::read('WC.data_path');
		$code = 0; //OK
		
		$this->log("download id:$id", 'debug');
		//$this->log($this->request, 'debug');
		
		if (!empty($id)) {
			 $merchant = $this->Merchants->find()
				->where(['id' => $id, 'enabled'=>1])
				->first();
				
			$m_currency='';
			if (!empty($merchant)) {
				$m_currency = strtoupper($merchant->currencies);
			}
		}
		//valid check
		if (empty($id) || !isset($merchant) || empty($merchant))
			$code = 2;
		elseif (empty($date) || !$this->checkValidDate($date) || ($rtime = strtotime($date))==FALSE)
			$code = 3;
		elseif (empty($currency) || strpos("$m_currency,", "$currency,")===FALSE)
			$code = 4;
			
		if ($code==0) {
			// find record
			$uas = TableRegistry::get('UploadActivity');
			$query = $uas->find()
					->where(['status =' => 0, 'merchant_id'=> $id, 'currency'=>$currency, 'settle_time'=>date('Y-m-d 00:00:00',$rtime) ,'json_file IS NOT'=>null, ])
					->order(['upload_time' => 'DESC'])
					->first();
			$this->log($query, 'debug');
			//$json_file=$basepath.$query->json_file;
			
			if (empty($query) || empty($query->json_file) || !is_readable(($json_file=$basepath.$query->json_file)) )
				$code = 1;
			else
				$output = file_get_contents($json_file);
			//TODO: check json
			if (isset($json_file))
				$this->log("json_file:$json_file", 'debug');
		}
		//$this->log($merchant, 'debug');
		switch ($code) {
			case 1:	$msg='Report not available';
				break;
			case 2:	$msg='Invalid or missing merchant ID';
				break;
			case 3:	$msg='Invalid or missing date';
				break;
			case 4:	$msg='Invalid or missing currency';
				break;
			default:
					$msg='OK';
		}
		
		if ($code!=0) 
			$output = json_encode(array('code'=>$code, 'msg'=>$msg));
		//$this->log("output: $output", 'debug');
		//IE fix for not cache the request
		$this->response->disableCache();
		$this->response->modified('now');
		$this->response->checkNotModified($this->request);
		
		$mime = 'application/json';
		$output_name = sprintf("%s_%s_%s.json", $currency, $date, time());//basename($output);
		//$this->response->header(array("Content-Type: $mime",'Pragma: no-cache'));
		$this->response->body($output);
		$this->response->type($mime);
		// Optionally force file download
		//$this->response->download($output_name);
		// Return response object to prevent controller from trying to render a view.
		return $this->response;
	}

    public function listGroup() {
	    $json_url = Router::url(['action' => 'listGroupJson']);
        $dl_url = Router::url(['action' => 'listGroupJson', true ]);
        $update_url = Router::url(['action' => 'updateGroup']);
        $update_status_url = Router::url(['action' => 'updateGroupStatus']);

        $this->set(compact('json_url', 'update_url', 'update_status_url', 'dl_url'));
    }

    public function listGroupJson($download=false) {
        $this->log(__METHOD__, 'debug');
        $this->log($this->request->query, 'debug');

        $data = ['status'=>-1, 'msg'=>'Failed'];

        if ($this->request->is(['ajax','get'])) {
            /*
            $mgroup = TableRegistry::get('MerchantGroup');
            $query = $mgroup->find();
            $query->hydrate(false);
            //->contain(['Merchants']);
            $data = $query->order(['name' => 'ASC'])
                ->map(function ($row) { // map() is a collection method, it executes the query
                    $r['id'] = $row['id'];
                    $r['name'] = $row['name'];
                    $r['status'] = $row['status'];
                    $r['statusname'] = ($row['status']>0?'Enabled':'Disabled');
                    return $r;
                })
                ->toArray();
*/
            $data = $this->getMasterMerchants();
            /*
            $total = $query->count();
            $data = $query->toArray();
            */
            $total = count($data);
            //excel download
            if ($total>0 && $download) {
                //$path = $this->saveBatchJsonFile($res);
                // save xlsx file,
                //$xlsfile = sprintf('xls/MerchantBalance-%s.xlsx', time());
                $xlspath = sprintf('%s/xls/MerchantMasterList-%s', TMP, time());
                $xlsdata = array();
                $mappings = ['ID'=>'id', 'Master Merchant'=>'name', 'Status'=>'statusname'];
                foreach ($data as $d) {
                    $row = array();
                    foreach ($mappings as $key=>$val)
                        $row[$key] = (isset($d[$val])?$d[$val]:null);
                    $xlsdata[] = $row;
                }
                $xlsfile = fromArrayToExcelFile([date('Y-m-d His')=>$xlsdata], $xlspath);
                $xlsfile = str_replace(TMP, '',  $xlsfile);
                $this->log("path: $xlspath, file: $xlsfile", 'debug');

                return $this->serveFile($xlsfile);
            }
            $data = ['status'=>1, 'msg'=>'Success', 'total'=>$total, 'data'=>$data];
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function updateGroupStatus()
    {
        $this->log(__METHOD__, 'debug');
        $data = ['status'=>-1, 'msg'=>'Failed'];

        if ($this->request->is(['ajax','get'])) {
            $this->log($this->request->data, 'debug');

            $id = $this->request->data['id'];
            $status = $this->request->data['status'];
            $group = TableRegistry::get('MerchantGroup');
            $query = $group->find()
                ->where(['id' => $id]);
            $count = $query->count();
            //update 1 record only
            if ($count==1) {
                $list = $query->first();
                $list->status = $status;
                $list->update_time = date('Y-m-d H:i:s');
                $this->log($list, 'debug');
                if ($group->save($list))
                    $data = ['status' => 1, 'msg' => 'Success'];
            }
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function updateGroup()
    {
        $this->log(__METHOD__, 'debug');
        $data = ['status' => -1, 'msg' => 'Failed'];

        if ($this->request->is(['ajax','get'])) {
            $this->log($this->request->data, 'debug');

            $id = strtolower($this->request->data['id']);
            $group = TableRegistry::get('MerchantGroup');
            $query = $group->find()
                ->where(['id' => $id]);
            $count = $query->count();
            //$count = 0;
            //new record has empty statusname
            $update = (isset($this->request->data['statusname']) && !empty($this->request->data['statusname']));
            //update name of 1 record only
            if (isset($this->request->data['name'])) {
                $name = trim($this->request->data['name']);
                if ($count == 1 && $update) {
                    $list = $query->first();
                    $list->name = $name;
                    $list->update_time = date('Y-m-d H:i:s');
                    $this->log($list, 'debug');
                    if ($group->save($list))
                        $data = ['status' => 1, 'msg' => 'Success'];
                } elseif ($count == 1) {
                    //new record with same id
                    $data = ['status' => -1, 'msg' => 'Merchant ID has already been used.'];
                } elseif ($count == 0 && empty($name)) {
                    $data = ['status' => -1, 'msg' => 'Please enter Merchant Name.'];
                } elseif ($count == 0 && ! $update) {
                    //new record
                    $this->log("new MerchantGroup", 'debug');
                    $group = TableRegistry::get('MerchantGroup');
                    $item = $group->newEntity();
                    $item->id = $id;
                    $item->name = $name;
                    $item->status = 1; //enabled
                    if ($group->save($item)) {
                        $this->log($item, 'debug');
                        $data = ['status' => 1, 'msg' => 'Success'];
                    }
                }
            }
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    /*
     * Search merchants
     */
    public function search() {
        $master_lst = $this->getMasterMerchants(true);
        $json_url = Router::url(['action' => 'searchJson']);

        $this->set(compact('json_url', 'master_lst'));
    }

    public function searchJson() {
        $this->log(__METHOD__, 'debug');
        $data = ['status'=>-1, 'msg'=>'Failed'];
        $total = 0;

        if ($this->request->is(['ajax','get'])) {
            $query = $this->Merchants->find()
                ->join([
                'g' => [
                    'table' => 'merchants_group_id',
                    //'alias' => 'm',
                    'type' => 'LEFT',
                    'conditions' => 'Merchants.id = g.merchant_id',
                ],
                'm' => [
                    'table' => 'merchants_group',
                    //'alias' => 'm',
                    'type' => 'LEFT',
                    'conditions' => 'm.id = g.id',
                ],
            ]);

            $query->select($this->Merchants)
                ->select(['group_id' => 'm.id', 'group_name' => 'm.name']);

            if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
                foreach ($this->request->query['filter']['filters'] as $filter) {
                    $val = (isset($filter['value'])?trim($filter['value']):'');
                    if (!empty($filter['field']) && $val!='')
                        switch ($filter['field']) {
                            case 'master':
                                $query->where(['m.id' => $val]);
                                break;
                            case 'id':
                                //$query->where(['RemittanceBatch.merchant_id' => $val]);
                                $query->where(['Merchants.id' => $val]);
                                break;
                            case 'enabled':
                                if ($val)
                                    $query->where(['Merchants.enabled >' => 0]);
                                else
                                    $query->where(['OR' => [["Merchants.enabled <" => 1], ["Merchants.enabled IS NULL"]]]);
                                break;
                            case 'name':
                                //$query->where(['beneficiary_name' => $val]);
                                $val = strtolower($val);
                                $query->where(['LOWER(Merchants.name) LIKE' => "%$val%"]);
                                /*
                                // ignore other criteria if id exists
                                $query->orWhere(['RemittanceBatch.id' => $val])
                                    ->andWhere(['merchant_id' => $this->merchant_id]) ;
                                */
                                break;
                            //0 or 1
                            case 'local_remittance_enabled':
                            case 'remittance_preauthorized':
                            case 'remittance_api_enabled':
                                if ($val)
                                    $query->where(["{$filter['field']} >" => 0]);
                                else
                                    //$query->where(['coalesce('.$filter['field'].', 0) <' => 1]);
                                     $query->where(['OR' => [["{$filter['field']} <" => 1], ["{$filter['field']} IS NULL"]]]);
                                break;
                            default:
                                // case 'account':
                                $query->where([$filter['field'] => $val]);
                        }   //switch
                }
            }

            $query->order(['group_name' => 'asc', 'Merchants.name' => 'asc']);

            $this->log($query, 'debug');
            $total = $query->count();
            if (isset($this->request->query['page']) && isset($this->request->query['pageSize']))
                $query->limit($this->request->query['pageSize'])
                    ->page($this->request->query['page']);

            $res = $query
                ->hydrate(false)
                ->map(function ($row) { // map() is a collection method, it executes the query
                    /*
                    $row->enabled = ($row->enabled>0?'Y':'N');
                    $row->local_remittance_enabled = ($row->local_remittance_enabled>0?'Y':'N');
                    $row->remittance_preauthorized = ($row->remittance_preauthorized>0?'Y':'N');
                    $row->remittance_api_enabled = ($row->remittance_api_enabled>0?'Y':'N');
                    $row->skip_balance_check = ($row->skip_balance_check>0?'Y':'N');
                    $row->remittance_netting = ($row->remittance_netting>0?'Y':'N');

                    $row->api_username = $row->api_password = null;
                    $row->action_url = Router::url(['action' => 'edit', $row->id]);
                    */
                    $row['enabled'] = ($row['enabled']>0?'Y':'N');
                    $row['local_remittance_enabled'] = ($row['local_remittance_enabled']>0?'Y':'N');
                    $row['remittance_preauthorized'] = ($row['remittance_preauthorized']>0?'Y':'N');
                    $row['remittance_api_enabled'] = ($row['remittance_api_enabled']>0?'Y':'N');
                    $row['skip_balance_check'] = ($row['skip_balance_check']>0?'Y':'N');
                    $row['remittance_netting'] = ($row['remittance_netting']>0?'Y':'N');

                    $row['api_username'] = $row['api_password'] = null;
                    $row['action_url'] = Router::url(['action' => 'edit', $row['id']]);
                    return $row;
                })
                //->hydrate(false)
                ->toArray();


            $data = ['status'=>1, 'msg'=>'Success', 'total'=>$total, 'data'=>$res];
            //excel download
            if ($total>0 && isset($this->request->query['type']) && $this->request->query['type']=='excel') {
                //$path = $this->saveInstantJsonFile($res);
                $basepath = Configure::read('WC.data_path');
                $maps = ['group_name'=>'Master Merchant',
                    'name'=>'Name',
                    'id'=>'Merchant ID',
                    'enabled'=>'Enabled',
                    'settle_fee'=>'MDR Fee in %',
                    'settle_min_fee_cny'=>'MDR Min Fee',
                    'refund_fee_cny'=>'Refund Fee',
                    'settle_option'=>'FX Package',
                    'settle_rate_symbol'=>'FX Rate Symbol',
                    'round_precision'=>'Rounding Precision',
                    'processor_settle_currency'=>'Processor Settlement Currency',
                    'fx_source'=>'FX Source',
                    'settle_currency'=>'Settlement Currency',
                    'settle_handling_fee'=>'Settlement Handling Fee',
                    'remittance_fee'=>'Cross Border Fee %',
                    'remittance_min_fee'=>'Cross Border Min Fee',
                    'remittance_fee_type'=>'Fee Bearer',
                    'local_remittance_enabled'=>'Local Remittance Enabled',
                    'local_remittance_fee'=>'Local Remittance Fee (CNY)',
                    'remittance_preauthorized'=>'Pre-authorized Enabled',
                    'remittance_api_enabled'=>'Remittance API Enabled',
                    'skip_balance_check'=>'Skip Balance Check',
                    'remittance_netting'=>'Remittance Netting'
                    ];

                $filename = sprintf("%s%s%s_%s", $basepath, '/xls/', 'merchant', time());
                $this->log("file: $filename", 'debug');
                //$this->log($res, 'debug');
                foreach ($res as $rk=>$r) {
                    //$res[$rk] = array_diff_key( $r, array_flip(['api_password','action_url']));
                    $row = array();
                    foreach ($maps as $mkey=>$mcol) {
                        if (isset($r[$mkey]))
                            $row[$mcol] = $r[$mkey];
                        else
                            $row[$mcol] = null;
                    }
                    $excel_data[] = $row;
                }
                //$this->log($excel_data, 'debug');

                $path = fromArrayToExcelFile(['Merchant'=>$excel_data], $filename);
                $path = str_replace($basepath, '', $path);
                $xlsurl = Router::url(['controller'=>'RemittanceBatch','action' => 'serveStaticFile', $path]);
                $data = ['status'=>1, 'msg'=>'Success', 'path'=>$xlsurl, 'total'=>$total];
            }
        }
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }
    //serve file in tmp folder
    public function serveFile($f) {
        //$path = Configure::read('WC.data_path').$f;
        $path = TMP.$f;
        $this->log("serveFile($path)",'debug');

        if (!is_readable($path))
            $this->response->body(NULL);
        else
            $this->response->file($path,['download' => true, 'name' => basename($f)]);

        return $this->response;
    }

    function checkValidDate($d) {
        if (! preg_match ( '/^(\d{4})(\d{2})(\d{2})$/' , $d, $matches) || count($matches)!=4)
            return FALSE;
        //var_dump($matches);
        return checkdate ($matches[2] ,$matches[3] ,$matches[1]);
    }

    public function getMasterMerchants($list=false) {
        $mgroup = TableRegistry::get('MerchantGroup');
        if ($list) {
            $query = $mgroup->find('list', [
                'keyField' => 'id',
                'valueField' => 'name'
            ])
                ->order(['name' => 'ASC']);
            return $query->toArray();
        }

        $query = $mgroup->find();
        $query->hydrate(false);

        //->contain(['Merchants']);
        $data = $query->order(['name' => 'ASC'])
            ->map(function ($row) { // map() is a collection method, it executes the query
                $r['id'] = $row['id'];
                $r['name'] = $row['name'];
                $r['status'] = $row['status'];
                $r['statusname'] = ($row['status']>0?'Enabled':'Disabled');
                return $r;
            })
            ->toArray();
        return $data;
    }
    /**
     * Add Merchant Account
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->log(__METHOD__, 'debug');
        //master merchant
        $master_lst = $this->getMasterMerchants(true);
        $validate_url = Router::url(['action' => 'viewJson']);
        $id = null;

        $merchant = $this->Merchants->newEntity();
        $group = TableRegistry::get('MerchantsGroupId')->newEntity();

        $this->set(compact('merchant', 'master_lst', 'validate_url'));

        if ($this->request->is('post')) {
            //$this->log($this->request->data, 'debug');
            if (isset($this->request->data['mid']))
                $id = $this->request->data['mid'];

            $master = $this->request->data['master_merchant'];
            $exist = null;
            try {
                $exist = $this->Merchants->get($id);
            }   catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            }

            if (! is_null($exist)) {
                $this->Flash->error(__('Merchant ID has already been used.'));
                return null;
            }

            if (! empty($this->request->data['recipient_email'])) {
                $this->request->data['report_recipient_email'] = $this->trimEmailList($this->request->data['recipient_email']);
                /*
                $email_lst = trim($this->request->data['recipient_email'], ' ;,');
                $email_lst = str_replace(',',';', $email_lst);
                $emails = explode(';', $email_lst);
                if (is_array($emails)) {
                    $emails = array_map('trim', $emails);
                    $emails = array_filter($emails, function($k) {
                        return !empty($k);
                    });
                    $this->request->data['report_recipient_email'] = implode(';', $emails);
                    //TODO: validate email
                }
                */
            }
            //required
            $this->request->data['id'] = $id;
            if (! isset($this->request->data['currencies']) || empty($this->request->data['currencies']))
                $this->request->data['currencies'] = 'USD';
            if (! isset($this->request->data['settle_currency']) || empty($this->request->data['settle_currency']))
                $this->request->data['settle_currency'] = 'USD';
            if (! isset($this->request->data['createdate']))
                $this->request->data['createdate'] = date('Y-m-d H:i:s');
            //$this->Flash->error(__('The merchant could not be saved. Please, try again.'));

            $this->log($this->request->data, 'debug');
            $merchant->accessible('id', true);
            $merchant = $this->Merchants->patchEntity($merchant, $this->request->data);

            $group->accessible('merchant_id', true);
            $group = TableRegistry::get('MerchantsGroupId')->patchEntity($group, ['id'=>$master, 'merchant_id'=>$id, 'master'=>($master==$id?1:0)]) ;

            if ($this->Merchants->save($merchant)) {
                TableRegistry::get('MerchantsGroupId')->save($group);
                $this->Flash->success(__('The merchant has been saved.'));
                //return $this->redirect(['action' => 'index']);
            } else {
                $this->log($merchant->errors(), 'debug');
                $this->Flash->error(__('The merchant could not be saved. Please, try again.'));
            }

        }

        //$this->set('_serialize', ['merchant']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Merchant id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->log(__METHOD__ . " ($id)", 'debug');
        //$this->log($this->Merchants->get($id), 'debug');
        $query = $this->Merchants->find()
            ->where(['Merchants.id' => $id]);

        $query->join([
            'g' => [
                'table' => 'merchants_group_id',
                //'alias' => 'm',
                'type' => 'LEFT',
                'conditions' => 'Merchants.id = g.merchant_id',
            ],
            'm' => [
                'table' => 'merchants_group',
                //'alias' => 'm',
                'type' => 'LEFT',
                'conditions' => 'm.id = g.id',
            ],
        ]);

        $query->select($this->Merchants)
            ->select(['group_id' => 'm.id', 'group_name' => 'm.name']);

        //$this->log($query, 'debug');

        $entity = $query->first();
        $merchant = $query->hydrate(false)->toArray();
        if (is_array($merchant) && isset($merchant[0]))
            $merchant = $merchant[0];

        $this->log($merchant, 'debug');
        $this->log($entity, 'debug');

        if ($this->request->is(['patch', 'post', 'put'])) {
            //fields not to be updated
            $readonlys = ['id', 'processor_account_type', 'settle_option', 'processor_settle_currency', 'fx_source', 'settle_currency'];
            foreach ($readonlys as $ro) {
                if (isset($this->request->data[$ro]))
                    unset($this->request->data[$ro]);
            }
            //email list
            if (isset($this->request->data['recipient_email']))
                $this->request->data['report_recipient_email'] = $this->trimEmailList($this->request->data['recipient_email']);
            $this->log("POST:" . var_export($this->request->data, true), 'debug');

            $entity = $this->Merchants->patchEntity($entity, $this->request->data);

            if ($this->Merchants->save($entity)) {
                $this->Flash->success(__('The merchant has been saved.'));
                $merchant = array_merge($merchant, $this->request->data);
                //return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The merchant could not be saved. Please, try again.'));
            }
        }

        $this->set(compact('merchant'));
        $this->set('_serialize', ['merchant']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Merchant id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $merchant = $this->Merchants->get($id);
        if ($this->Merchants->delete($merchant)) {
            $this->Flash->success(__('The merchant has been deleted.'));
        } else {
            $this->Flash->error(__('The merchant could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }

    function trimEmailList($email_lst) {
        $email_lst = trim($email_lst, ' ;,');
        $email_lst = str_replace(',',';', $email_lst);
        $emails = explode(';', $email_lst);
        if (is_array($emails)) {
            $emails = array_map('trim', $emails);
            $emails = array_filter($emails, function($k) {
                return !empty($k);
            });
            $email_lst = implode(';', $emails);
            //TODO: validate email
        }
        return $email_lst;
    }
}
