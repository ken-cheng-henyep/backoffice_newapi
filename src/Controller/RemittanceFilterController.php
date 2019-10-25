<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use RemittanceReportReader;
use RemittanceException;

/**
 * RemittanceFilter Controller
 *
 * @property \App\Model\Table\RemittanceFilterTable $RemittanceFilter
 */
class RemittanceFilterController extends AppController
{
    const INSERT_ERROR_MSG = 'The filter could not be saved. Please, try again.';
    var $reader, $username, $role;
// $update_url, $delete_url;

    public function initialize() {
        parent::initialize();

        $db_name = ConnectionManager::get('default')->config()['database'];
        $this->reader = new RemittanceReportReader($db_name);
        $this->log("initialize() $db_name", 'debug');

        $usrs = $this->request->session()->read('Auth.User');
        $this->username = $usrs['username'];
        if (isset($usrs['role']))
            $this->role = $usrs['role'];

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

        $update_url = Router::url(['action' => 'setStatus']);
        $delete_url = Router::url(['action' => 'delete']);
        $this->set(compact('update_url', 'delete_url'));
    }

    /**
     * Blacklist Filter
     *
     * @return \Cake\Network\Response|null
     */
    public function blacklist()
    {

        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');

            $field = trim($this->request->data['type']);
            $value = trim($this->request->data['val']);
            $mid = $this->request->data['merchant'];

            try {
                if (!empty($field) && !empty($value) && $this->reader->addBlacklistFilter($field, $value, $mid)) {
                    $this->Flash->success(__('The filter has been saved.'));
                } else {
                    $this->Flash->error(__('The filter could not be saved. Please, try again.'));
                }
            } catch (RemittanceException $e) {
                $this->log('RemittanceException: '.$e->getMessage(),'debug');
                $this->Flash->error(__('Filter already exists.'));
            }
        }
        $json_url = Router::url(['action' => 'blacklistJson']);
        //$json_url = Router::url(['action' => 'listJson', ['401', '402']]);

