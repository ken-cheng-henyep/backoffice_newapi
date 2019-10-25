<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

/**
 * Requests Controller
 *
 * @property \App\Model\Table\RequestsTable $Requests
 */
class CivsReportController extends AppController
{
    const WECOLLECT_HK_IP = '210.177.209.157';
    public $excelFolder;
    public $timezoneDiff;

    public function beforeFilter(Event $event)
    {
        $this->log("CivsReportController beforeFilter", 'debug');
        $user = $this->Auth->user();
        $this->log($user['id'], 'debug');
        //$this->log($event, 'debug');

        $this->excelFolder = ROOT . '/tmp/xls/';

        $default_tz = date_default_timezone_get();
        $tz = date('Z');
        date_default_timezone_set('Asia/Hong_Kong');
        $tz2 = date('Z');
        date_default_timezone_set($default_tz);
        $this->timezoneDiff = $tz2-$tz;
    }

    public function userJson()
    {
        $conn = ConnectionManager::get('wcdb');
        $table = TableRegistry::get('Users', ['connection'=>$conn]);
        // Turn query logging on.
        /*
        $conn->logQueries(true);
        $users = $conn->newQuery()
            //->select('*')
            ->select(['id','username'])
            ->from('users')
            ->order(['username' => 'ASC'])
            ->execute()
            ->fetchAll('assoc');
            //->toArray();
        */
        $users = $table->find()
            ->select(['id','username'])
            ->order(['username' => 'ASC'])
            ->toArray();

        $this->RequestHandler->ext = 'json';
        //$this->log($users, 'debug');
        $this->set('user', $users);
        $this->set('_serialize', 'user');
    }
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        $this->paginate = [
            'contain' => ['Users']
        ];
        $requests = $this->paginate($this->Requests);

