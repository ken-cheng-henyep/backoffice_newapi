<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Common\Type;

use MerchantWallet;
use RemittanceReportReader;

/**
 * MerchantTransaction Controller
 *
 * @property \App\Model\Table\MerchantTransactionTable $MerchantTransaction
 */
class MerchantTransactionController extends AppController
{
    public $helpers = ['Html'];
    var $wallet, $reader, $username, $role;

    public function initialize() {
        parent::initialize();

        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->wallet = new MerchantWallet();
        $this->reader = new RemittanceReportReader($db_name);

        $usrs = $this->request->session()->read('Auth.User');
        $this->username = $usrs['username'];
        if (isset($usrs['role']))
            $this->role = $usrs['role'];
    }

    /**
     * List Merchant Balance
     *
     * @return \Cake\Network\Response|null
     */
    public function index($download=false)
    {
        if ($download) {
            $res = $this->getAccountsList();

            // save xlsx file,
            $xlsfile = sprintf('xls/MerchantBalance-%s.xlsx', time());
            $xlspath = sprintf('%s/%s', TMP, $xlsfile);

            if (is_array($res)) {
                $this->log("index download:".$xlspath, 'debug');
                //$this->log($res, 'debug');

                $xlsfile = fromArrayToExcelFile([date('Y-m-d His')=>$res], $xlspath);
                $xlsfile = str_replace(TMP, '',  $xlsfile);
                /*
                $writer = WriterFactory::create(Type::XLSX);
                //$styleRedFloat = (new StyleBuilder())->setNumberFormat('0.00')->build();
                //$writer->registerStyle($styleRedFloat); # If using it for a single cell

                $writer->openToFile($xlspath)
                    //->setDefaultRowStyle($styleRedFloat)
                    //->setTempFolder($customTempFolderPath)
                    //->setShouldUseInlineStrings(true)
                    //->addRow($headerRow)
                    ->addRow(array_keys($res[0]))
                    ->addRows($res);
                $sheet = $writer->getCurrentSheet();
                $sheet->setName(date('Y-m-d His'));
                $writer->close();
                //serveStaticFile
                //$this->serveTmpFile($xlsfile);
                */
                $this->serveFile($xlsfile);
            }

        }

        $dl_url = Router::url(['action' => 'index', true ]);
        $this->set(compact('dl_url'));
        /*
        $merchantTransaction = $this->paginate($this->MerchantTransaction);

        $this->set(compact('merchantTransaction'));
        $this->set('_serialize', ['merchantTransaction']);
        */

    }

    /*
     * List Merchant Balance json
     */
    public function json() {
        $this->log("json, role:".$this->role, 'debug');

        if (!$this->request->is('ajax')) {
            return false;
        }

        $res = $this->getAccountsList();
        foreach ($res as $k=>$r) {
            //wcSetNumberFormat($res[$k]);
            $res[$k]['action_url'] = Router::url(['action' => 'view', $r['merchant_id'], $r['wallet_id'] ]);
        }
        //$this->log($res,'debug');

        $this->response->type('json');
        $data = ['data'=>$res, 'total'=>count($res)];
        $this->response->body(json_encode($data));

        return $this->response;
    }

