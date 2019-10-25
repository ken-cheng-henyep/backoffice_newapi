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
 * BalanceApi Controller
 *
 * @property \App\Model\Table\BalanceApiTable $BalanceApi
 */
class BalanceApiController extends AppController
{
    /*
0	OK	Query successful
-1	Merchant ID invalid	Merchant ID missing, invalid or is disabled
-2	Incomplete parameter	Date range is missing start_date or end_date
-3	Wallet not found	Wallet ID does not exist
-4	Date range invalid	Invalid date range or date format
-100	<Token failure message>	Message varies depending on the type of token failure
     */
    private $no_param_responses = ["code"=> -2, "msg"=> "Incomplete parameter", "data"=> null];
    private $no_auth_responses = ["code"=> -1, "msg"=> "Merchant ID invalid", "data"=> null];
    //private $no_auth_responses = ["code"=> -2, "msg"=> "unauthorized", "data"=> null];
    private $merchantid;
    private $reader;
    private $wallet;
    // remittance_preauthorized in table
    private $pre_authorized = false;

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->log(__CLASS__.": beforeFilter", 'debug');

        // Allow all actions
        $this->Auth->allow();
        $this->request->allowMethod(['post']);
        $this->RequestHandler->ext = 'json';
        \Cake\I18n\Time::setJsonEncodeFormat('yyyy-MM-dd HH:mm:ss');
/*
        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->reader = new RemittanceReportReader($db_name);
        //$this->reader->username = 'API';
        $this->reader->ip = $this->request->clientIp();
*/
        if (! $this->checkAuth())
            return $this->outputJson($this->no_auth_responses);
    }

    /*
     * Return false if the merchant is not authorised
     */
    public function checkAuth() {
        //check valid Merchant ID
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
            ->where(['id' => $id, 'enabled >'=>0]);
        //for internal API requests
        /*
        $override_key='bypass_api_checkauth';
        if (! isset($this->request->data[$override_key]) || $this->request->data[$override_key]!='true')
            $query->where(['remittance_api_enabled >'=>0]);
        else
            $this->log("checkAuth bypass_api", 'info');
        //->where(['id' => $id, 'remittance_api_enabled >'=>0]);
*/
        if ($query->count()!=1)
            return FALSE;
        $data = $query->toArray();
        //$this->log($data[0], 'debug');

        $this->merchantid = $id;
        //$this->reader->setMerchant($id);
        $this->wallet = new MerchantWallet($id);
/*
        $wallet_id = $this->wallet->switchServiceWallet(MerchantWallet::SERVICE_REMITTANCE);
        $this->log("RM Wallet ID:$wallet_id", 'debug');
        //Check if no wallet set for remittance, in Table merchant_wallet_service
        if (! $wallet_id)
            return FALSE;
*/
        if ($data[0]->remittance_preauthorized > 0)
            $this->pre_authorized = true;
        $this->log("pre_authorized:".$this->pre_authorized, 'debug');

        return TRUE;
    }

    public function checkParameter($para=NULL, $post = true) {
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
    /**
     * Account Balances Query
     *
     * @return \Cake\Network\Response|null
     */
    public function listBalances()
    {
        $accounts = $this->wallet->getAccountsList();
        $balances = array();
        foreach ($accounts as $ac)
            $balances[] = [
                'name'=>$ac['name'], 'wallet_id'=>$ac['wallet_id'], 'currency'=>$ac['currency'], 'amount'=>$ac['balance_text']
            ];

        $data = ["code"=> 0, "msg"=> 'OK', "data"=> ['balances'=>$balances]];
        return $this->outputJson($data);
    }


    public function transaction()
    {
        if (! $this->checkParameter(['wallet_id','start_date','end_date']) )
            return $this->outputJson(["code"=> -2, "msg"=> "Incomplete parameter", "data"=> null]);

        $start = $this->request->data['start_date'];
        $start_tm = strtotime($start);
        $end = $this->request->data['end_date'];
        $end_tm = strtotime($end);
        $now = time();
        //check valid date format & range
        if (!checkValidDate($start) || !checkValidDate($end) || $start_tm>$end_tm || $start_tm>$now || $end_tm>$now) {
            return $this->outputJson(["code"=> -4, "msg"=> "Date range invalid", "data"=> null]);
        }

        $wallet_id = $this->request->data['wallet_id'];
        if (! $this->wallet->isWalletExist($wallet_id))
            return $this->outputJson(["code"=> -3, "msg"=> "Wallet not found", "data"=> null]);
        $wallets = $this->wallet->getWalletDetails($wallet_id);

        $this->loadModel('MerchantTransaction');
        $txs = $this->MerchantTransaction->getTransactions($this->merchantid, $wallet_id, $start, $end);
        //$this->log($txs[0],'debug');

        $txs = array_map(function ($r) {
            $tx['time'] = $r['create_time'];
            $tx['particulars'] = $r['type_name'];
            $tx['amount'] = number_format($r['amount'], 2, '.', '');
            $tx['balance'] = number_format($r['balance'], 2, '.', '');
            $tx['remarks'] = strip_tags($r['remarks']);
            //$r['time'] = $r['create_time']->i18nFormat('yyyy-MM-dd HH:mm:ss');
            //Log::write('debug', "{$r['create_time']}, {$r['time']}");
            return $tx;
        }, $txs);
        //$this->log($txs[1]['create_time'],'debug');

        $data = ["code"=> 0, "msg"=> 'OK', "data"=> [
            'name'=> $wallets['name'],
            'currency'=> $wallets['currency'],
            'transactions'=>$txs
        ]];
        return $this->outputJson($data);
    }

    /**
     * View method
     *
     * @param string|null $id Balance Api id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        //$data = $this->no_param_responses;
        $data = ["code"=> -3, "msg"=> 'Wallet not found', "data"=> null];

        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
        //return $this->outputJson($data);
    }

    /**
     * Edit method
     *
     * @param string|null $id Balance Api id.
     * @return \Cake\Network\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    /*
    public function edit($id = null)
    {
        $balanceApi = $this->BalanceApi->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $balanceApi = $this->BalanceApi->patchEntity($balanceApi, $this->request->data);
            if ($this->BalanceApi->save($balanceApi)) {
                $this->Flash->success(__('The balance api has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The balance api could not be saved. Please, try again.'));
        }
        $this->set(compact('balanceApi'));
        $this->set('_serialize', ['balanceApi']);
    }
    */

    /*
     * Serve JSON response
    */
    public function outputJson($data=null) {
        //$this->log(__CLASS__.": outputJson:".var_export($data, true), 'debug');
        if (!is_array($data)) {
            $data = $this->no_param_responses;
        }
        /*
        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
*/
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