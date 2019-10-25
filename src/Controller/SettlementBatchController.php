<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Cake\Network\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\Cache\Cache;

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

use SQLBuilder\Universal\Query\SelectQuery;

use App\Lib\CakeDbConnector;
use App\Lib\JobMetaHelper;
use App\Lib\CakeLogger;

use WC\Query\QueryHelper;
use WC\Backoffice\Settlement\BatchQuery;
use WC\Backoffice\Settlement\BatchBuilder;
use WC\Backoffice\Settlement\BatchData;
use WC\Backoffice\Settlement\BatchState;
use WC\Backoffice\SettlementService;
use WC\Backoffice\MerchantService;
use WC\Backoffice\ProcessException;

use \RemittanceReportReader;
use \MerchantWallet;

class SettlementBatchController extends AppController
{
    protected $defaultCurrency = 'CNY';
    protected $merchantCurrency = null;

    protected $merchantService = null;
    protected $service = null;

    protected $sortableFields = [
        'sales'=>
        [
            'reconciled_state_time',
            'state',
            'customer_name',
            'merchant',
            'tx_currency',
            'tx_amount',
            'tx_fee',
            'tx_net_amount',
            'settle_currency',
            'converted_amount',
        ],
        'refund'=>
        [
            'reconciled_state_time',
            'state',
            'customer_name',
            'merchant',
            'tx_currency',
            'tx_amount',
            'tx_fee',
            'tx_net_amount',
            'settle_currency',
            'converted_amount',
        ],
    ];


    public function initialize()
    {
        parent::initialize();


        $this->connection = ConnectionManager::get('default');

        $logger = CakeLogger::shared();

        // Preparing database adapter to QueryHelper.
        CakeDbConnector::setShared($this->connection);

        $this->service = new SettlementService();
        $this->service->setLogger($logger);
        $this->merchantService = new MerchantService();
        $this->merchantService->setLogger($logger);


        $this->loadModel('Merchants');
        $this->loadModel('MerchantGroup');
        $this->loadModel('MerchantGroupId');
        $this->loadModel('TransactionLog');
        $this->loadModel('SettlementBatch');
    }

    /**
     * Showing the screen of listing all possible merchants
     *
     * @return void
     */
    public function index()
    {
    }

    /**
     * Refresh all merchants unsettled
     *
     * @param string $format Requested format
     *
     * @return void
     */
    public function refreshMerchantsUnsettled($format = 'json')
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

        $this->service->updateMerchantUnsettled();

