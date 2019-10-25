<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

use RemittanceReportReader;
/**
 * Settlement API for merchant Controller
 *
 * @property \App\Model\Table\MerchantsTable $Merchants
 */
class SettlementApiController extends AppController
{
    private $no_param_responses = ["code"=> -2, "msg"=> "Incomplete parameter", "data"=> null];
    private $no_auth_responses = ["code"=> -1, "msg"=> "Merchant ID invalid", "data"=> null];
    private $merchantid;
    private $pc_api;
    private $reader;

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->log("beforeFilter", 'debug');
        // Allow all actions
        $this->Auth->allow();
        $this->request->allowMethod(['get','post']);
        $this->RequestHandler->ext = 'json';

        if (! $this->checkAuth())
            return $this->output_json($this->no_auth_responses);

        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->pc_api = new \PayConnectorAPI();
        $this->reader = new RemittanceReportReader($db_name);
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
        $this->log($this->request->query, 'debug');
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
        $query = $merchants->find()
            ->where(['id' => $id]);
        //->first();
        if ($query->count()!=1)
            return FALSE;

        $this->merchantid = $id;
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

    // POST: 2.1.1	/transaction/status
    public function transactionStatus(){
        $this->log("function transactionStatus", 'debug');

        if (! $this->checkParameter(['transaction_id']) && !$this->checkParameter(['start_date','end_date']) )
            return $this->output_json(["code"=> -2, "msg"=> "Incomplete parameter", "data"=> null]);

        $this->log($this->request->data,'debug');
        if (isset($this->request->data['start_date'])) {
            $start = $this->request->data['start_date'];
            $start_tm = strtotime($start);
            $end = $this->request->data['end_date'];
            $end_tm = strtotime($end);
            //check valid date format & range
            if (!checkValidDate($start) || !checkValidDate($end) || $start_tm > $end_tm) {
                return $this->output_json(["code" => -4, "msg" => "Date range invalid", "data" => null]);
            }
        } else {
            $start = $end = '';
        }

        $txs = $this->pc_api->getDatabaseTransactions($start, $end, $this->merchantid, $this->request->data['transaction_id']);
        $total = count($txs);
        $this->log("count:".$total,'debug');
        //no record
        if ($total==0)
            return $this->output_json(["code"=> -3, "msg"=> "Transaction not found", "data"=> null]);

        $datas = array();
        $fields = ['transaction_id','state','state_time','transaction_state','transaction_time', 'customer', 'email','amount','adjustment','merchant_ref','settlement_currency','conversion_rate','amount_converted','product','ip_address','bank_code'];
        $fields = array_fill_keys($fields, 1);
        $mappings = ['convert_currency'=>'settlement_currency', 'convert_rate'=>'conversion_rate','convert_amount'=>'amount_converted'];

        foreach ($txs as $row) {
            $row = array_change_key_case($row);
            foreach ($mappings as $k=>$mapping) {
                if (isset($row[$k]))
                    $row[$mapping] = $row[$k];
            }
            // FX package 2, rate of tx day not applied
            if ($row['fx_package']==2) {
                $row['conversion_rate'] = $row['amount_converted'] = '';
            }
            $row = array_intersect_key($row, $fields);
            /*
             * SELECT STATE_TIME, STATE, tx.TRANSACTION_TIME, TRANSACTION_STATE, concat(`FIRST_NAME`,' ',`LAST_NAME`) as customer,email,
		 m.name AS merchant, tx.merchant_id, tx.CURRENCY, tx.AMOUNT, ADJUSTMENT, MERCHANT_REF, tx.TRANSACTION_ID, CONVERT_CURRENCY, CONVERT_RATE, ROUND(CONVERT_AMOUNT, 2) as CONVERT_AMOUNT,
		 tx.product, tx.ip_address, g.BANK_NAME as bank, g.BANK_CODE
             */
            wcSetNumberFormat($row);

            $datas[]=$row;
        }

        $data = ["code"=>0,
            "msg"=>"OK",
            "data"=> $datas
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