        $this->set(compact('requests'));
        $this->set('_serialize', ['requests']);
    }

    /**
     * View method
     *
     * @param string|null $id Request id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $request = $this->Requests->get($id, [
            'contain' => ['Users', 'Params']
        ]);

        $this->set('request', $request);
        $this->set('_serialize', ['request']);
    }

    public function olddateform() {
        //todo: checkAuth
        /*
        $usrs = TableRegistry::get('Users');
        $query = $usrs->find('list',[
            'keyField' => 'id',
            'valueField' => 'username'
        ])
            ->order(['username' => 'ASC']);
        $this->set('usr_lst', $query->toArray());
        */

        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');
            /*
            $subs = TableRegistry::get('Subscriptions');
            $query2 = $subs->find('all')
                ->contain(['Services'])
                ->order(['Subscriptions.code' => 'ASC']);
*/
            $query = $this->Requests->find('all')
                ->contain(['Users'])
                ->order(['Requests.datetime' => 'DESC']);

            if (!empty($this->request->data['user']))
                $query->where(['Requests.user_id =' => $this->request->data['user']]);
            if (!empty($this->request->data['startdate']))
                $query->where(['Requests.datetime >=' => strtotime($this->request->data['startdate'])]);
            if (!empty($this->request->data['enddate']))
                $query->where(['Requests.datetime <=' => strtotime($this->request->data['enddate'])]);

            $query2 = $query->map(function ($row) { // map() is a collection method, it executes the query
                $row->trimmedUrl = trim($row->resource,' /');
                return $row;
            });

            //$this->log($query,'debug');
            //$this->log($query->count(),'debug');
            $data = array();
            $data[] = ['id', 'username', 'date', 'ip', 'resource', 'trimmedUrl'];
            foreach ($query2 as $q) {
                $data[] = [$q->id, $q->user->username, $q->date, $q->ip, $q->resource, $q->trimmedUrl];
            }
            $file = $this->saveExcel($data);
            if ($file)
                return $this->serveExcel($file);
        }
    }

    public function dateform() {
        //todo: checkAuth
        if ($this->request->is('post')) {
            //remote database
            $conn = ConnectionManager::get('wcdb');

            $this->log($this->request->data,'debug');
            $uid = $this->request->data['user'];

            $subs = TableRegistry::get('Subscriptions', ['connection'=>$conn]);

            $opts['join'] = [['table'=>'users','alias'=>'user','type'=>'INNER','conditions'=>['user.id = Subscriptions.user_id']],
                ['table'=>'services','alias'=>'service','type'=>'INNER','conditions'=>['service.code = Subscriptions.code']] ];
            $opts['conditions'] = ['Subscriptions.user_id'=>$uid];
            $opts['fields']=['Subscriptions.id','Subscriptions.code','Subscriptions.merchant_name', 'Subscriptions.currency', 'Subscriptions.rate', 'service.description', 'user.username'];
            $opts['order'] = ['Subscriptions.code' => 'ASC'];

            $query2 = $subs->find('all',$opts);
            $subs = $query2->toArray();

            $this->log($query2,'debug');
            //$this->log($subs,'debug');
            //$this->log($subs[0]['merchant_name'],'debug');

            $reqs = TableRegistry::get('Requests', ['connection'=>$conn]);
            $opts = array();
            $opts['join'] = [['table'=>'users','alias'=>'user','type'=>'INNER','conditions'=>['user.id = Requests.user_id']], ];
            $opts['conditions'] = ['Requests.user_id'=>$uid, 'COALESCE(Requests.return_code, 0) >='=> -10];
            // -98 = 权限不足
            //, 'Requests.ip !='=>self::WECOLLECT_HK_IP];
            // not include office IP for prd users
            if (strtolower($subs[0]['merchant_name'])!='test') {
                $this->log('Excluding IP: '.self::WECOLLECT_HK_IP,'debug');
                $opts['conditions'][] = ['Requests.ip !=' => self::WECOLLECT_HK_IP];
            }
            $opts['fields']=['Requests.id','Requests.datetime','Requests.ip', 'Requests.resource', 'user.username'];
            $opts['order'] = ['Requests.datetime' => 'DESC'];

            if (!empty($this->request->data['startdate'])) {
                $starttime = strtotime($this->request->data['startdate'].' 00:00');
                $opts['conditions']['Requests.datetime >='] = $starttime;
            }
            if (!empty($this->request->data['enddate'])) {
                $endtime = strtotime($this->request->data['enddate'].' 23:59:59');
                $opts['conditions']['Requests.datetime <='] = $endtime;
            }

            $query2 = $reqs->find('all',$opts);
            //$this->log($query2->toArray(),'debug');
            $data = array();
            //$data[] = ['id', 'username', 'date', 'ip', , 'code'];
            foreach ($query2 as $q) {
                $tmp = ['id'=>$q->id, 'user_id'=>$q->user['username'], 'date'=>date('Y-m-d H:i',$q->datetime), 'ip'=>$q->ip, 'resource'=>$q->resource, 'code'=>strtolower(trim(strrchr(trim($q->resource,' /'),'/'),'/'))];
                $data[] = $tmp;
            }
            //$this->log($data,'debug');

            $file = $this->saveExcelReport($starttime, $endtime, $subs, $data);
            if ($file)
                return $this->serveExcel($file);

            $this->Flash->error('No report available for the period.');
            return NULL;

/*
 *    Since no model class of Users & Service, below is not working
 *
            $query = $this->Requests->find('all')
                ->contain(['Users'])
                ->order(['Requests.datetime' => 'DESC']);

            $query->where(['Requests.user_id =' => $uid]);
            $starttime = strtotime($this->request->data['startdate'].' 00:00');
            $endtime = strtotime($this->request->data['enddate'].' 23:59:59');

            if (!empty($this->request->data['startdate']))
                $query->where(['Requests.datetime >=' => $starttime ]);
            if (!empty($this->request->data['enddate']))
                $query->where(['Requests.datetime <=' => $endtime]);

            $query2 = $query->map(function ($row) { // map() is a collection method, it executes the query
                $row->code = strtolower(trim(strrchr(trim($row->resource,' /'),'/'),'/'));
                return $row;
            });
*/

        }

    }

    public function saveExcel($data) {
        if (!is_array($data))
            return false;

        //$this->log($data,'debug');
        $excel = new \PHPExcel();
        $excel->setActiveSheetIndex(0);
        $sheet = $excel->getActiveSheet();
        $sheet->setTitle('Requests');

        $sheet->fromArray($data, null, 'A1');

        $f = sprintf('%s/requests_%s.xlsx',$this->excelFolder, time());
        $writer = new \PHPExcel_Writer_Excel2007($excel);
        $writer->save($f);

        return $f;
    }

    public function saveExcelReport($start, $end, $subs, $usages) {
        if (!count($subs))
            return false;

        $startdate = date('Y-m-d', $start);
        $enddate = date('Y-m-d', $end);
        $totals = array();
        if (is_array($usages))
            foreach ($usages as $u) {
                $code = $u['code'];
                $totals[$code] = (isset($totals[$code])?$totals[$code]+1:1);
            }

        $tpl = ROOT.'/data/template/civs_usage_report_template.xlsx';
        $excel = \PHPExcel_IOFactory::load($tpl);
        $sheet1 = $excel->getSheetByName('Summary');
        //Period
        //$this->log($subs[0]->user,'debug');
        $sheet1->setCellValue("A8", 'User ID: '.$subs[0]->user['username']);
        $sheet1->setCellValue("A9", 'Client Name: '.$subs[0]->merchant_name);
        $sheet1->setCellValue("E8", $startdate);
        $sheet1->setCellValue("E9", $enddate);

        $row = $baseRow = 14;
        $idx = 0;
        foreach ($subs as $sub) {
            $row = $baseRow + $idx;
            $sheet1->insertNewRowBefore($row+2, 1); //insert 1 row at row#
            $sheet1->setCellValue("A$row", $sub->service['description'])
                ->setCellValue("B$row", strtoupper($sub->code))
                ->setCellValue("C$row", (isset($totals[$sub->code])?$totals[$sub->code]:0))
                ->setCellValue("D$row", $sub->rate)
                ->setCellValue("E$row", "=C$row*D$row");
            $idx++;
        }
        //remove 2 empty rows
        $sheet1->removeRow($row+1, 3);

        //Service Charges, =-(SUM(E14:E16))
        $s1_data = $sheet1->toArray(null,true,true,true);
        foreach ($s1_data as $row_idx=>$row_data) {

            if ($row_data['A']=='Service Charges') {
                $this->log("SUM: $row_idx",'debug');
                $sheet1->setCellValue("B$row_idx", "=-(SUM(E$baseRow:E".($baseRow+$idx)."))");
                break;
            }
        }

        // 2nd sheet
        $sheet2 = $excel->getSheetByName('Usage Logs');
        //Period
        $sheet2->setCellValue("C6", $startdate);
        $sheet2->setCellValue("C7", $enddate);
        $row = $baseRow = 9;
        $idx = 0;
        $this->log("TZ:".date('e'),'debug');
        if (count($usages)) {
            //$this->log($usages,'debug');

            foreach ($usages as $u) {
                $row = $baseRow + $idx;
                $sheet2->insertNewRowBefore($row + 1, 1); //insert 1 row at row#
                $localtm = $this->getLocalTime($u['date']);
                $date = date('Y-m-d', $localtm);
                $time = date('H:i', $localtm);
                //list($date, $time) = explode(' ', $u['date'], 2);
                $sheet2->setCellValue("A$row", $date)
                    ->setCellValue("B$row", $time)
                    ->setCellValue("C$row", $u['user_id'])
                    ->setCellValue("D$row", strtoupper($u['code']))
                    ->setCellValue("E$row", $u['ip']);
                $idx++;
            }
            $sheet2->removeRow($row + 1, 1);
        }

        $f = sprintf('%s/requests_%s.xlsx',$this->excelFolder, time());
        $writer = new \PHPExcel_Writer_Excel2007($excel);
        $writer->save($f);

        return $f;
    }

    public function serveExcel($output) {
        if (!is_readable($output))
            return false;

        $this->log("output file: $output",'debug');
        $this->response->disableCache();
        $this->response->modified('now');
        $this->response->checkNotModified($this->request);

        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $output_name = basename($output);
        $this->response->type($mime);
        //$this->response->body($output);

        // Optionally force file download
        //$this->response->download($output_name);
        $this->response->file($output,
            ['download' => true, 'name' => $output_name]
        );
        // Return response object to prevent controller from trying to render a view.
        return $this->response;
    }

    //return HK local time
    public function getLocalTime($s) {
        //$this->log("getLocalTime($s): diff=".$this->timezoneDiff,'debug');
        return strtotime($s)+$this->timezoneDiff;
    }
    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $request = $this->Requests->newEntity();
        if ($this->request->is('post')) {
            $request = $this->Requests->patchEntity($request, $this->request->data);
            if ($this->Requests->save($request)) {
                $this->Flash->success(__('The request has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The request could not be saved. Please, try again.'));
            }
        }
        $users = $this->Requests->Users->find('list', ['limit' => 200]);
        $this->set(compact('request', 'users'));
        $this->set('_serialize', ['request']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Request id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $request = $this->Requests->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $request = $this->Requests->patchEntity($request, $this->request->data);
            if ($this->Requests->save($request)) {
                $this->Flash->success(__('The request has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The request could not be saved. Please, try again.'));
            }
        }
        $users = $this->Requests->Users->find('list', ['limit' => 200]);
        $this->set(compact('request', 'users'));
        $this->set('_serialize', ['request']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Request id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $request = $this->Requests->get($id);
        if ($this->Requests->delete($request)) {
            $this->Flash->success(__('The request has been deleted.'));
        } else {
            $this->Flash->error(__('The request could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}