        $this->set(compact('json_url'));
    }

    /**
     * View method
     *
     * @param string|null $id Remittance Filter id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function blacklistJson($id = null)
    {
        $this->log("blacklistJson ($id), role:".$this->role, 'debug');

        if (!$this->request->is('ajax')) {
            return false;
        }

        return $this->listJson(['401', '402']);
        /*
        $filters = $this->reader->getBlacklistFilters();

        if (is_array($filters)) {
            foreach ($filters as $k=>$f) {
                $filters[$k]['type_name'] = RemittanceReportReader::getBlacklistFilterName($f['column']);
                $live = ($f['status']>0);
                $filters[$k]['status_name'] = ($live?'Enabled':'Disabled');
                $filters[$k]['action_txt'] = ($live?'Disable':'Enable');
                $filters[$k]['action_val'] = ($live?'disable':'enable');
                $filters[$k]['action_class'] = '';
            }
            //
        }

        $this->response->type('json');
        $data = ['data'=>$filters, 'total'=>count($filters)];
        $this->response->body(json_encode($data));

        return $this->response;
        */
    }

    /**
     * View method
     *
     * @param string|null $id Remittance Filter id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function listJson($code = null)
    {
        if (!$this->request->is('ajax')) {
            return false;
        }

        if (isset($this->request->data['code']))
            $code = $this->request->data['code'];
        elseif (isset($this->request->query['code']))
            $code = $this->request->query['code'];

        $this->log("listJson , role:".$this->role, 'debug');
        $this->log($code, 'debug');
        //$filters = $this->reader->getBlacklistFilters();
        $filters = $this->RemittanceFilter->find()
            ->where(['code IN' => $code])
            ->contain('Merchants')
            ->contain('RemittanceFilterRule')
            ->order(['create_time' => 'ASC'])
            ->hydrate(false)
            ->map(function ($row) { // map() is a collection method, it executes the query
                $row['merchant_name'] = (isset($row['merchant']['name'])?$row['merchant']['name']:'All');
                $row['live'] = ($row['status']>0);
                $row['action'] = ucwords($row['action']); //block
                $row['period_text'] = RemittanceReportReader::fromSecondsToPeriodText($row['period']);

                $row['amount'] = $row['condition'] = $row['matching_fields'] = null;
                /*
                if (isset($row['remittance_filter_rule'][0]['column']) && $row['remittance_filter_rule'][0]['column']=='amount') {
                    $row['amount'] = floatval($row['remittance_filter_rule'][0]['val']);
                    $row['condition'] = $row['remittance_filter_rule'][0]['condition'];
                }
               */
                if (is_array($row['remittance_filter_rule'])) {
                    foreach ($row['remittance_filter_rule'] as $k=>$rules) {
                        if ($rules['column']=='amount') {
                            $row['amount'] = floatval($rules['val']);
                            $row['condition'] = $rules['condition'];
                        } elseif ($rules['condition']=='=') {
                            $row['matching_fields'][] = RemittanceReportReader::getBlacklistFilterName($rules['column']);
                        }
                    }
                    if (count($row['matching_fields'])) {
                        sort($row['matching_fields']);
                        $row['field_text'] = implode(', ', $row['matching_fields']);
                    }
                    if (count($row['remittance_filter_rule'])==1) {
                        $row['field_val'] = $row['remittance_filter_rule'][0]['val'];
                    }
                }
                return $row;
            })
            ->toArray();

        //$this->log($filters[0], 'debug');

        if (is_array($filters)) {
            foreach ($filters as $k=>$f) {
                //$filters[$k]['type_name'] = RemittanceReportReader::getBlacklistFilterName($f['column']);
                //$filters[$k]['type_name'] = RemittanceReportReader::getBlacklistFilterName($f['remittance_filter_rule'][0]['column']);
                $live = ($f['live']);
                $filters[$k]['status_name'] = ($live?'Enabled':'Disabled');
                $filters[$k]['action_txt'] = ($live?'Disable':'Enable');
                $filters[$k]['action_val'] = ($live?'disable':'enable');
                $filters[$k]['action_class'] = '';
            }
        }

        $this->response->type('json');
        $data = ['data'=>$filters, 'total'=>count($filters)];
        $this->response->body(json_encode($data));

        return $this->response;
    }

    /**
     * Transaction Amount Limit Filter
     *
     * @return \Cake\Network\Response|null
     */
    public function txLimit()
    {
        $code = '403';
        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');

            $condition = trim($this->request->data['condition']);
            $amount = $this->request->data['amount'];
            $action = strtolower(trim($this->request->data['action']));
            $mid = (empty($this->request->data['merchant'])?null:$this->request->data['merchant']);

            //$filter = $this->RemittanceFilter->newEntity();
            if (!empty($condition) && !empty($action) && $amount!='') {
                $dsc = sprintf("Amount %s limit of CNY%.2f.", $condition, $amount);
                $data = ['name'=>'Single transaction limit', 'code'=>$code, 'dsc'=>"$dsc",
                    'merchant_id'=>$mid, 'rule_type'=>'or', 'action'=>$action, 'isblacklist'=>1 ,'status'=>1, 'create_time'=>date('Y-m-d H:i:s'),
                    'remittance_filter_rule'=>[
                        ['column'=>'amount', 'val'=>"$amount", 'condition'=>$condition]
                    ],
                ];
                $this->log("newEntity:".var_export($data, true),'debug');

                //$this->log("isExistFilter:".$this->reader->isExistFilter($data), 'debug');
                //$filter = $this->RemittanceFilter->patchEntity($filter, $data);
                $filter = $this->RemittanceFilter->newEntity($data, ['associated' => ['RemittanceFilterRule']]);
                //$this->log("newEntity Error:".var_export($filter->errors(), true),'debug');

                if ($this->RemittanceFilter->save($filter)) {
                    $this->Flash->success(__('The filter has been saved.'));
                } else {
                    //$errors = $filter->errors('UniqueRemittanceFilter');
                    $errors = $filter->errors('notUnique');
                    $this->log("Error:".var_export($errors, true),'debug');

                    if (count($errors) && isset($errors['UniqueRemittanceFilter'])) {
                        $errMsg = $errors['UniqueRemittanceFilter'];
                    } else {
                        $errMsg = self::INSERT_ERROR_MSG;
                    }
                    $this->Flash->error(__($errMsg));
                }
            }   //! empty
        }   //POST

        $json_url = Router::url(['action' => 'listJson', 'code'=>$code]);

        $this->set(compact('json_url'));
    }

    /**
     * Moving Sum Transaction Amount Limit Filter
     *
     * @return \Cake\Network\Response|null
     */
    public function sumLimit()
    {
        $code = '404';
        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');

            $condition = trim($this->request->data['condition']);
            $amount = $this->request->data['amount'];
            $action = strtolower(trim($this->request->data['action']));
            $mid = (empty($this->request->data['merchant'])?null:$this->request->data['merchant']);
            $period = intval($this->request->data['period']);
            $ptype = $this->request->data['periodtype'];
            $matching = $this->request->data['matching'];
            switch ($ptype) {
                case 'day':
                    $length=$period*86400;
                    break;
                default:    //hour
                    $length=$period*3600;
                    break;
            }

            $rules = null;
            //$filter = $this->RemittanceFilter->newEntity();
            if (!empty($condition) && !empty($action) && $amount!='') {
                $dsc = sprintf("Total transaction amount in the past %d %s %s CNY%.2f", $period, $ptype, $condition, $amount);
                if (!empty($matching)) {
                    $rules = [['column'=>$matching, 'val'=>null, 'condition'=>'=']];
                    $dsc.=" for transactions containing the same value in \"$matching\"";
                }
                $dsc.='.';

                $data = [
                    'name'=>'Moving Sum Transaction Amount Limit', 'dsc'=>"$dsc",
                    'code'=>$code, 'merchant_id'=>$mid, 'rule_type'=>'and', 'action'=>$action, 'amount_limit'=>$amount, 'amount_limit_type'=>$condition, 'period'=> $length,
                    'isblacklist'=>0 ,'status'=>1, 'create_time'=>date('Y-m-d H:i:s'),
                    'remittance_filter_rule'=> $rules,
                ];
                $this->log("newEntity:".var_export($data, true),'debug');

                //$filter = $this->RemittanceFilter->patchEntity($filter, $data);
                $filter = $this->RemittanceFilter->newEntity($data, ['associated' => ['RemittanceFilterRule']]);
                $errors = $filter->errors();
                $this->log("Error:".var_export($errors, true),'debug');

                if ($this->RemittanceFilter->save($filter)) {
                    $this->Flash->success(__('The filter has been saved.'));
                } else {
                    //$this->Flash->error(__('The filter could not be saved. Please, try again.'));
                    $errors = $filter->errors('notUnique');
                    $this->log("Error:".var_export($errors, true),'debug');

                    if (count($errors) && isset($errors['UniqueRemittanceFilter'])) {
                        $errMsg = $errors['UniqueRemittanceFilter'];
                    } else {
                        $errMsg = self::INSERT_ERROR_MSG;
                    }
                    $this->Flash->error(__($errMsg));
                }
            }   //! empty
        }   //POST

        $json_url = Router::url(['action' => 'listJson', 'code'=>$code]);

        $this->set(compact('json_url'));
    }

    /**
     * Transaction Rate Limit Filter
     *
     * @return \Cake\Network\Response|null
     */
    public function rateLimit()
    {
        $code = '405';
        if ($this->request->is('post')) {
            $this->log($this->request->data,'debug');

            $condition = trim($this->request->data['condition']); //Amount Limit Type
            $amount = $this->request->data['amount']; //Amount Limit
            $count = $this->request->data['count']; //Count limit
            $action = strtolower(trim($this->request->data['action']));
            $mid = (empty($this->request->data['merchant'])?null:$this->request->data['merchant']);
            $period = intval($this->request->data['period']);
            $ptype = $this->request->data['periodtype'];
            $matching = $this->request->data['matching'];   //Matching field
            switch ($ptype) {
                case 'day':
                    $length=$period*86400;
                    break;
                default:    //hour
                    $length=$period*3600;
                    break;
            }

            $rules = null;
            if (!empty($period) && $count!='') {
                //compose error message
                $dsc = "Number of transactions";
                if ($amount>0 && !empty($condition)) {
                    $rules[] = ['column'=>'amount', 'val'=>$amount, 'condition'=>$condition];
                    $dsc.= sprintf(" with amount %s CNY%.2f", $condition, $amount);
                }
                $dsc .= sprintf(" in the past %d %s exceeds the limit of %d", $period, $ptype, $count);
                if (!empty($matching)) {
                    $rules[] = ['column'=>$matching, 'val'=>null, 'condition'=>'='];
                    //$dsc.=" for transactions containing the same value in \"$matching\"";
                    $dsc.= sprintf(" for transactions containing the same value in \"%s\"", $matching);
                }
                $dsc.='.';

                $data = [
                    'name'=>'Transaction Rate Limit', 'dsc'=>"$dsc",
                    'code'=>$code, 'merchant_id'=>$mid, 'rule_type'=>'and', 'action'=>$action, 'count_limit'=>$count, 'count_limit_type'=>'>=', 'period'=> $length,
                    'isblacklist'=>0 ,'status'=>1, 'create_time'=>date('Y-m-d H:i:s'),
                    'remittance_filter_rule'=> $rules,
                ];
                $this->log("newEntity:".var_export($data, true),'debug');

                //$filter = $this->RemittanceFilter->patchEntity($filter, $data);
                $filter = $this->RemittanceFilter->newEntity($data, ['associated' => ['RemittanceFilterRule']]);
                $errors = $filter->errors();
                //$this->log("Error:".var_export($errors, true),'debug');

                if ($this->RemittanceFilter->save($filter)) {
                    $this->Flash->success(__('The filter has been saved.'));
                } else {
                    //$this->Flash->error(__('The filter could not be saved. Please, try again.'));
                    $errors = $filter->errors('notUnique');
                    $this->log("Error:".var_export($errors, true),'debug');

                    if (count($errors) && isset($errors['UniqueRemittanceFilter'])) {
                        $errMsg = $errors['UniqueRemittanceFilter'];
                    } else {
                        $errMsg = self::INSERT_ERROR_MSG;
                    }
                    $this->Flash->error(__($errMsg));
                }
            }   //! empty
        }   //POST

        $json_url = Router::url(['action' => 'listJson', 'code'=>$code]);

        $this->set(compact('json_url'));
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|null Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $remittanceFilter = $this->RemittanceFilter->newEntity();
        if ($this->request->is('post')) {
            $remittanceFilter = $this->RemittanceFilter->patchEntity($remittanceFilter, $this->request->data);
            if ($this->RemittanceFilter->save($remittanceFilter)) {
                $this->Flash->success(__('The remittance filter has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The remittance filter could not be saved. Please, try again.'));
        }
        $this->set(compact('remittanceFilter'));
        $this->set('_serialize', ['remittanceFilter']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Remittance Filter id.
     * @return \Cake\Network\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $remittanceFilter = $this->RemittanceFilter->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $remittanceFilter = $this->RemittanceFilter->patchEntity($remittanceFilter, $this->request->data);
            if ($this->RemittanceFilter->save($remittanceFilter)) {
                $this->Flash->success(__('The remittance filter has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The remittance filter could not be saved. Please, try again.'));
        }
        $this->set(compact('remittanceFilter'));
        $this->set('_serialize', ['remittanceFilter']);
    }

    // Enable/Disable filter
    public function setStatus()
    {
        $this->log('updateStatus: query, data', 'debug');
        $this->log($this->request->data, 'debug');
        $data = ['status' => -1, 'msg' => 'Failed'];

        if ($this->request->is('ajax')) {
            $id = $this->request->data['id'];
            $status = (strtolower($this->request->data['status'])=='disable'?-1:1);

            $remittanceFilter = $this->RemittanceFilter->get($id, [
                'contain' => []
            ]);
            $remittanceFilter = $this->RemittanceFilter->patchEntity($remittanceFilter, ['status'=>$status]);
            if ($this->RemittanceFilter->save($remittanceFilter)) {
                $data = ['status' => 1, 'msg' => 'Success'];
            }
        }
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }
    /**
     * Delete method
     *
     * @param string|null $id Remittance Filter id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete()
    {
        $this->request->allowMethod(['post', 'ajax']);

        $data = ['status' => -1, 'msg' => 'Failed'];
        $id = $this->request->data['id'];
        $remittanceFilter = $this->RemittanceFilter->get($id);

        if ($this->RemittanceFilter->delete($remittanceFilter)) {
            $data = ['status' => 1, 'msg' => 'Success'];
        } else {
        }

        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
        //return $this->redirect(['action' => 'index']);
    }
}