    public function getAccountsList($id = null) {
        $this->log('getAccountsList()','debug');
        //$id='5d253a18-0f92-11e7-9480-0211eb00a4cc';

        $ac = TableRegistry::get('MerchantTransactionAccount');
        $query = $ac->find('all');

        /*
        $query->select([
            'merchant_id',
            'account_status' => 'MAX(MerchantTransactionAccount.status)',
            'merchant_name' => 'MAX(Merchants.name)',
            'balance' => 'SUM(balance)',
            //'balance' => $query->func()->sum('balance'),
//            'status',
        ])
            ->contain(['Merchants'])
            ->group('merchant_id');
*/
        $query->select([
            'merchant_id',
            'account_status' => 'MerchantTransactionAccount.status',
            'merchant_name' => 'Merchants.name',
            'wallet_name' => 'MerchantTransactionAccount.name',
            'wallet_id',
            'currency',
            'balance',
            //'service'=>'MerchantWalletService.type',
            //'balance' => 'SUM(balance)',
            //'balance' => $query->func()->sum('balance'),
        ])
            ->contain(['Merchants',
                //'MerchantWalletService'
                ])
            //->hasMany(['MerchantWalletService'])
            ->order(['merchant_name','wallet_id']);

        if (!empty($id))
            $query->where(['merchant_id' => $id]);

        $this->log($query,'debug');
        //$this->log("count:".count($query),'debug');
        $query->hydrate(false); // Results as arrays
        //$res = $query->toList(); // Execute the query and return the array
        $res = $query->toArray();
        foreach ($res as $k=>$r) {
            $wallet = new MerchantWallet($r['merchant_id'], $r['wallet_id']);
            $services = $wallet->getWalletService();
            if (count($services))
                $res[$k]['service_name'] = implode(',', $services);
        }

        //$this->log($res,'debug');
        return $res;
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
    /**
     * View method
     *
     * @param string|null $id Merchant Transaction id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null, $wallet_id = null)
    {
        $this->log("view($id, $wallet_id), role:".$this->role,'debug');

        $account_lst = array();
        $accounts = $this->getAccountsList();
        if (is_array($accounts))
            foreach ($accounts as $ac)
                $account_lst[$ac['merchant_id']] = $ac['merchant_name'];
        $json_url = Router::url(['action' => 'viewJson']);
        $update_url = Router::url(['action' => 'updateJson']);
        $wallet_url = Router::url(['action' => 'walletJson']);

        $this->set(compact('id','account_lst', 'json_url', 'update_url', 'wallet_url', 'wallet_id'));

        // Manager Approve
        //$this->set('showUpdateBox', ($this->role=='manager'));
        $this->set('showUpdateBox', true);

        /*
        $merchantTransaction = $this->MerchantTransaction->get($id, [
            'contain' => []
        ]);

        $this->set('merchantTransaction', $merchantTransaction);
        $this->set('_serialize', ['merchantTransaction']);
        */
    }

    public function viewJson($id = null, $wallet_id = null) {
        $this->log("viewJson($id, $wallet_id)",'debug');

        if (! $this->request->is(['ajax','get'])) {
            return false;
        }

        $wallet_id = \MerchantWallet::DEFAULT_WALLET_ID;
        $startdate = $enddate = date('Y/m/d 00:00:00');
/*
        $query = $this->MerchantTransaction->find()
            ->contain(['Merchants'])
            ->where(['merchant_id' => $id]);
        // $query->select(['id', 'title', 'body']);
        $query->order(['create_time' => 'asc']);
*/
        if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
            /*
            $query = $this->MerchantTransaction->find()
                ->contain(['Merchants'])
                ->select(['merchant_id', 'type', 'amount', 'balance', 'create_time', 'username', 'remarks', 'ref_id', 'merchant_name'=>'Merchants.name',])
                //->select([$this->MerchantTransaction, $this->Merchants.name])
            ->order(['create_time' => 'asc']);
*/
            foreach ($this->request->query['filter']['filters'] as $filter) {
                $val = trim($filter['value']);
                if ($val!='')
                    switch ($filter['field']) {
                        case 'id':
                            $id = $val;
                            //$query->where(['merchant_id' => $val]);
                            break;
                        case 'wallet_id':
                            //$query->where(['wallet_id' => $val]);
                            $wallet_id = $val;
                            $wallet_set = true;
                            break;
                        case 'start':
                            $startdate = date('Y/m/d 00:00:00', strtotime($val));
                            //$query->where(['create_time >='=> $startdate]);
                            break;
                        case 'end':
                            $enddate = date('Y/m/d 00:00:00',strtotime($val));
                            //$query->where(['create_time <'=> $enddate]);
                            break;

                    }   //switch
            }
        }