        // Tell them it's ready for fetch
        return $this->dataResponse(['status'=>'done']);
    }

    /**
     * A method for fetching all unsettled merchants
     *
     * @param string $format Requested format
     *
     * @return void
     */
    public function fetchMerchantUnsettled($format = 'json')
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



        $this->loadModel('MerchantUnsettled');

        $query = $this->MerchantUnsettled->find('all');
        $query->join([
            'm' => [
                'table'=>'merchants',
                'conditions'=>['m.id = mu.merchant_id'],
            ],
            'mgi' => [
                'table'=>'merchants_group_id',
                'conditions'=>['mgi.merchant_id = m.id'],
            ],
            'mg' => [
                'table'=>'merchants_group',
                'conditions'=>['mgi.id = mg.id'],
            ],
        ]);
        $query->select([
            'merchantgroup_id'=>'mg.id',
            'merchantgroup_name'=>'mg.name',
            'master_merchant_id'=>'m.id',
            'merchant_fx_package'=>'m.settle_option',
            'merchant_settle_rate_symbol'=>'m.settle_rate_symbol',
            'currency',
            'start_date' ,
            'end_date' ,
            'tx_total',
            'amount',
        ]);
        $query->where(['tx_total > 0']);
        $query->order('mg.name', 'ASC');
        $grouped = [];
        $items = [];

        $this->log($query, 'debug');
        
        // (Create) object with 2 property by json transaction short-cut
        $result = json_decode(json_encode(['items'=>[], 'grouped'=>[]]));
        
        foreach ($query->toArray() as $index => $item) {
            $result->items[] = $item;
            $group_id = $item['merchantgroup_id'];

            if (isset($result->grouped [$group_id])) {
                $group = $result->grouped[ $group_id ];
            } else {
                // Short-cut to build anonymous object in php.
                $group = json_decode(json_encode(['id'=> $group_id]));
                $group->id = $group_id;
                $group->name = $item['merchantgroup_name'];
                $group->master_merchant_id = $item['master_merchant_id'];
                $group->min_date = new \DateTime($item['start_date']);
                $group->max_date = new \DateTime($item['end_date']);
                $group->fx_package = $item['merchant_fx_package'];
                $group->settle_rate_symbol = $item['merchant_settle_rate_symbol'];
                $group->details = [];
                $group->s_amount = '';
                $group->s_count = '';
                $group->s_currency = '';
                $group->action_allowed = true;
                $group->action_url = '';

                if ($group->fx_package == '2' && empty($group->settle_rate_symbol)) {
                    $group->action_allowed  = false;
                }

                $result->grouped[ $group->id ] = $group;
            }

            if (count($group->details)> 0) {
                $group->s_amount .= "<br />\n";
                $group->s_count .= "<br />\n";
                $group->s_currency .= "<br />\n";
            }

            $group->s_amount .= number_format($item['amount'], 2, '.', ',');
            $group->s_count .= $item['tx_total'];
            $item_min_date = new \DateTime($item['start_date']);
            $item_max_date = new \DateTime($item['end_date']);

            // Comparing date by using DateTime comparsion
            if ($item_min_date < $group->min_date) {
                $group->min_date = $item_min_date;
            }

            if ($item_max_date > $group->max_date) {
                $group->max_date = $item_max_date;
            }
            
            $group->s_currency .= $item['currency'];

            $group->details[] = [
                'master_merchant_id'=>$item['master_merchant_id'],
                'currency'=>$item['currency'],
                'amount'=>$item['amount'],
                'min_date'=>$item_min_date,
                'max_date'=>$item_max_date,
                'count' => $item['tx_total'],
            ];
        };


        $group_ids = array_keys($result->grouped);
        $data = [];
        // Transalate key-value based group into simple array form.
        foreach ($group_ids as $group_id) {
            $group = $result->grouped[ $group_id ];

            $group->s_min_date = $group->min_date->format('Y-m-d');
            $group->s_max_date = $group->max_date->format('Y-m-d');

            $group->action_url = Router::url([
                'action'=>'create',
                $group->master_merchant_id,
            ]).'?'.http_build_query([
                'start'=>$group->s_min_date,
                'end'=> $group->s_max_date,
            ]);
            $data[] = $group;
        }

        $this->dataResponse(['data'=>$data,'total'=>count($data), 'grouped'=>$result->grouped, 'items'=>$result->items]);
    }

    /**
     * The action for builing spreadsheet
     *
     * @param string $format Requested format
     *
     * @return void
     */
    public function downloadMerchantUnsettled($format = 'json')
    {

        set_time_limit(0);

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

        $response =['status'=>'error','type'=>'UnknownReason'];

        // Export as excel
        $file = sprintf('xls/SettlementBatchMerchantsUnsettled-%s', time());

        $writer = new \App\Tasks\Writers\MerchantUnsettledWriter([]);
        $writer->config($this->service);
        $writer->save($file);
        
        $file.=".xlsx";
        $xlsurl = Router::url(['controller'=>'QueueJob','action' => 'serveFile', $file]);
        $response = ['status'=>'done', 'msg'=>'Success','url'=>$xlsurl];
    

        $this->dataResponse($response);
    }

    /**
     * Screen method for rendering create process
     *
     * @param string $merchant_id Master merchant id
     *
     * @return void
     */
    public function create($merchant_id = null)
    {
        $startDate = $this->request->query('start');
        $endDate = $this->request->query('end');
        
        // Get user information
        $user = $this->Auth->user();

        try {
            if (empty($startDate)) {
                throw new \Exception(__('Start date is required.'));
            }
            if (empty($endDate)) {
                throw new \Exception(__('End date is required.'));
            }
            if (strtotime($startDate) > time() || strtotime($startDate) > time()) {
                throw new \Exception(__('Invalid date range. The given range could not be included with the date after today.'));
            }
            if (strtotime($endDate) < strtotime($startDate)) {
                throw new \Exception(__('Invalid date range. End date is earlier than the start date.'));
            }

            if (empty($merchant_id)) {
                throw new \Exception(__('Master merchant is required.'));
            }
            
            

            $particulars = $this->request->data('particulars');
            $reportDate = $this->request->data('report_date');

            $params = compact('particulars', 'txid', 'ntx');
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
            $parmas['no_summary_result'] = true;
            $params['no_cache'] = true;
            
            $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);

            $checksum = $this->request->data('checksum');
            
            // Remove cached data after submit a batch.
            if (!empty($checksum)) {
                $this->removeBatchCache($checksum);
            }

            // Force ignoring cached data.
            $batchData = $this->createBatchWithCache($masterMerchant, $params, $checksum, true);

            $defaultCurrency = $batchData->defaultCurrency;
            $merchantCurrency = $batchData->merchantCurrency;


            // Create wallet instance for handling batch data change.
            $wallet = new MerchantWallet($batchData->masterMerchantId);
            $wallet->setUser($user['username']);

            $walletIdDefault  = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->defaultCurrency);
            $walletIdMerchant = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->merchantCurrency);
            $walletIdCarryForward = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_CARRYFORWARD, $batchData->merchantCurrency);

            if (!$wallet->isWalletExist($walletIdDefault)) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement Primary Wallet (Default Currency) does not exist.', ['currency'=>$batchData->defaultCurrency]);
            }
            if (!$wallet->isWalletExist($walletIdMerchant) && $batchData->defaultCurrency != $batchData->merchantCurrency) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement Primary Wallet (Merchant Currency) does not exist.', ['currency'=>$batchData->merchantCurrency]);
            }
            if (!$wallet->isWalletExist($walletIdCarryForward)) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement CarryForward Wallet (Merchant Currency) does not exist.', ['currency'=>$batchData->merchantCurrency]);
            }
        } catch (\Exception $exp) {
            $this->Flash->error($exp->getMessage());
            return $this->redirect(['action' => 'index']);
        }
        
        $particulars = $batchData->toParticularGridInfo();

        
        $this->set('masterMerchant', $masterMerchant);
        $this->set('startDate', $startDate);
        $this->set('endDate', $endDate);
        $this->set('localChecksum', $batchData->getChecksum());
        $this->set('defaultCurrency', $defaultCurrency);
        $this->set('merchantCurrency', $merchantCurrency);
        $this->set('particulars', $particulars);
    }

    /**
     * Preparing initial data for the batch creation
     *
     * @param string $merchant_id Master merchant id.
     * @param string $format      The request format.
     * 
     * @return void
     */
    public function fetchInitialMerchant($merchant_id, $format = 'json')
    {
        
        set_time_limit(0);

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

        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');
        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');
        $requested_amount = floatval($this->request->data('requested_amount'));
        // if (empty($'startDate')) {
        //     throw new \Exception(__('Unknown start date.'));
        // }
        // if (empty($endDate)) {
        //     throw new \Exception(__('Unknown end date.'));
        // }
        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }
        if ($startDateNtx == 'null') {
            $startDateNtx = null;
        }
        if ($endDateNtx == 'null') {
            $endDateNtx = null;
        }
        if ($ntx == 'null') {
            $ntx = null;
        }

        $params = [];
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;

        $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);

        $checksum = $this->request->data('checksum');
        $checksumChanged = $this->request->data('checksumChanged') == 'yes';
        
        $batchData = $this->createBatchWithCache($masterMerchant, $params, $checksum, $checksumChanged);

        /////// Update from "reload"

        $fields = [
            'tx.id'=>'id',
            'tx.currency'=>'tx_currency',
            ''.$batchData->querySet->getNetAmountCase().'' => 'tx_net_amount',
            ''.$batchData->querySet->getConvertedNetAmountCase().'' =>'converted_amount',
            "DATE_FORMAT(tx.reconciled_state_time,'%Y-%m-%d')"=>'state_date',
        ];
        $query = clone $batchData->querySet->sales;
        $query->setSelect($fields);

        $rs = new \WC\Query\Resultset($this->service->getDb(), $query);
        $rs->setLogger(CakeLogger::shared());
        $data = $rs->map(function ($entity, $index) use ($batchData) {
            $entity['tx_net_amount'] = round($entity['tx_net_amount'], 2);
            $entity['converted_amount'] = round($entity['converted_amount'], 2);
            $entity['reconciled_state_time'] = $entity['state_date'];
            return $entity;
        });

        $response = ['status'=>'done', 'msg'=>'Success'];
        $response['data'] = $data;
        $response['count'] = count($data);

        // Send the response to client-side
        $this->dataResponse($response);
    }


    /**
     * Provide the list sales transaction log id
     *
     * @param string $merchant_id Master merchant ID
     * @param string $format      Requested format
     *
     * @return void
     */
    public function fetchSuggestedTx($merchant_id, $format = 'json')
    {

        set_time_limit(0);

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


        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');
        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');
        $requested_amount = floatval($this->request->data('requested_amount'));
        // if (empty($'startDate')) {
        //     throw new \Exception(__('Unknown start date.'));
        // }
        // if (empty($endDate)) {
        //     throw new \Exception(__('Unknown end date.'));
        // }
        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }
        if ($startDateNtx == 'null') {
            $startDateNtx = null;
        }
        if ($endDateNtx == 'null') {
            $endDateNtx = null;
        }
        if ($ntx == 'null') {
            $ntx = null;
        }

        // If the amount does not provided, we assume all tx selected by provided range.
        if (!is_float($requested_amount) || $requested_amount <= 0) {
            $requested_amount = -1;
        }
        $particulars = $this->request->data('particulars');
        $reportDate = $this->request->data('report_date');

        $params = compact('particulars', 'txid', 'ntx');
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
        $params['start_date_ntx'] = $startDateNtx;
        $params['end_date_ntx'] = $endDateNtx;
        $params['report_date'] = $reportDate;
        
        $params['no_summary_result'] = true;
        $params['no_cache'] = true;

        $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);
        

        $checksum = $this->request->data('checksum');
        $checksumChanged = $this->request->data('checksumChanged') == 'yes';
        
        $batchData = $this->createBatchWithCache($masterMerchant, $params, $checksum, $checksumChanged);
        
        /////// Update from "reload"


        $response = $this->service->batchBuilder->findSuggestedTransaction($masterMerchant, $startDate, $endDate, $requested_amount);

        $response = array_merge([
            'status'=>'done',
            'msg'=>'Success',
            'requested_amount'=>$requested_amount,
        ], $response);

        // Send the response to client-side
        $this->dataResponse($response);
    }

    /**
     * Undocumented function
     *
     * @param string $batch_id Batch ID
     *
     * @return void
     */
    public function fetchBatchRemittanceTx($batch_id, $merchant_id)
    {
        $db_name = ConnectionManager::get('default')->config()['database'];
        $reader = new RemittanceReportReader($db_name);
        $_data = $reader->getBatchDetails($batch_id, true);

        if (!is_array($_data)) {
            throw new \Exception('No result for batch id '.$batch_id);
        }

        $defaultCurrency = BatchBuilder::DEFAULT_CURRENCY;

        $data = [];
        foreach ($_data as $idx => $row) {
            $_raw = $row;
            
            // Filter status code below 0 mean its failure.
            if (intval($row['tx_status']) < 0) {
                continue;
            }

            if ($defaultCurrency == $row['convert_currency']) {
                $row['convert_currency'] = $_raw['currency'];
                $row['convert_amount']  = $_raw['amount'];
                $row['paid_amount']  = $_raw['convert_paid_amount'];
                $row['convert_paid_amount']  = $_raw['paid_amount'];
                $row['amount'] = $_raw['convert_amount'];
                $row['currency'] = $_raw['convert_currency'];
            }
            $row['index'] = $idx + 1;
            $row['amount'] *= -1;
            $row['convert_amount'] *= -1;
            $data[ ] = $row;
        }
        $total = count($data);

        $this->dataResponse(compact('batch_id', 'data', 'total'));
    }

    /**
     * Return transaction list in json
     *
     * @param string $merchant_id Merchant ID
     * @param string $format      Request format
     *
     * @return void
     */
    public function fetchMerchant($merchant_id = null, $format = 'json')
    {

        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown typ                                                                                                                                                                                                                                                                                                                                                             e
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);

        $checksum = $this->request->data('checksum');

        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');


        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');

        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }
        /////// Update from "reload"
        //

        // If txid passed in form data, extrat it.
        $req_txid = isset($this->request->data['txid']) ? $this->request->data('txid') : null;
        if (is_string($req_txid)) {
            $req_txid = empty($req_txid)? []: explode(',', trim($req_txid));
        }

        $particulars = $this->request->data('particulars');
        $reportDate = $this->request->data('report_date');

        $params = compact('particulars', 'ntx');
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
        $params['start_date_ntx'] = $startDateNtx;
        $params['end_date_ntx'] = $endDateNtx;
        $params['report_date'] = $reportDate;
        $params['txid'] = $req_txid;

        $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);


        $checksum = $this->request->data('checksum');
        $checksumChanged = $this->request->data('checksumChanged') == 'yes';

        $clearCache = $this->request->data('clearCache') == 'yes';

        // Remove cached data if requested
        if (!empty($checksum) && $clearCache) {
            $this->removeBatchCache($checksum);
        }
        
        $batchData = $this->createBatchWithCache($masterMerchant, $params, $checksum, $checksumChanged);
        
        if (!empty($req_txid) && is_array($req_txid)) {
            $matchIds = [];
            $unmatchIds = [];
            $counter = 0;
            // Try to find out is all the selection is available from database.
            for ($i = 0; $i < count($req_txid); $i ++) {
                if (in_array($req_txid[$i], $batchData->txid) && !in_array($req_txid[$i], $matchIds)) {
                    $matchIds[] = $req_txid[$i];
                } else {
                    $unmatchIds[] = $req_txid[$i];
                }
            }

            // if (count($batchData->txid) != count($req_txid)) {
            //     $response = [
            //         'status'=>'error',
            //         'numRequested'=>count($req_txid),
            //         'numStored'=>count($batchData->txid),
            //         'type'=>'UnmatchedTx',
            //         'msg'=>'Selected transaction does not match with database stored.'
            //     ];
            //     $this->log($response, 'error');
    
            //     return $this->dataResponse($response);
            // }
            
            // If changed, tell client-side about the update
            if (count($unmatchIds)> 0) {// Unlock path
                
                $response = [
                    'status'=>'error',
                    'unmatchIds'=>$unmatchIds,
                    'matchIds'=>$matchIds,
                    'type'=>'UnmatchedTx',
                    'msg'=>'Selected transaction is not available.'
                ];
                $this->log($response, 'error');
    
                return $this->dataResponse($response);
            }
        }

        $response = [
           'status'=>'done',
           'msg'=>'Success',
        //    'checksumValue'=>$batchData->getChecksumValue(),
           'checksum'=>$batchData->getChecksum(),
           'merchant_id'=>$batchData->masterMerchantId,
           'particulars'=> $batchData->particulars,
           'merchant'=>[
               'settleBankAccount'=>$batchData->bankAccount,
               'settleBankName'=>$batchData->bankName,
           ],
        ];
    
        // Send the response to client-side
        $this->dataResponse($response);
    }

    /**
     * Return merchant's transaction list in json
     *
     * @param string $merchant_id Merchant ID
     * @param string $format      Request format
     *
     * @return void
     */
    public function fetchMerchantDetail($merchant_id = null, $format = 'json')
    {

        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new \Exception(__('Unknown format.'));
        }

        $particular = $this->request->data('particular');
        if (empty($particular) || !is_string($particular)) {
            throw new \Exception(__('Missing particular.'));
        }
        
        $currency = $this->request->data('currency');

        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');
        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');

        // if (empty($'startDate')) {
        //     throw new \Exception(__('Unknown start date.'));
        // }
        // if (empty($endDate)) {
        //     throw new \Exception(__('Unknown end date.'));
        // }
        if (empty($merchant_id)) {
            throw new \Exception(__('Unknown master merchant.'));
        }
        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }
        if ($startDateNtx == 'null') {
            $startDateNtx = null;
        }
        if ($endDateNtx == 'null') {
            $endDateNtx = null;
        }
        if ($ntx == 'null') {
            $ntx = null;
        }

        $particulars = $this->request->data('particulars');

        // If txid passed in form data, extrat it.
        $req_txid = isset($this->request->data['txid']) ? $this->request->data('txid') : null;
        if (is_string($req_txid)) {
            $req_txid = empty($req_txid)? []: explode(',', trim($req_txid));
        }

        $params = compact('particulars', 'ntx');
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
        $params['start_date_ntx'] = $startDateNtx;
        $params['end_date_ntx'] = $endDateNtx;
        $params['txid'] = $req_txid;
        // $params['report_date'] = $reportDate;

        $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);
        

        // For Batch Remittance Transaction, use RemittanceReportReader->getBatch() direcctly
        if ($particular == 'batchRemittanceTx') {
            $rBatchId = $this->request->data('rBatchId');
            if (empty($rBatchId)) {
                throw new \Exception(__('Missing rBatchId'));
            }
            $this->fetchBatchRemittanceTx($rBatchId, $masterMerchant['id']);
            return;
        }
        
        $checksum = $this->request->data('checksum');
        $checksumChanged = $this->request->data('checksumChanged') == 'yes';
        
        $batchData = $this->createBatchWithCache($masterMerchant, $params, $checksum, $checksumChanged);
        
        $particularTypesKeys = array_keys(BatchData::$particularTypes);
        if (!in_array($particular, $particularTypesKeys)) {
            throw new \Exception(__('Unsupported particular.'));
        }

        $querySet = $batchData->querySet;

        // If passing currency, we filter with it in all sales/refund transaction.
        // remittance must be in CNY.
        if (!empty($currency)) {
            $querySet->sales->where(['tx.CURRENCY'=> $currency]);
            $querySet->refund->where(['tx.CURRENCY'=> $currency]);
        }

        // A flag to control is it necessary to fetch data.
        $allowed = true;

        // Base response setting
        $response = [
           'status'=>'done',
           'msg'=>'Success',
           'total'=>0,
           'data'=>[],
        ];


        // Apply to sales transaction only
        // If `txid` exist in post/get parameter, filter the result with these transaction id.

        // If `txid` is empty, we reported them directly.
        if ($particular == 'sales') {
            if (empty($req_txid) || !is_array($req_txid)) {
                    $allowed = false;
            } else {
                $querySet->sales->where()->in('tx.id', $req_txid);
            }
        }

        if ($allowed) {
            $start_offset = 0;
            $page_size = -1;
            if (isset($this->request->data['page']) && isset($this->request->data['pageSize'])) {
                $page_size = intval($this->request->data['pageSize']);
                if ($page_size < 1) {
                    $page_size = 1;
                }
                $start_offset = (intval($this->request->data['page']) -1 ) * $page_size;
            }

            // // Getting the total number first.
            // $searchResult = $querySet->getResult($particular, null, $start_offset, $page_size);
            // $response['total'] = $searchResult->total;

            $searchResult = $querySet->getResult($particular, null, $start_offset, $page_size, function ($query) use ($particular) {

                $req_filter = null;
                if (!empty($this->request->data['filter']) && is_array($this->request->data['filter'])) {
                    $req_filter = $this->request->data['filter'];
                }
    
                $req_sort = null;
                if (!empty($this->request->data['sort']) && is_array($this->request->data['sort'])) {
                    $req_sort = $this->request->data['sort'];
                }
                
                $this->createGridDetailQuery($query, $particular, $req_filter, $req_sort);
            });
            $this->service->debug('SearchResult['.$particular.'].count='.$searchResult->total);
            $response['total'] = $searchResult->total;
            $response['data'] = $searchResult->data;
        }

        $this->dataResponse($response);
    }
    /**
     * Create a background job for export all settlement data into a excel file
     *
     * @param string $merchant_id Master merchant id
     * @param string $format      Requested format
     *
     * @return void
     */
    public function queueDownloadPreview($merchant_id, $format = 'json')
    {
        set_time_limit(0);
        
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
        
        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');
        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');
        
        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }

        $txid = [];
        $req_txid = isset($this->request->data['txid']) ? $this->request->data('txid') : null;
        if (is_string($req_txid)) {
            $req_txid = explode(',', trim($req_txid));
        } elseif (is_array($req_txid)) {
            $req_txid = $req_txid;
        }

        // Normalized
        $txid = $this->service->batchBuilder->compressTxidArray(implode(',', $req_txid));

        $particulars = $this->request->data('particulars');
        $reportDate = $this->request->data('report_date');

        $params = compact('particulars', 'txid', 'ntx');
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
        $params['start_date_ntx'] = $startDateNtx;
        $params['end_date_ntx'] = $endDateNtx;
        $params['report_date'] = $reportDate;

        $config = compact('merchant_id', 'ntx');


        $task_name = '\\App\\Tasks\\SettlementBatchPreviewExportTask';
        $queue_name = 'excelexport';
        $type = 'excelexport';

        $any_data = false;


        $job_data = compact('config', 'params', 'type');
        // $this->log(__METHOD__.'/ data.length= '. strlen(json_encode($job_data)), 'debug');
        // $this->log(__METHOD__.'/ data= '. json_encode($job_data), 'debug');
        
        $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

        $this->log("Added Queue Task for {$task_name}. JobID={$job_id}", 'info');

        return $this->dataResponse(['status'=>'added','id'=>$job_id]);
    }

    /**
     * A method for submitting a batch from create step.
     *
     * @param string $merchant_id Merchant ID
     * @param string $format      Requested format
     *
     * @return void
     */
    public function submit($merchant_id, $format = 'json')
    {
        set_time_limit(0);
        
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
        

        $startDate = $this->request->data('start');
        $endDate = $this->request->data('end');
        $startDateNtx = $this->request->data('start_ntx');
        $endDateNtx = $this->request->data('end_ntx');
        $ntx = $this->request->data('ntx');
        
        if ($startDate == 'null') {
            $startDate = null;
        }
        if ($endDate == 'null') {
            $endDate = null;
        }
        $req_txid = isset($this->request->data['txid']) ? $this->request->data('txid') : null;
        if (is_string($req_txid)) {
            $req_txid =empty($req_txid)? []:  explode(',', trim($req_txid));
        }

        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'SettlementBatch.lock';
        
        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }
        $this->log('Settlement token locked.', 'debug');
        


        $particulars = $this->request->data('particulars');
        $reportDate = $this->request->data('report_date');

        $params = compact('particulars', 'ntx');
        $params['start_date'] = $startDate;
        $params['end_date'] = $endDate;
        $params['start_date_ntx'] = $startDateNtx;
        $params['end_date_ntx'] = $endDateNtx;
        $params['report_date'] = $reportDate;
        $params['txid'] = $req_txid;

        $masterMerchant = $this->merchantService->getMasterMerchant($merchant_id, true);
        
        $batchData = $this->service->batchBuilder->create($masterMerchant, $params);
        $batchData->id = Text::uuid(); // Assign the batch id for the creation
        
        
        try {
            // Requesting client side to submit the total amount for comparing server-side result
            $requestedTotalSettlementAmount = assume($particulars, 'totalSettlementAmount', null);
            if (!isset($requestedTotalSettlementAmount['converted_amount'])) {
                throw new ProcessException('InvalidTotalSettlementAmount', 'submitBatch', 'Total settlement amount need to be submitted.');
            }

            $requestedTotalSettlementAmount['converted_amount'] = round(floatval($requestedTotalSettlementAmount['converted_amount']), 2);
    
            // Warning if total settlement amount is equal / lower than zero in server-side result
            $totalSettlementAmount = $batchData->getParticularData('totalSettlementAmount', $batchData->merchantCurrency);
            $totalSettlementAmount['converted_amount'] = round(floatval($totalSettlementAmount['converted_amount']), 2);

            if (round($totalSettlementAmount['converted_amount'], 2) != round($requestedTotalSettlementAmount['converted_amount'], 2)) {
                $response = [
                    'server'=>$totalSettlementAmount['converted_amount'],
                    'requested'=>$requestedTotalSettlementAmount['converted_amount'],
                    'particulars'=>$batchData->particulars,
                ];
                throw new ProcessException('UnmatchedTotalSettlementAmount', 'submitBatch', 'Submitted total settlement amount does not match server-side result.', $response);
            }
    

            $submittingChecksum = $batchData->getChecksum();
            
            $this->service->batchProcessor->submit($batchData, $user);
            
            $this->Flash->success('Settlement batch created.');
            $response = [
                'status'=>'done',
                'msg'=>'Batch created.',
                'id'=>$batchData->id,
                'url'=>Router::url(['action'=>'view', $batchData->id]),
            ];

            // Remove cached data after submit a batch.
            $this->removeBatchCache($submittingChecksum);

            $this->log($response, 'debug');

            // Update merchant unsettled records after batch created.
            $this->service->updateMerchantUnsettled();

            // Clear cache if checksum provided.
            $checksum = $this->request->data('checksum');
            if (!empty($checksum)) {
                $cacheKey = 'settlement_batch_cached_'.$checksum;
                Cache::delete($cacheKey);
            }
        } catch (ProcessException $exp) {
            $response = [
                'status'=>'error',
                'type'=>$exp->type,
                'msg'=>$exp->message,
            ];
            if ($exp->data != null) {
                $response['data'] = $exp->data;
            }
            $this->log($response, 'error');
        } catch (\Exception $exp) {
            $response = [
                'status'=>'error',
                'type'=>'Exception',
                'exception'=>get_class($exp),
                'msg'=>$exp->getMessage(),
            ];
            $this->log($response, 'error');
        }

        // Unlock path
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);
        $this->log('Settlement token unlocked.', 'debug');

        return $this->dataResponse($response);
    }

    /**
     * View method for settlement batch search
     *
     * @return void
     */
    public function search()
    {

        $query =  $this->MerchantGroup->find('all');
        $query
            ->select(['mg.id','mg.name','merchant_id'=>'mgid.merchant_id'])
            ->join([
                'table'=>'merchants_group_id',
                'alias'=>'mgid',
                'conditions'=>'mg.id = mgid.id AND master = 1',
            ]);
        $query
            ->where(['mg.status'=>'1'])
            ->order(['mg.name' => 'ASC']);

        $merchantgroupAry = $query->toArray();
        $merchantgroups = [];

        $mercdata = [];
        foreach ($merchantgroupAry as $group) {
            $mercdata[ $group['merchant_id'] ] = $group['name'];
            $merchantgroups [ $group['merchant_id'] ] = $group;
        }



        if ($this->request->is('ajax')) {
            $response = [
                'status'=>'done',
                'msg'=>'Success.',
                'data'=>[],
                'total'=>0,
            ];

            foreach (['state'=>'sb.state', 'merchant_id'=>'sb.merchant_id'] as $fieldName => $condAlias) {
                $val = $this->request->data($fieldName);
                if (!empty($val)) {
                    if (is_string($val)) {
                        $val = explode(',', trim($val));
                    }
                    $conditions[] = [ $condAlias, 'IN', $val];
                }
            }
    

            $start_date = null;
            $end_date = null;
            if (!empty($this->request->data['start_date'])) {
                $start_date = new \DateTime( $this->request->data['start_date']);
                $start_date->setTime(0, 0, 0);
            }
            if (!empty($this->request->data['start_date_ts'])) {
                $start_date = new \DateTime();
                $start_date->setTimestamp((intval($this->request->data['start_date_ts'])/1000)<<0);
                $start_date->setTime(0, 0, 0);
            }
            if (!empty($this->request->data['end_date'])) {
                $end_date = new \DateTime( $this->request->data['end_date']);
                $end_date->setTime(0, 0, 0);
            }
            if (!empty($this->request->data['end_date_ts'])) {
                $end_date = new \DateTime();
                $end_date->setTimestamp((intval($this->request->data['end_date_ts'])/1000)<<0);
                $end_date->setTime(0, 0, 0);
            }
    
            if (!empty($start_date)) {
                $conditions[] = ['report_date', '>=',  $start_date->format('Y-m-d').' 00:00:00'];
            }
            
            if (!empty($end_date)) {
                $conditions[] = ['report_date', '<',  $end_date->add(new \DateInterval('P1D'))->format('Y-m-d')];
            }
            
            // Handling for paging request.
            $offset = -1;
            $pageSize = -1;

            if (!empty($this->request->data['page']) && !empty($this->request->data['pageSize'])) {
                $pageSize = intval($this->request->data['pageSize']);
                if ($pageSize < 1) {
                    $pageSize = 1;
                }
                
                $offset = (intval($this->request->data['page']) - 1) * $pageSize;
                if ($offset < 0) {
                    $offset = 0;
                }
            }

            $data = $this->service->findBatches($conditions, true, $offset, $pageSize);


            $response['offset'] = ($offset);
            $response['pageSize'] = ($pageSize);
            $response['total'] = count($data);
            $response['data'] = $data;

            return $this->dataResponse($response);
        }
        $this->set('merchantgroup_lst', $mercdata);
    }

    /**
     * View method for settlement batch detail
     *
     * @param string $batchId Batch id for the action
     *
     * @return void
     */
    public function view($batchId = null)
    {
        try {
            $batchRow = $this->SettlementBatch->get($batchId);
            
            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);
            
            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);

            // Added on 27 Nov 2017 for the bank info & handling fee update
            // If the state is OPEN

            if ($batchData->state == BatchState::OPEN) {
                $this->service->batchProcessor->merchantConfigUpdate($batchData, $masterMerchant);
            }


            $particulars = $batchData->toParticularGridInfo();
        } catch (\Exception $exp) {
            $this->Flash->error($exp->getMessage());
            return $this->redirect(['action' => 'search']);
        }
        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;


        $this->set('batchId', $batchData->id);
        $this->set('batchRow', $batchRow);
        $this->set('masterMerchant', $batchData->masterMerchant);
        $this->set('startDate', $batchData->startDate->format('Y-m-d'));
        $this->set('endDate', $batchData->endDate->format('Y-m-d'));
        $this->set('defaultCurrency', $defaultCurrency);
        $this->set('merchantCurrency', $merchantCurrency);
        $this->set('particulars', $particulars);
    }

    public function fetchInitialBatch($batchId, $format = 'json')
    {
        
        set_time_limit(0);

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


        if (empty($batchId)) {
            throw new \Exception(__('Unknown batch.'));
        }
        try {
            $batchRow = $this->SettlementBatch->get($batchId);
            
            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);
            
            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        } catch (\Exception $exp) {
            return $this->dataResponse(['status'=>'error', 'msg'=>$exp->getMessage()]);
        }


        /////// Update from "reload"

        $fields = [
            'tx.id'=>'id',
            ''.$batchData->querySet->getNetAmountCase().'' => 'tx_net_amount',
            ''.$batchData->querySet->getConvertedNetAmountCase().'' =>'converted_amount',
            "DATE_FORMAT(tx.reconciled_state_time,'%Y-%m-%d')"=>'state_date',
        ];
        $query = clone $batchData->querySet->sales;
        $query->setSelect($fields);

        $rs = new \WC\Query\Resultset($this->service->getDb(), $query);
        $rs->setLogger(CakeLogger::shared());
        $data = $rs->map(function ($entity) use ($batchData) {
            $entity['tx_net_amount'] = round($entity['tx_net_amount'], 2);
            $entity['converted_amount'] = round($entity['converted_amount'], 2);
            $entity['reconciled_state_time'] = $entity['state_date'];
            return $entity;
        });

        $response = ['status'=>'done', 'msg'=>'Success'];
        $response['data'] = $data;
        $response['count'] = count($data);

        // Send the response to client-side
        $this->dataResponse($response);
    }

    /**
     * Return transaction list in json
     *
     * @param string $batchId Batch ID
     * @param string $format  Request format
     *
     * @return void
     */
    public function fetchBatch($batchId = null, $format = 'json')
    {

        set_time_limit(0);

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


        if (empty($batchId)) {
            throw new \Exception(__('Unknown batch.'));
        }
        try {
            $batchRow = $this->SettlementBatch->get($batchId);
            
            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);
            
            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        } catch (\Exception $exp) {
            return $this->dataResponse(['status'=>'error', 'msg'=>$exp->getMessage()]);
        }


        $currentChecksumValue = $batchData->getChecksumValue();
        $currentChecksum = $batchData->getChecksum();
        
        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;
        
        $response = [
           'status'=>'done',
           'msg'=>'Success',
           'state'=>$batchData->state,
        //    'currentChecksumValue'=>$currentChecksumValue,
           'currentChecksum'=>$currentChecksum,
        //    'checksumValue'=>$batchData->getChecksumValue(),
           'checksum'=>$batchData->getChecksum(),
           'merchant_id'=>$batchData->masterMerchantId,
           'particulars'=> $batchData->particulars,
           'merchant'=>[
               'settleBankAccount'=>$batchData->bankAccount,
               'settleBankName'=>$batchData->bankName,
           ],
        ];
    
        // Send the response to client-side
        $this->dataResponse($response);
    }

    /**
     * The action for builing spreadsheet
     *
     * @param string $format Requested format
     *
     * @return void
     */
    public function downloadBatches($format = 'json')
    {

        set_time_limit(0);

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

        $response =['status'=>'error','type'=>'UnknownReason'];

        // Export as excel
        $file = sprintf('xls/SettlementBatches-%s', time());


        $conditions = [];
        // Setup callback for query conditions
        
        foreach (['state'=>'sb.state', 'merchant_id'=>'sb.merchant_id'] as $fieldName => $condAlias) {
            $val = $this->request->data($fieldName);
            if (!empty($val)) {
                if (is_string($val)) {
                    $val = explode(',', trim($val));
                }
                $conditions[] = [ $condAlias, 'IN', $val];
            }
        }

        $start_date = null;
        $end_date = null;
        if (!empty($this->request->data['start_date'])) {
            $start_date = new \DateTime( $this->request->data['start_date']);
            $start_date->setTime(0, 0, 0);
        }
        if (!empty($this->request->data['start_date_ts'])) {
            $start_date = new \DateTime();
            $start_date->setTimestamp((intval($this->request->data['start_date_ts'])/1000)<<0);
            $start_date->setTime(0, 0, 0);
        }
        if (!empty($this->request->data['end_date'])) {
            $end_date = new \DateTime( $this->request->data['end_date']);
            $end_date->setTime(0, 0, 0);
        }
        if (!empty($this->request->data['end_date_ts'])) {
            $end_date = new \DateTime();
            $end_date->setTimestamp((intval($this->request->data['end_date_ts'])/1000)<<0);
            $end_date->setTime(0, 0, 0);
        }

        if (!empty($start_date)) {
            $conditions[] = ['report_date', '>=',  $start_date->format('Y-m-d').' 00:00:00'];
        }
        
        if (!empty($end_date)) {
            $_end_date = clone $end_date;
            $_end_date->add(new \DateInterval('P1D'));
            $conditions[] = ['report_date', '<',  $_end_date->format('Y-m-d')];
        }
        
        $data = [
            'conditions'=> $conditions, 
            'start_date'=>$start_date->format('Y-m-d'), 
            'end_date'=>$end_date->format('Y-m-d'),
            'merchant_id'=>$this->request->data('merchant_id'),
            'state'=>$this->request->data('state'),
        ];
        $writer = new \App\Tasks\Writers\SettlementBatchListWriter($data);
        $writer->config($this->service, $this->merchantService);
        $writer->save($file);
        
        $file.=".xlsx";
        $xlsurl = Router::url(['controller'=>'QueueJob','action' => 'serveFile', $file]);
        $response = ['status'=>'done', 'msg'=>'Success','url'=>$xlsurl];
    

        $this->dataResponse($response);
    }

    /**
     * Hold transaction
     *
     * @param string $batchId The batch id
     * @param string $format  The request format.
     *
     * @return void
     */
    public function holdTx($batchId = null, $format = 'json')
    {
        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];


        // Get user information
        $user = $this->Auth->user();

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);

        $wallet = null;

        try {
            $txid = $this->request->data('txid');
            if (empty($txid)) {
                throw new ProcessException('TransactionIdRequired', 'particularChange', __('No transaction id. in the request.'));
            }

            if (empty($batchId)) {
                throw new ProcessException('BatchIdRequired', 'particularChange', __('No batch id. in the request.'));
            }

            $batchRow = $this->SettlementBatch->get($batchId);
            
            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);

            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);

            $wallet = new MerchantWallet($batchData->masterMerchantId);

            // Wallet Id Lookup
            $walletIdDefault  = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->defaultCurrency);
            $walletIdMerchant = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->merchantCurrency);
            $walletIdCarryForward = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_CARRYFORWARD, $batchData->merchantCurrency);

            if (!$wallet->isWalletExist($walletIdDefault)) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement Primary Wallet (Default Currency) does not exist.', ['currency'=>$batchData->defaultCurrency]);
            }
            // For settlement primary wallet, we check once for each currency based wallet
            if (!$wallet->isWalletExist($walletIdMerchant) && $batchData->defaultCurrency != $batchData->merchantCurrency) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement Primary Wallet (Merchant Currency) does not exist.', ['currency'=>$batchData->merchantCurrency]);
            }
            if (!$wallet->isWalletExist($walletIdCarryForward)) {
                throw new ProcessException('WalletNotExist', 'particularChange', 'Settlement CarryForward Wallet (Merchant Currency) does not exist.', ['currency'=>$batchData->merchantCurrency]);
            }
            
            // This action is allowed for batch state in OPEN.
            if ($batchData->state != 'OPEN') {
                throw new ProcessException('UnmatchedSettlementStatus', 'particularChange', 'Settlement batch is not allowed to change.');
            }

            // Verify if the calculated checksum is matched with database saved version.
            $currentChecksum = $batchData->getChecksum();
            if ($currentChecksum != $batchRow['editable_checksum']) {
                $data = [
                    'storedChecksum'=>$batchRow['editable_checksum'],
                    'currentChecksum'=>$currentChecksum,
                ];
                throw new ProcessException('InvalidInternalChecksum', 'particularChange', 'Internal error - database checksum does not match with runtime calculated.', $data);
            }
            
            // Verify if the submitted checksum is matched with database version.
            // It is required for changing batch data.
            $checksum = $this->request->data('checksum');
            if ($batchData->getChecksum() != $checksum) {
                $data = [
                    'storedChecksum'=>$batchRow['editable_checksum'],
                    'submittedChecksum'=>$checksum,
                ];
                throw new ProcessException('UnmatchedChecksum', 'particularChange', 'Submitted checksum value does not match with saved version.', $data);
            }
            // Getting the formatted transaction with settlement rate (if available.)
            $tx = $batchData->querySet->getTransactionByLogId($txid);
            if (empty($tx['id'])) {
                throw new ProcessException('TrasnactionNotFound', 'particularChange', 'Transaction record does not found.', $data);
            }
    
            // Only SALE record could be set to withheld state
            if ($tx['state'] != 'SALE') {
                throw new ProcessException('TransactionStateInvalid', 'particularChange', 'Transaction state is not allowed for this area.', $data);
            }
    
            // Check if transaction settlement status correct.
            if ($tx['settlement_status'] != 'SETTLING') {
                throw new ProcessException('UnmatchedSettlementStatus', 'particularChange', 'Transaction is not allowed for this area.', $data);
            }
        } catch (ProcessException $exp) {
            $this->log($exp->type.' - :'.PHP_EOL.$exp->getMessage(), 'error');
            $this->dataResponse(['status'=>'error', 'type'=>$exp->type, 'msg'=>$exp->getMessage()]);
            return;
        } catch (\Exception $exp) {
            $this->log(''.PHP_EOL.$exp->getMessage(), 'error');
            $this->dataResponse(['status'=>'error', 'msg'=>$exp->getMessage()]);
            return;
        }

        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;

        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'settlementStateChange.lock';
        
        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }
        $this->log('Settlement state change locked.', 'debug');
        
        $this->log('Settlement batch processing...', 'debug');

        $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);

        $currentTime = new \DateTime();
        // prepare the list of update queries
        $this->connection-> transactional(function ($conn) use ($masterMerchant, $batchData, $tx, $user, $tokenFp, $tokenLockPath, $batchRow) {
            

            $entity = $this->TransactionLog->get($tx['id']);
            $entity = $this->TransactionLog->patchEntity($entity, ['settlement_status'=>'WITHHELD']);
            if (!$this->TransactionLog->save($entity)) {
                tryFileUnlock($tokenFp);
                @unlink($tokenLockPath);

                $response = [
                    'status'=>'error',
                    'type'=>'DatabaseError',
                    'msg'=>'Unable to change the transaction into new status.',
                ];
                $this->log($response, 'error');

                return $this->dataResponse($response);
            }
            

            // Reload the batch
            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);

            // Calcualte new batch data for database
            $newBatchRow = $batchData->toArray();

            $entity = $this->SettlementBatch->get($batchData->id);
            $entity = $this->SettlementBatch->patchEntity($entity, $newBatchRow);

            if (!$this->SettlementBatch->save($entity)) {
                tryFileUnlock($tokenFp);
                @unlink($tokenLockPath);

                $response = [
                    'status'=>'error',
                    'type'=>'DatabaseError',
                    'msg'=>'Unable to change the settlement batch into new status.',
                ];
                $this->log($response, 'error');

                return $this->dataResponse($response);
            }
        });
        
        // Reload the batch
        $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        
        $wallet = new MerchantWallet($batchData->masterMerchantId);
        $wallet->setUser($user['username']);

        
        $amount = $tx['tx_net_amount'] ;
        $converted_amount = $tx['converted_amount'] ;
        if ($amount > 0 && $converted_amount > 0) {
            $this->service->info(sprintf('Settlement batch %s withheld tx %s with amount %s %d, %d', $batchData->id, $tx['id'], $tx['tx_currency'], $amount, $converted_amount));
            $transactionLogDesc = 'Settlement batch '.$batchData->id;


            // Wallet Id Lookup
            $walletIdDefault  = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->defaultCurrency);
            $walletIdMerchant = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $batchData->merchantCurrency);

            // 5.4.6 vi) Add the transaction net amount to merchant primary settlement wallet by currency.
            if ($tx['tx_currency'] == $batchData->defaultCurrency) {
                $wallet->setWallet($walletIdDefault);
                $this->service->info(sprintf('Handling in wallet', $walletIdDefault));
                
                // It should be original tx net amount
                // Amount should be larger than zero for preventing "add values" for merchant
                $wallet->addTransaction($amount, MerchantWallet::TYPE_SETTLEMENT_ADJUSTMENT_TRANSACTION_WITHHELD, $transactionLogDesc);
            }

            if ($tx['tx_currency'] == $batchData->merchantCurrency && $batchData->defaultCurrency != $batchData->merchantCurrency) {
                $wallet->setWallet($walletIdMerchant);
                $this->service->info(sprintf('Handling in wallet', $walletIdMerchant));
                
                // Amount should be larger than zero for preventing "add values" for merchant
                $wallet->addTransaction($amount, MerchantWallet::TYPE_SETTLEMENT_ADJUSTMENT_TRANSACTION_WITHHELD, $transactionLogDesc);
            }

            // 5.4.6 vii) Deduct the transaction net amount to merchant primary settlement wallet (primary).
            $wallet->setWallet($walletIdDefault);
            $this->service->info(sprintf('Handling in wallet', $walletIdDefault));
            
            $transactionLogDesc = 'Transaction '.$tx['transaction_id'].' is withheld';
            // Deduce the settled amount in merchant currency.
            $wallet->addTransaction(- $amount, MerchantWallet::TYPE_SETTLEMENT_STATUS_UPDATED, $transactionLogDesc);
        }
        
        // 5.4.6 xi) Release settlement state change token.
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);
        
        $response = [
           'status'=>'done',
           'msg'=>'Success',
        //    'currentChecksumValue'=>$currentChecksumValue,
           'currentChecksum'=>$currentChecksum,
        //    'checksumValue'=>$batchData->getChecksumValue(),
           'checksum'=>$batchData->getChecksum(),
           'merchant_id'=>$batchData->masterMerchantId,
           'particulars'=> $batchData->particulars,
        ];
    
        // Send the response to client-side
        $this->dataResponse($response);
    }

    /**
     * Fetch merchant wallet info
     *
     * @param string $merchantId The master merchant id
     * @param string $format     The request format
     * 
     * @return void
     */
    public function fetchMerchantWalletInfo($merchantId, $format = 'json')
    {

        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new \Exception(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);

        $masterMerchant = $this->merchantService->getMasterMerchant($merchantId);


        $response = ['status'=>'done', 'msg'=>'Success'];

        // Create wallet instance for handling batch data change.
        $wallet = new MerchantWallet($masterMerchant['id']);

        $defaultCurrency = $this->defaultCurrency;
        $merchantCurrency = $masterMerchant['settle_currency'];

        $walletIdRemittanceDefault  = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_REMITTANCE, $defaultCurrency);
        $walletIdSettlementDefault  = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $defaultCurrency);
        $walletIdSettlementMerchant = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_SETTLEMENT, $merchantCurrency);
        $walletIdCarryForward = $wallet->getServiceCurrencyWallet(MerchantWallet::SERVICE_CARRYFORWARD, $merchantCurrency);

        $response['wallets']['remittance-'.$defaultCurrency] = [
            'id'=> $walletIdRemittanceDefault,
            'exist'=>$wallet->isWalletExist($walletIdRemittanceDefault),
        ];
        $response['wallets']['settlement-'.$defaultCurrency] = [
            'id'=> $walletIdSettlementDefault,
            'exist'=>$wallet->isWalletExist($walletIdSettlementDefault),
        ];
        $response['wallets']['settlement-'.$merchantCurrency] = [
            'id'=> $walletIdSettlementMerchant,
            'exist'=>$wallet->isWalletExist($walletIdSettlementMerchant),
        ];
        $response['wallets']['carryforward-'.$merchantCurrency] = [
            'id'=> $walletIdCarryForward,
            'exist'=>$wallet->isWalletExist($walletIdCarryForward),
        ];

        return $this->dataResponse($response);
    }

    /**
     * Return merchant's transaction list in json
     *
     * @param string $batchId Batch ID
     * @param string $format  Request format
     *
     * @return void
     */
    public function fetchBatchDetail($batchId = null, $format = 'json')
    {

        set_time_limit(0);

        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new \Exception(__('Unknown format.'));
        }
        $this->viewBuilder()->className($formats[ $format ]);

        if (empty($batchId)) {
            throw new \Exception(__('Unknown batch.'));
        }
            
        try {
            $batchRow = $this->SettlementBatch->get($batchId);

            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);

            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        } catch (\Exception $exp) {
            $this->Flash->error($exp->getMessage());
            return $this->redirect(['action' => 'index']);
        }

        // Verify if the calculated checksum is matched with database saved version.
        $currentChecksum = $batchData->getChecksum();
            
        if (empty($this->request->data['particular']) || !is_string($this->request->data['particular'])) {
            throw new \Exception(__('Unknown particular.'));
        }
        
        $particular = $this->request->data('particular');
        $particularTypesKeys = array_keys(BatchData::$particularTypes);
        $particularTypesKeys[] = 'batchRemittanceTx';
        if (!in_array($particular, $particularTypesKeys)) {
            throw new \Exception(__('Unsupported particular.'));
        }
        $currency = $this->request->data('currency');

        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;

        // For Batch Remittance Transaction, use RemittanceReportReader->getBatch() direcctly
        if ($particular == 'batchRemittanceTx') {
            $rBatchId = $this->request->data('rBatchId');
            if (empty($rBatchId)) {
                throw new \Exception(__('Missing rBatchId'));
            }
            $this->fetchBatchRemittanceTx($rBatchId, $masterMerchant['id']);
            return;
        }
        

        $querySet = $batchData->querySet;

        // If passing currency, we filter with it in all sales/refund transaction.
        // remittance must be in CNY.
        if (!empty($currency)) {
            $querySet->sales->where(['tx.CURRENCY'=> $currency]);
            $querySet->refund->where(['tx.CURRENCY'=> $currency]);
        }

        // A flag to control is it necessary to fetch data.
        $allowed = true;

        // Base response setting
        $response = [
           'status'=>'done',
           'msg'=>'Success',
           'total'=>0,
           'data'=>[],
        ];

        // Apply to sales transaction only
        // If `txid` exist in post/get parameter, filter the result with these transaction id.
        // if (isset($this->request->data['txid'])) {
        //     $txid = [];
        //     if (is_array($this->request->data['txid'])) {
        //         $txid = $this->request->data['txid'];
        //     } elseif (is_string($this->request->data['txid'])) {
        //         $txid = explode(',', trim(''.$this->request->data['txid']));
        //     }

        //     // If `txid` is empty, we reported them directly.
        //     if (empty($txid)) {
        //         $allowed = false;
        //     } else {
        //         $querySet->sales->where()->in('tx.id', $txid);
        //     }
        // }
        // if ($particular == 'sales') {
        //     if (empty($req_txid) || !is_array($req_txid)) {
        //             $allowed = false;
        //     } else {
        //         $querySet->sales->where()->in('tx.id', $req_txid);
        //     }
        // }
        
        if ($allowed) {
            $start_offset = 0;
            $page_size = -1;
            if (isset($this->request->data['page']) && isset($this->request->data['pageSize'])) {
                $page_size = intval($this->request->data['pageSize']);
                if ($page_size < 1) {
                    $page_size = 1;
                }
                $start_offset = (intval($this->request->data['page']) -1 ) * $page_size;
            }

            // // Getting the total number first.
            // $searchResult = $querySet->getResult($particular, null, $start_offset, $page_size);
            // $response['total'] = $searchResult->total;
            
            $searchResult = $querySet->getResult($particular, null, $start_offset, $page_size, function ($query) use ($particular) {

                $req_filter = null;
                if (!empty($this->request->data['filter']) && is_array($this->request->data['filter'])) {
                    $req_filter = $this->request->data['filter'];
                }
    
                $req_sort = null;
                if (!empty($this->request->data['sort']) && is_array($this->request->data['sort'])) {
                    $req_sort = $this->request->data['sort'];
                }

                $this->createGridDetailQuery($query, $particular, $req_filter, $req_sort);
            });
            $this->service->debug('SearchResult['.$particular.'].count='.$searchResult->total);
            $response['total'] = $searchResult->total;
            $response['data'] = $searchResult->data;
        }

        $this->dataResponse($response);
    }

    /**
     * Create a background job for export all settlement data into a excel file
     *
     * @param string $batchId Batch ID
     * @param string $format  Request format
     *
     * @return void
     */
    public function queueDownloadBatch($batchId, $format = 'json')
    {
        set_time_limit(0);
        
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
        
        
        $txid = [];
        $req_txid = isset($this->request->data['txid']) ? $this->request->data('txid') : null;
        if (is_string($req_txid)) {
            $txid = explode(',', trim($req_txid));
        } elseif (is_array($req_txid)) {
            $txid = $req_txid;
        }

        // Normalized
        $txid = implode(',', $txid);
        try {
            $batchRow = $this->SettlementBatch->get($batchId);

            $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);
            
            $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        } catch (\Exception $exp) {
            $this->Flash->error($exp->getMessage());
            return $this->redirect(['action' => 'index']);
        }

        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;

        $particulars = $this->request->data('particulars');

        $params = compact('particulars');


        $task_name = '\\App\\Tasks\\SettlementBatchPreviewExportTask';
        $queue_name = 'excelexport';
        $type = 'excelexport';
        $state = $batchRow['state'];

        $any_data = false;

        

        $job_data = compact('config', 'params', 'type', 'batchId', 'state');
        // $this->log(__METHOD__.': '. print_r($job_data, true));
        
        $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

        $this->log("Added Queue Task for {$task_name}. JobID={$job_id}", 'info');

        return $this->dataResponse(['status'=>'added','id'=>$job_id]);
    }
    
    /**
     * Handle an action for set the batch into settled state.
     *
     * @param string $batchId Batch id for the action
     * @param string $format  Request format
     *
     * @return void
     */
    public function particularChange($batchId = null, $format = 'json')
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
        
        $batchRow = $this->SettlementBatch->get($batchId);

        $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);

        $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        
        // This action is allowed for batch state in OPEN.
        if ($batchData->state != 'OPEN') {
            $response = [
                'status'=>'error',
                'type'=>'UnmatchedSettlementStatus',
                'msg'=>'Settlement batch is not allowed to change.',
            ];
            $this->log($response, 'error');

            return $this->dataResponse($response);
        }

        // Verify if the calculated checksum is matched with database saved version.
        $currentChecksum = $batchData->getChecksum();
        if ($currentChecksum != $batchRow['editable_checksum']) {
            $response = [
                'status'=>'error',
                'type'=>'InvalidInternalChecksum',
                '_cv'=>$batchData->getChecksumValue(),
                'msg'=>'Internal error - database checksum does not match with runtime calculated.',
            ];
            $this->log($response, 'error');

            return $this->dataResponse($response);
        }
        
        // Verify if the submitted checksum is matched with database version.
        // It is required for changing batch data.
        $checksum = $this->request->data('checksum');
        if ($batchData->getChecksum() != $checksum) {
            $response = [
                'status'=>'error',
                'type'=>'UnmatchedChecksum',
                'msg'=>'Submitted checksum value does not match with saved version.',
            ];
            $this->log($response, 'error');
            return $this->dataResponse($response);
        }
        
        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;

        $column = $this->request->data('column');
        $newParticulars = $this->request->data('particulars');

        
        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'SettlementBatchStateChange.lock';
        
        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error', 'type'=>'CannotCreateToken']);
            return;
        }
        $this->log('Settlement token locked.', 'debug');

        try {
            $this->service->batchProcessor->change($batchData, $user, $newParticulars, $column);

            $response = [
                'status'=>'done',
                'msg'=>'Batch updated.',
                'checksum'=>$batchData->getChecksum(),
                'id'=>$batchData->id,
            ];
        } catch (ProcessException $exp) {
            $response = ['status'=>'error', 'type'=>$exp->type, 'msg'=>$exp->message];
        }

        // Unlock path
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);

        $this->log('Settlement token unlocked.', 'debug');

        $this->log($response, 'debug');

        return $this->dataResponse($response);
    }

    /**
     * Handle an action for set the batch into settled state.
     *
     * @param string $batchId Batch id for the action
     * @param string $format  Request format
     *
     * @return void
     */
    public function complete($batchId = null, $format = 'json')
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
        
        $batchRow = $this->SettlementBatch->get($batchId);
        $masterMerchant = $this->merchantService->getMasterMerchant($batchRow['merchant_id']);
        $batchData = $this->service->batchBuilder->load($batchRow, $masterMerchant);
        
        // This action is allowed for batch state in OPEN.
        if ($batchData->state != 'OPEN') {
            $response = [
                'status'=>'error',
                'type'=>'UnmatchedSettlementStatus',
                'msg'=>'Settlement batch is not allowed to change.',
            ];
            $this->log($response, 'error');

            return $this->dataResponse($response);
        }

        // Verify if the calculated checksum is matched with database saved version.
        $currentChecksum = $batchData->getChecksum();
        if ($currentChecksum != $batchRow['editable_checksum']) {
            $response = [
                'status'=>'error',
                'type'=>'InvalidInternalChecksum',
                'msg'=>'Internal error - database checksum does not match with runtime calculated.',
            ];
            $this->log($response, 'error');

            return $this->dataResponse($response);
        }
        
        // Verify if the submitted checksum is matched with database version.
        // It is required for changing batch data.
        $checksum = $this->request->data('checksum');
        if ($batchData->getChecksum() != $checksum) {
            $response = [
                'status'=>'error',
                'type'=>'UnmatchedChecksum',
                'msg'=>'Submitted checksum value does not match with saved version.',
            ];
            $this->log($response, 'error');
            return $this->dataResponse($response);
        }
        
        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;


        // Step 1 - Create single token. if any exist token found, stop here.
        $tokenLockPath = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'SettlementBatchStateChange.lock';
        
        // Lock the state for preventing other process
        $tokenFp = tryFileLock($tokenLockPath);
        if (!$tokenFp) {
            $this->dataResponse(['status'=>'error','type'=>'CannotCreateToken']);
            return;
        }
        $this->log('Settlement token locked.', 'debug');

        try {
            $this->service->batchProcessor->complete($batchData, $user);

            $response = [
                'status'=>'done',
                'msg'=>'Batch settled.',
                'id'=>$batchData->id,
            ];
            $this->log($response, 'debug');
        } catch (ProcessException $exp) {
            $response = [
                'status'=>'error',
                'type'=>$exp->type,
                'msg'=>$exp->message,
            ];
            if ($exp->data != null) {
                $response['data'] = $exp->data;
            }
            $this->log($response, 'error');
        } catch (\Exception $exp) {
            $response = [
                'status'=>'error',
                'type'=>'Exception',
                'exception'=>get_class($exp),
                'msg'=>$exp->getMessage(),
            ];
            $this->log($response, 'error');
        }
        
        // Unlock path
        tryFileUnlock($tokenFp);
        @unlink($tokenLockPath);

        $this->log('Settlement token unlocked.', 'debug');


        return $this->dataResponse($response);
    }

    /**
     * Handle an action for resend the batch details to email inbox
     *
     * @param string $batchId Batch id for the action
     *
     * @return void
     */
    public function resend($batchId = null)
    {
        $type = $this->request->data('type');
        $requestedRecipients = [];
        if ($type == 'adhoc') {
            $requestedRecipients = explode(',', trim($this->request->data('email')));
        }

        $validRecipients = [];
        foreach ($requestedRecipients as $email) {
            // TODO: Verify email address
            $validRecipients[] = $email;
        }

        if (count($validRecipients)> 0) {
        }
    }

    protected function createGridDetailQuery($query, $particular = null, $req_filter = null, $req_sort = null)
    {

        if (!empty($req_sort) && is_array($req_sort) && !empty($this->sortableFields[$particular])) {
            $query->setOrderBy([]);
            foreach ($req_sort as $sort_info) {
                // Only listed fields could be sorted.
                if (in_array($sort_info['field'], $this->sortableFields[$particular])) {
                    $query->orderBy($sort_info['field'], $sort_info['dir']);
                }
            }
        }

        if (!empty($req_filter)) {
            $whereClause = $query->where();
            $filter = null;

            $rootClause = $whereClause->group();

            // Reformatting for 'sales' and 'refund' data filtering.
            if ($particular == 'sales' || $particular == 'refund') {
                $filter = [
                    'logic'=>$req_filter['logic'],
                    'filters'=>[],
                ];

                $grouped = [];
                foreach ($req_filter['filters'] as $filter_info) {
                    // Some of the requested fields are alias by select statement.
                    // We need to change the filter by adding custom where case directly

                    if ($filter_info['field'] == 'state_date' || $filter_info['field'] == 'reconciled_state_time') {
                        if (!isset($grouped ['state_date'])) {
                            $grouped['state_date'] = ['field'=>'reconciled_state_time', 'logic'=>$req_filter['logic'], 'type'=>'date', 'values'=>[]];
                        }
                        $group_info = &$grouped['state_date'];

                        $group_info['values'][] = $filter_info['value'];
                    } else {
                        $filter['filters'][] = $filter_info;
                    }
                }

                if (!empty($grouped)) {
                    $parent = $rootClause->group();
                    foreach ($grouped as $group_info) {
                        // If type is normal string comparing, use IN
                        if ($group_info['type'] == 'text') {
                            $parent->in($group_info['field'], $group_info['values']);
                        
                        // If type is date, we setup the date range by each selected date.
                        } elseif ($group_info['type'] == 'date') {
                            $parent = $parent->group();
                            foreach ($group_info['values'] as $index => $val) {
                                if ($group_info['logic'] == 'or' && $index > 0) {
                                    $parent = $rootClause->or();
                                }

                                $next_date = (new \DateTime($val))
                                    ->add(new \DateInterval('P1D'))
                                    ->format('Y-m-d');
                                $parent
                                    ->greaterThanOrEqual($group_info['field'], $val.' 00:00:00')
                                    ->and()
                                    ->lessThan($group_info['field'], $next_date.' 00:00:00');
                            }
                        }
                    }
                }
            } else {
                $filter = $req_filter;
            }

            // $this->log('Query with filter: '. print_r($filter, true), 'debug');
            
            // If anything else,
            if (!empty($filter['filters'])) {
                QueryHelper::handleFiltering($filter, null, $whereClause);
            }
        }

        // Log::write('debug', __METHOD__.'@'.__LINE__.', GridDetail['.$particular.'] Query'.PHP_EOL.'sql: '.$this->service->getDb()->toSql($query));
    }
    
    /**
     * Remove a cached batch data
     *
     * @param string $checksum The checksum of a BatchData
     * 
     * @return void
     */
    protected function removeBatchCache($checksum)
    {
        $cacheKey = $this->getBatchCacheKey($checksum);
        Cache::delete($cacheKey);
    }

    /**
     * Return the key of BatchData by a checksum.
     *
     * @param string $checksum The checksum of a BatchData
     * 
     * @return string
     */
    protected function getBatchCacheKey($checksum)
    {
        return $cacheKey = 'settlement_batch_cached_'.$checksum;
    }

    /**
     * Handling a cached BatchData during batch create process
     *
     * @param array  $masterMerchant The master merchant data row.
     * @param array  $params         The parameters during create batch data from db.
     * @param string $checksum       The checksum of a BatchData
     * @param bool   $changed        The flag of change status to control cache behavior during loading.
     * 
     * @return BatchData
     */
    protected function createBatchWithCache($masterMerchant, $params, $checksum = null, $changed = false)
    {

        $needCache = !isset($params['no_cache']) || $params['no_cache']!=true;

        $this->service->debug('Changed? '.($changed ? 'Yes': 'No'));
        $cached = null;
        $cacheKey = null;
        if (!empty($checksum) && !$changed) {
            $cacheKey = $this->getBatchCacheKey($checksum);


            // If the client is asking for change the local cache
            // Ignore the cache
            if ($needCache) {
                $this->service->debug('Using cachekey '.$cacheKey);
                try {
                    $cacheText = Cache::read($cacheKey);

                    if (!empty($cacheText)) {
                        $this->service->debug('Has content when using cache '.$cacheKey);
                        $cached = json_decode($cacheText, true);
                    } else {
                        $this->service->debug('No content when using cache '.$cacheKey);
                    }
                } catch (\Exception $exp) {
                    $this->service->debug('Problem when using cache '.$exp->getMessage());
                }
                if (empty($cached['merchant_id']) || $cached['merchant_id'] != $masterMerchant['id']) {
                    $this->service->debug('Cached data is not matched for merchant id: '. print_r($cached, true));
                    $cached = null;
                }
            }
        }
        $batchData = $this->service->batchBuilder->create($masterMerchant, $params, $cached);

        // Replace cached value
        if ((empty($cached) || $checksum != $batchData->getChecksum()) && $needCache) {

            
            if (!empty($cacheKey) && $changed) {
                Cache::delete($cacheKey);
                $this->service->debug('Removed cache with key '.$cacheKey);
            }
            if ($changed || empty($checksum)) {
                $checksum = $batchData->getChecksum();
            }
            if (empty($cached)) {
                $cached = [
                    'merchant_id'=>$masterMerchant['id'],
                    'txid'=> $batchData->txid,
                    'summaryResult'=> $batchData->summaryResult->toArray(),
                ];
                $cacheKey = $this->getBatchCacheKey($checksum);

                $cacheText = json_encode($cached);
                $this->service->debug('Begin of writing cache with key '.$cacheKey);
                if (!Cache::write($cacheKey, $cacheText)) {
                    $this->service->debug('Error when writing cache with key '.$cacheKey);
                } else {
                    $this->service->debug('End of writing cache with key '.$cacheKey);
                }
            }
        }

        return $batchData;
    }
}
