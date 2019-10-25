<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Network\Exception\NotFoundException;
use Cake\View\Exception\MissingTemplateException;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Routing\Router;

use App\Lib\CakeDbConnector;
use App\Lib\JobMetaHelper;
use App\Lib\CakeLogger;

/**
 * TransactionLog Controller
 *
 * @property \App\Model\Table\TransactionLogTable $TransactionLog
 */
class TransactionLogController extends AppController
{

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

        // Initialize
        $this->connection = ConnectionManager::get('default');

        $logger = CakeLogger::shared();
        
        // Preparing database adapter to QueryHelper.
        CakeDbConnector::setShared($this->connection);

    }

    public function beforeFilter(Event $event)
    {
        $this->Auth->allow('index');
    }

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        /*
        $this->paginate = [
        'contain' => ['Merchants', 'Internals', 'States']
        ];
        $transactionLog = $this->paginate($this->TransactionLog);

        $this->set(compact('transactionLog'));
        $this->set('_serialize', ['transactionLog']);
        */
    }

    /*
     * json feed of missing db records
     */
    public function missingRecordJson()
    {
        $this->log($this->request->params, 'debug');
        $this->RequestHandler->ext = 'json';
        if (! $this->request->is('ajax')) {
            return null;
        }

        $sql = "SELECT max(id) as id, date(STATE_TIME) as start, date(STATE_TIME) as end, concat (count(*),' missing') as title, 1 as source FROM `transaction_log`
where internal_id is null group by start order by start; ";

        $connection = ConnectionManager::get('default');
        $results = $connection->execute($sql)->fetchAll('assoc');
        foreach ($results as $k => $v) {
            $results[$k]['isallday']=true;
        }

        $sql2="SELECT max(internal_id)+99999999 as id, date(STATE_TIME) as start, date(STATE_TIME) as end, concat (count(*),' missing') as title, 2 as source FROM `transaction_log` t left join gpay_transaction_log g 
on (t.internal_id=g.merchant_order_no) 
 left join ght_transaction_log ght on (t.transaction_id=ght.transaction_id)
where internal_id is not null and g.id is null
AND ght.transaction_id is null 
group by start order by start ";
        $res2 = $connection->execute($sql2)->fetchAll('assoc');
        foreach ($res2 as $k => $v) {
            $res2[$k]['isallday']=true;
        }

        // compare difference between payconn & gpay
        $sql3="select t.*, g.count as g_count, g.sum as g_sum, ght.count as ght_count, ght.sum as ght_sum from 
( SELECT count(*) as count, round(sum(amount),2) as sum, date(transaction_time) as date FROM `transaction_log` 
where state='SALE' group by date )  t  
left join 
 (
SELECT count(*) as count, round(sum(amount),2) as sum, date(transaction_time) as date FROM `gpay_transaction_log` 
where type like '网银(Online banking)' group by date  )  g 
on (t.date=g.date)
left join (
SELECT count(*) as count, round(sum(amount),2) as sum, date(transaction_time) as date FROM `ght_transaction_log` 
where upper(status) in ('支付成功','SALE') group by date 
) ght 
on (t.date=ght.date) 
where g.date is not null
and (t.sum != g.sum OR t.count != g.count)
and (t.sum != (g.sum + coalesce(ght.sum,0)) OR t.count != (g.count + coalesce(ght.count,0)))  
order by t.date desc
;";

        $res3 = $connection->execute($sql3)->fetchAll('assoc');
        foreach ($res3 as $k => $r) {
            $res3[$k]['id'] = uniqid();
            $res3[$k]['start'] = $res3[$k]['end'] = $r['date'];
            $res3[$k]['source'] = 3;
            $res3[$k]['isallday'] = true;
            $res3[$k]['title'] = sprintf("PayConnector {$r['sum']} ({$r['count']}) VS GPay {$r['g_sum']} ({$r['g_count']}) + GHT %01.2f (%d)", $r['ght_sum']+0, $r['ght_count']+0);
        }
        $results = array_merge($results, $res2, $res3);
        /*
        $results = array_merge($results, $res2);
        */
        //$this->log($results, 'debug');
        $this->set('data', $results);
        $this->set('_serialize', 'data');
    }

    /**
     * View method
     *
     * @param string|null $id Transaction Log id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $transactionLog = $this->TransactionLog->get($id, [
        'contain' => ['Merchants', 'Internals', 'States']
        ]);

        $this->set('transactionLog', $transactionLog);
        $this->set('_serialize', ['transactionLog']);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $transactionLog = $this->TransactionLog->newEntity();
        if ($this->request->is('post')) {
            $transactionLog = $this->TransactionLog->patchEntity($transactionLog, $this->request->data);
            if ($this->TransactionLog->save($transactionLog)) {
                $this->Flash->success(__('The transaction log has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The transaction log could not be saved. Please, try again.'));
            }
        }
        $merchants = $this->TransactionLog->Merchants->find('list', ['limit' => 200]);
        $internals = $this->TransactionLog->Internals->find('list', ['limit' => 200]);
        $states = $this->TransactionLog->States->find('list', ['limit' => 200]);
        $this->set(compact('transactionLog', 'merchants', 'internals', 'states'));
        $this->set('_serialize', ['transactionLog']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Transaction Log id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $transactionLog = $this->TransactionLog->get($id, [
        'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $transactionLog = $this->TransactionLog->patchEntity($transactionLog, $this->request->data);
            if ($this->TransactionLog->save($transactionLog)) {
                $this->Flash->success(__('The transaction log has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The transaction log could not be saved. Please, try again.'));
            }
        }
        $merchants = $this->TransactionLog->Merchants->find('list', ['limit' => 200]);
        $internals = $this->TransactionLog->Internals->find('list', ['limit' => 200]);
        $states = $this->TransactionLog->States->find('list', ['limit' => 200]);
        $this->set(compact('transactionLog', 'merchants', 'internals', 'states'));
        $this->set('_serialize', ['transactionLog']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Transaction Log id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        /*
        $this->request->allowMethod(['post', 'delete']);
        $transactionLog = $this->TransactionLog->get($id);
        if ($this->TransactionLog->delete($transactionLog)) {
        $this->Flash->success(__('The transaction log has been deleted.'));
        } else {
        $this->Flash->error(__('The transaction log could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
        */
    }

    public function display()
    {
    }

    public function dateform()
    {

        $task_name = '\\App\\Tasks\\TransactionLogExportTask';
        $queue_name = 'excelexport';

        if ($this->request->is('post')) {
            $this->log($this->request->data, 'info');
            //check date range
            $date = $this->request->data['startdate'];
            $startdate = $date; //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);
            $date = $this->request->data['enddate'];
            $enddate = $date;   //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);

            if (($start=strtotime($startdate))==false || ($end=strtotime($enddate))==false) {
                return $this->dataResponse(['status'=>'error','error'=>['message'=>__('Invalid Date.')]]);
            }
            if ($start>$end) {
                return $this->dataResponse(['status'=>'error','error'=>['message'=>__('Start Date should be earlier than End Date.')]]);
            }


            $basepath = TMP.'/xls/';

            $startdate_str = str_replace(['/',' '], '-', $startdate);
            $enddate_str = str_replace(['/',' '], '-', $enddate);

            $xlsfile= sprintf('%stransaction_%sto%s_%s', $basepath, $startdate_str, $enddate_str, time());


            $user = $this->Auth->user();
            $type = 'excelexport';
        
            $job_data = array(
                'startdate' => $startdate,
                'enddate' => $enddate,
                'mid' => '',
                'status' => null,
                'xlsfile' => $xlsfile,
                'user'=>$user,
                'type'=>$type,
            );

            // $job = $this->QueuedJobs->createJob('TransactionLogExport', $job_data);
            // $job_id = $job->id;

            $job_id = JobMetaHelper::add($task_name, $job_data, $queue_name);

            $this->log("Added Queue Task for TransactionLogExport ($startdate to $enddate). JobID={$job_id}", 'info');

            return $this->dataResponse(['status'=>'added','id'=>$job_id]);
        } else {
            $this->display();
        }
    }

    public function dateformDownload()
    {
        if ($this->request->is('post')) {
            set_time_limit(600);
            
            $this->log($this->request->data, 'info');
            //check date range
            $date = $this->request->data['startdate'];
            $startdate = $date; //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);
            $date = $this->request->data['enddate'];
            $enddate = $date;   //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);

            if (($start=strtotime($startdate))==false || ($end=strtotime($enddate))==false) {
                $this->Flash->error(__('Invalid Date.'));
                return $this->redirect(['action' => 'dateform']);
            }
            if ($start>$end) {
                $this->Flash->error('Start Date should be earlier than End Date.');
                return $this->redirect(['action' => 'dateform']);
            }

            $basepath = Configure::read('WC.data_path');
            $this->log("$startdate to $enddate", 'info');


            $callback_name = isset($this->request->data['callback']) ? $this->request->data['callback'] : null;
            $is_callback = !empty($callback_name) ;

            if ($is_callback) {
                $this->autoRender = false;
                echo "<p>Starting</p>";
                echo "<script>parent.{$callback_name}(".json_encode(['status'=>'start',]).")</script>".PHP_EOL.PHP_EOL;

                // ob_flush();
                flush();
            }

            try {
                $pc_api = new \PayConnectorAPI(false);
                // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

                // Setup paging request to reduce memory usage.
                $offset = 0;
                $limit = 10000;


                $startdate_str = str_replace(['/',' '], '-', $startdate);
                $enddate_str = str_replace(['/',' '], '-', $enddate);
                $xlsfile= sprintf('%stransaction_%sto%s_%s', $basepath, $startdate_str, $enddate_str, time());

                // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                $num_loop = 0;

                do {
                    $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                    $data = $pc_api->getDatabaseTransactions($startdate, $enddate, $mid = '', $status = null, $limit, $offset);
                    if (!is_array($data) || count($data)<1) {
                        if ($offset == 0) {
                            if ($is_callback) {
                                echo "<p>Error: "."No data within the period ($startdate to $enddate)."."</p>";
                                echo "<script>parent.{$callback_name}(".json_encode(['status'=>'error','message'=>"No data within the period ($startdate to $enddate)."]).")</script>".PHP_EOL.PHP_EOL;
                                return null;
                            } else {
                                $this->Flash->error(__('Error to write file.'));
                                return $this->response;
                            }
                        } else {
                            break;
                        }
                    }

                    if ($is_callback) {
                        echo "<p>Progress... ($offset)</p>";
                        echo "<script>parent.{$callback_name}(".json_encode(['status'=>'progress', 'offset'=> $offset + count($data)]).")</script>".PHP_EOL.PHP_EOL;

                        // ob_flush();
                        flush();
                    }


                    $this->log("Offset[$offset/$limit] count:".count($data), 'info');
                    /*
                    if (count($data)>10000) {
                        throw new \Exception("Too many records.");
                    }
        */
                    //$output = $pc_api->saveToExcel($data, $xlsfile);
                    $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

                    wcSetNumberFormat($data);
                    $output = $pc_api->saveToExcel2($data, $xlsfile, $num_loop> 0 || count($data) >= 10000 ? '.csv':'.xlsx');
                    // $output = $pc_api->saveToExcel2($data, $xlsfile, '.xlsx');

                    $offset+= $limit;
                    $num_loop ++;
                    $this->log(__METHOD__.'@'.__LINE__.': output = '.$output, 'debug');
                    // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                } while (!empty($data));
               
                $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
        
                if (!is_readable($output)) {
                    $this->log("Error to write: $output", 'debug');


                    if ($is_callback) {
                        echo "<p>Error: ".__('Error to write file.')."</p>";
                        echo "<script>parent.{$callback_name}(".json_encode(['status'=>'error','message'=>__('Error to write file.')]).")</script>".PHP_EOL.PHP_EOL;
                        return null;
                    } else {
                        $this->Flash->error(__('Error to write file.'));
                        return $this->response;
                    }
                } else {
                    $this->log("uname:".$this->Auth->user('username'), 'debug');
                    $this->log("output file: $output", 'debug');
                    //$this->Flash->success('We will get back to you soon.'. $output);

                    //$this->response->body($output);

                    $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                    $this->log(__METHOD__.'@'.__LINE__.':'."Peak memory usage: ".((memory_get_peak_usage() / 1024 / 1024)<<0).'MB', 'debug');

                    
                    $output_name = basename($output);

                    
                    if ($is_callback) {
                        $xlsurl = Router::url(['action' => 'serveFile', $output_name]);

                        echo "<p>Ready for download</p>";
                        echo "<script>parent.{$callback_name}(".json_encode(['status'=>'done','url'=>$xlsurl,'download' => true, 'name' => $output_name]).")</script>".PHP_EOL.PHP_EOL;
                        return null;
                    } else {
                        $this->response->disableCache();
                        $this->response->modified('now');
                        $this->response->checkNotModified($this->request);
                        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        $this->response->type($mime);
                        // Optionally force file download
                        // $this->response->download($output_name);
                        $this->response->file(
                            $output,
                            ['download' => true, 'name' => $output_name]
                        );
                        // Return response object to prevent controller from trying to render a view.
                        return $this->response;
                    }
                }
            } catch (\Exception $e) {
                $this->Flash->error("Error : ".$e->getMessage());
                //return FALSE;
            }
        } else {
            $this->display();
        }
    }

    /**
     * Serve file in tmp folder
     *
     * @param  string $f File path
     * @return Resposne The response object
     */
    public function serveFile($f)
    {
        $path = Configure::read('WC.data_path').$f;
        // $path = TMP.$f;
        $this->log("serveFile($path)", 'debug');

        if (!is_readable($path)) {
            $this->response->body(null);
        } else {
            $this->response->file($path, ['download' => true, 'name' => basename($f)]);
        }
        return $this->response;
    }


    //to replace old dateform
    public function dateform2()
    {
        $startdate = $this->request->query('startdate');
        $enddate = $this->request->query('enddate') ;

        $this->log("dateform2(): $startdate to $enddate", 'info');

        //if (!$this->request->is('post'))
        if (empty($startdate) || empty($enddate)) {
            $this->display();
            return;
        }
        ini_set('memory_limit', '256M');


        $this->log($this->request->data, 'info');
        //check date range
        /*
        $date = $this->request->data['startdate'];
        $startdate = $date;    //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);
        $date = $this->request->data['enddate'];
        $enddate = $date;    //sprintf('%d-%d-%d', $date['year'], $date['month'], $date['day']);
*/
        if (($start = strtotime($startdate)) == false || ($end = strtotime($enddate)) == false) {
            $this->Flash->error(__('Invalid Date.'));
            return $this->redirect(['action' => 'dateform']);
        }
        if ($start > $end) {
            $this->Flash->error('Start Date should be earlier than End Date.');
            return $this->redirect(['action' => 'dateform']);
        }

        // prepare to serve excel file
        $basepath = Configure::read('WC.data_path');


        try {
            // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
            $pc_api = new \PayConnectorAPI(false);
            $data = $pc_api->getDatabaseTransactions($startdate, $enddate, $mid = '', $status = null);
            if (!is_array($data) || count($data) < 1) {
                throw new \Exception("No data within the period ($startdate to $enddate).");
                // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
            }

            $xdata['sheets']['Transactions'] = $data;
            $sheet_title = str_replace(['/','\\'], '', "Summary ($startdate to $enddate)");
            $xdata['sheets'][$sheet_title] = $pc_api->getDatabaseTransactionSummary($startdate, $enddate);

            $startdate = str_replace(['/', ' '], '-', $startdate);
            $enddate = str_replace(['/', ' '], '-', $enddate);
            $xlsfile = sprintf('%s/transaction_%sto%s_%s', $basepath, $startdate, $enddate, time());
            //$output = $pc_api->saveToExcel($data, $xlsfile);
            // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
            $output = $pc_api->saveToExcel2($xdata, $xlsfile);
            $this->log("saveToExcel2 $output", 'debug');
            // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');

            if (!is_readable($output)) {
                $this->log("Error to write: $output", 'debug');
                $this->Flash->error(__('Error to write file.'));
            } else {
                $this->log("uname:" . $this->Auth->user('username'), 'debug');
                $this->log("output file: $output", 'debug');
                //$this->Flash->success('We will get back to you soon.'. $output);
                $this->response->disableCache();
                $this->response->modified('now');
                $this->response->checkNotModified($this->request);

                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $output_name = basename($output);
                $this->response->type($mime);

                // Optionally force file download
                //$this->response->download($output_name);
                $this->response->file(
                    $output,
                    ['download' => true, 'name' => $output_name]
                );

                $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
                $this->log("Peak memory usage: ".((memory_get_peak_usage() / 1024 / 1024)<<0).'MB', 'debug');

                // Return response object to prevent controller from trying to render a view.
                return $this->response;
            }
        } catch (\Exception $e) {
            $this->Flash->error("Error : " . $e->getMessage());
            //return FALSE;
        }
    }

    public function transactionJson($req = '')
    {
        $this->log($this->request->query, 'debug');

        $this->RequestHandler->ext = 'json';
        if (!$this->request->is('ajax')) {
            return null;
        }

        if (isset($this->request->query['filter']['filters'])) {
            foreach ($this->request->query['filter']['filters'] as $filter) {
                switch ($filter['field']) {
                    case 'start':
                        $startdate = $filter['value'];
                        break;
                    case 'end':
                        $enddate = $filter['value'];
                        break;
                }
            }
        }
        if (empty($startdate)) {
            $startdate = date('Y-m-d', strtotime('-2 day'));
        }
        if (empty($enddate)) {
            $enddate = $startdate;
        }

        $this->log("$startdate to $enddate", 'debug');

        $offset = (isset($this->request->query['skip'])?$this->request->query['skip']:0);
        $limit = (isset($this->request->query['pageSize'])?$this->request->query['pageSize']:10);

        $pc_api = new \PayConnectorAPI(false);

        if ($req=='summary') {
            $data = $pc_api->getDatabaseTransactionSummary($startdate, $enddate, $mid = '');
        } else {
            $data = $pc_api->getDatabaseTransactions($startdate, $enddate, $mid = '', $status = null);  //, 10, 5);
        }

        if (is_array($data)) {
            $slice = array_slice($data, $offset, $limit);
        }

        //$this->log($sums, 'debug');

        $this->set('data', $slice);
        $this->set('total', count($data));
     //   $this->set('_serialize', 'data');
    }

    public function handleUploadFile($file, $extension)
    {
        if (empty($file['name'])) {
            return false;
        }
        $ext = substr(strtolower(strrchr($file['name'], '.')), 1); //get the extension
        if (!in_array($ext, $extension)) {
            return false;
        }
        $basepath = ROOT.DS.'tmp/xls/';
        $newFileName = sprintf("%s_%s_%s.%s", 'txlog', date('YmdHis'), md5_file($file['tmp_name']), $ext);
        $path = $basepath.$newFileName;
        $this->log('handleUploadFile:'."{$file['tmp_name']} \r\n to $path", 'info');
        move_uploaded_file($file['tmp_name'], $path);

        return $path;
    }

    public function upload()
    {
        if (!$this->request->is('post')) {
            return null;
        }

        $this->log($this->request->data, 'info');
        $pcfile = $this->request->data['pcfile'];
        $gpfile = $this->request->data['gpfile'];
        $ghfile = $this->request->data['ghfile'];
        $good_exts = ['xls', 'xlsx', 'csv'];

        $pc_api = new \PayConnectorAPI(false);
        if (($path1=$this->handleUploadFile($pcfile, $good_exts))!=false) {
            $json1 = $pc_api->handleExcelFile($path1, 'payconn');
            unlink($path1);

            if (!isset($json1['file']) || empty($json1['file'])) {
                //$this->Flash->success("{$pcfile['name']}: {$json1['msg']}");
            } else {
                //show download link
                $url =  Router::url(['controller' => 'RemittanceBatch', 'action' => 'serveStaticFile', $json1['file'], '_full' => true]);
                $this->set('download_url', $url);
                $this->log("download_url: $url", 'info');
            }
            $this->Flash->success("{$pcfile['name']}: {$json1['msg']}");
        } elseif (!empty($pcfile['name'])) {
            $this->Flash->error("{$pcfile['name']} NOT supported");
        }

        if (($path2=$this->handleUploadFile($gpfile, $good_exts))!=false) {
            $json2 = $pc_api->handleExcelFile($path2, 'gpay');
            unlink($path2);
            $this->Flash->success("{$gpfile['name']}: {$json2['msg']}");
        } elseif (!empty($gpfile['name'])) {
            $this->Flash->error("{$gpfile['name']} NOT supported");
        }

        if (($path3=$this->handleUploadFile($ghfile, $good_exts))!=false) {
            $json3 = $pc_api->handleExcelFile($path3, 'ght');
            unlink($path3);
            $this->Flash->success("{$ghfile['name']}: {$json3['msg']}");
        } elseif (!empty($ghfile['name'])) {
            $this->Flash->error("{$ghfile['name']} NOT supported");
        }
    }
}