        $res = $this->MerchantTransaction->getTransactions($id, $wallet_id, $startdate, $enddate);
        $total = count($res);
        //$this->log("getTransactions:".var_export($res, true),'debug');
        $this->log("getTransactions: $total",'debug');
        //$currency = 'CNY';
/*
        $query->formatResults(function (\Cake\Collection\CollectionInterface $results) {
            return $results->map(function ($r) {
                if (!isset($r['type_name']))
                    $r['type_name'] = \MerchantWallet::getTypeName($r['type']);
                if (isset($r['ref_id'])) {
                    $url = Router::url(['controller'=>'RemittanceBatch','action' => 'view', $r['ref_id']]) ;
                    $irurl = Router::url(['controller'=>'RemittanceBatch','action' => 'searchInstant', $r['ref_id']]) ;
                    switch ($r['type']) {
                        case MerchantWallet::TYPE_BATCH_REMITTANCE:
                            $r['remarks'] = sprintf("Batch <a href='%s'>%s</a> approved", $url, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_BATCH_REMITTANCE_ADJUSTMENT:
                        case MerchantWallet::TYPE_BATCH_REMITTANCELOG_ADJUSTMENT:
                            $r['remarks'] = sprintf("Batch <a href='%s'>%s</a> transaction status updated", $url, $r['ref_id']);
                            //$r['remarks_url'] = Router::url(['controller'=>'RemittanceBatch','action' => 'view', $r['ref_id']]) ;
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a>", $irurl, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE_FAILED_ADJUSTMENT:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a> failed", $irurl, $r['ref_id']);
                            break;
                        case MerchantWallet::TYPE_INSTANT_REMITTANCE_ADMIN_ADJUSTMENT:
                            $r['remarks'] = sprintf("Transaction <a href='%s'>%s</a> status updated", $irurl, $r['ref_id']);
                            break;
                    }
                }
                //$r['currency'] = $currency;
                return $r;
            });
        });

        $total = $query->count();
        $query->hydrate(false);
        $res = $query->toArray();
*/
        $this->wallet->setMerchant($id);
        $this->wallet->setWallet($wallet_id);
        $currency = $this->wallet->getWalletCurrency($wallet_id);
        $this->log("wallet ($wallet_id): $currency",'debug');
/*
        // Add Opening Balance
        if (isset($res[0]) && is_array($res[0])) {
            //$this->log($res[0],'debug');
            $opens = $res[0];
            $opens['create_time'] = date('Y-m-d 0:00:00', strtotime($res[0]['create_time']));
            $opens['amount'] = 0;
            $opens['balance'] = $res[0]['balance'] - $res[0]['amount'];
            $opens['type'] = \MerchantWallet::TYPE_OPEN_BALANCE;
            $opens['type_name'] = \MerchantWallet::getTypeName(\MerchantWallet::TYPE_OPEN_BALANCE);
            //$opens = compact('create_time', 'balance', 'type_name');
            $opens['username'] = $opens['remarks'] = '';
            //$this->log($opens,'debug');
            $res = array_merge([$opens], $res);
            $total++;
        } elseif ($total==0) {
            // Get previous balance
            $txs = $this->wallet->getPreviousBalances($startdate, $id);
            //$this->log("getPreviousBalance=$balance",'debug');
            $this->log($txs, 'debug');

            if (is_array($txs)) {
                $res[] = [
                    'create_time' => date('Y-m-d 0:00:00', strtotime($startdate)),
                    'amount' => 0,
                    'balance' => floatval($txs['balance']),
                    'merchant_id' => $id,
                    'merchant_name' => $txs['merchant_name'],
                    'type' => \MerchantWallet::TYPE_OPEN_BALANCE,
                    'type_name' => \MerchantWallet::getTypeName(\MerchantWallet::TYPE_OPEN_BALANCE),
                    'username' => '',
                    'remarks' => '',
                ];
            } else {
                $merchant = $this->reader->getMerchantDetails($id);
                //No transaction at all
                $res[] = [
                    'create_time' => date('Y-m-d 0:00:00'),
                    'amount' => 0,
                    'balance' => 0,
                    'merchant_id' => $id,
                    'merchant_name' => $merchant['name'],
                    'type' => \MerchantWallet::TYPE_OPEN_BALANCE,
                    'type_name' => \MerchantWallet::getTypeName(\MerchantWallet::TYPE_OPEN_BALANCE),
                    'username' => '',
                    'remarks' => '',
                ];
            }
            $total++;
        }
*/
        $data = ['data'=>$res, 'total'=>$total];

        //excel download
        if ($total>0 && isset($this->request->query['type']) && $this->request->query['type']=='excel') {
            $merchant_name = $res[0]['merchant_name'];
            $xlsfile = sprintf('xls/MerchantBalance-%s-%s', $id, time());
            $xlspath = sprintf('%s/%s', TMP, $xlsfile);

            $maps = [
                'Merchant'=>'merchant_name',
                'Merchant ID'=>'merchant_id',
                'Date'=>'create_time',
                'Particulars'=>'type_name',
                'Currency'=>'currency',
                'Amount'=>'amount',
                'Balance'=>'balance',
                'Operator'=>'username',
                'Remarks'=>'remarks',
            ];
            $xlsdata = array();
            foreach ($res as $k=>$r) {
                foreach($maps as $title=>$idx) {
                    $xlsdata[$k][$title] = (isset($r[$idx])?$r[$idx]:'');
                }
                //remove html link
                if (!empty($xlsdata[$k]['Remarks']))
                    $xlsdata[$k]['Remarks'] = strip_tags($xlsdata[$k]['Remarks']);
                $xlsdata[$k]['Currency'] = $currency;
            }

            $xlspath = fromArrayToExcelFile(['Merchant Balance '.date('Y-m-d')=>$xlsdata], $xlspath);
            $xlsfile = str_replace(TMP, '', $xlspath);
            $this->log("xlsx: $xlsfile",'debug');

            $xlsurl = Router::url(['action' => 'serveFile', $xlsfile]);
            $data = ['status'=>1, 'msg'=>'Success', 'path'=>$xlsurl, 'total'=>$total];
        }

        $this->response->type('json');
        //$data = ['data'=>$res, 'total'=>$total];
        $this->response->body(json_encode($data));
        return $this->response;
    }

    /*
     * 5.7	Merchant Wallet setup page
     */
    public function wallet()
    {
        $master_lst = (new MerchantsController())->getMasterMerchants($list=true);
        $json_url = Router::url(['action' => 'walletJson']);
        //
        $this->set(compact('master_lst', 'json_url'));
    }
    /*
     * List wallet of a merchant
     */
    public function walletJson($id=null) {
        $json=[];
        if (isset($this->request->query['id']))
            $id = $this->request->query['id'];

        $this->response->type('json');
        $this->log("walletJson($id)",'debug');

        if (!empty($id)) {
            $data = $this->getAccountsList($id);
            foreach ($data as $k=>$v) {
                $json[] = ['text'=>$v['wallet_name'], 'value'=>$v['wallet_id'], 'currency'=>$v['currency']];
            }
        }

        $this->response->body(json_encode($json));
        return $this->response;
    }

    public function setup($id = null)
    {
        $this->log("setup($id), role:".$this->role,'debug');

        if ($this->request->is(['post'])) {
            $this->log($this->request->data,'debug');

            $p = $this->request->data['processor'];
            $this->reader->setPreferredApiProcessor($p);

            $this->Flash->success('Preferred Processor has been updated.');
        }

        $ps = TableRegistry::get('Processors');
        $query = $ps->find('list', [
            'keyField' => 'id',
            'valueField' => 'name'
        ])
        ->where(['type like'=>'local', 'priority >'=>0])
        ->order(['priority'=>'ASC']);
        $processors_lst = $query->toArray();
        if (is_array($processors_lst)) {
            //$first = array_shift($processors_lst);
            foreach ($processors_lst as $id=>$v) {
                $first = ['id'=>$id, 'name'=>"$v"];
                unset($processors_lst[$id]);
                break;
            }
        }

        $this->set(compact('first','processors_lst'));
    }
    /**
     * Update Balance
     */
    public function updateJson()
    {
        $mid = $this->request->data['mid'];
        $wid = $this->request->data['wallet_id'];
        $amount = $this->request->data['amt'];
        $remarks = $this->request->data['remarks'];

        if (empty($wid))
            $wid = 1;   //default wallet_id

        $this->log("updateJson ($mid, $wid, $amount, $remarks), role:" . $this->role, 'debug');

        if (!$this->request->is('ajax') || empty($mid) || $amount=='') {
            return false;
        }
        if ($this->role!='manager') {
            //return false; //dev only
            $this->log("updateJson ($mid, $amount), BYPASS role check", 'debug');
        }

        $amount = round($amount,2);
        $data = ['status' => -1, 'msg' => 'Failed'];

        $this->wallet->setMerchant($mid);
        $this->wallet->setWallet($wid);
        $this->wallet->setUser($this->username);
        $done = $this->wallet->addTransaction($amount, MerchantWallet::TYPE_ADMIN_UPDATE, $remarks);
        if ($done)
            $data = ['status' => 1, 'msg' => 'Success', 'amount'=>$amount];

        $this->response->body(json_encode($data));
        return $this->response;
    }

    /**
     * Edit method
     *
     * @param string|null $id Merchant Transaction id.
     * @return \Cake\Network\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $merchantTransaction = $this->MerchantTransaction->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $merchantTransaction = $this->MerchantTransaction->patchEntity($merchantTransaction, $this->request->data);
            if ($this->MerchantTransaction->save($merchantTransaction)) {
                $this->Flash->success(__('The merchant transaction has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The merchant transaction could not be saved. Please, try again.'));
        }
        $this->set(compact('merchantTransaction'));
        $this->set('_serialize', ['merchantTransaction']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Merchant Transaction id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $merchantTransaction = $this->MerchantTransaction->get($id);
        if ($this->MerchantTransaction->delete($merchantTransaction)) {
            $this->Flash->success(__('The merchant transaction has been deleted.'));
        } else {
            $this->Flash->error(__('The merchant transaction could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
