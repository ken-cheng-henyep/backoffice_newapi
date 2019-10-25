<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Network\Exception\NotFoundException;

/**
 * MerchantArticle Controller
 *
 * @property \App\Model\Table\MerchantArticleTable $MerchantArticle
 */
class MerchantArticleController extends AppController
{
    const NEWS_TYPE_VALUE = 4;  //News

    var $basepath, $username;

    public function initialize() {
        parent::initialize();

        $this->basepath = Configure::read('WC.data_path').'docs/';
        $usrs = $this->request->session()->read('Auth.User');
        $this->username = $usrs['username'];
    }

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index($type = null)
    {
        //replaced with json feed
        /*
        $merchantArticle = $this->paginate($this->MerchantArticle);

        $this->set(compact('merchantArticle'));
        $this->set('_serialize', ['merchantArticle']);
        */
        $isNews = ($type=='news');
        if ($isNews) {
            //News
            $pageTitle = 'News and Announcement';
        } else {
            //Documentation
            $pageTitle = 'Documentation';
        }
        $this->set(compact('isNews', 'pageTitle'));
    }

    /**
     * View method
     *
     * @param string|null $id Merchant Article id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $merchantArticle = $this->MerchantArticle->get($id, [
            'contain' => []
        ]);

        $this->set('merchantArticle', $merchantArticle);
        $this->set('_serialize', ['merchantArticle']);
    }

    /*
     * Return article list in json
     */
    public function jsonList($type = null)
    {
        $this->log("jsonList: $type", 'debug');
        $this->log($this->request->query, 'debug');
        $data = ['status'=>-1, 'msg'=>'Failed'];

        if ($this->request->is('ajax')) {
            $types = $this->MerchantArticle->newEntity()->types;
            $query = $this->MerchantArticle->find('all');
                //->order(['type','create_time' => 'desc']) ;

            //docs or news or all
            if ($type=='news')
                $query->where(['type' => self::NEWS_TYPE_VALUE]);
            elseif ($type=='docs')
                $query->where(['type !=' => self::NEWS_TYPE_VALUE]);
/*
            if (isset($this->request->query['filter']) && is_array($this->request->query['filter']['filters'])) {
                $query = $this->RemittanceBatch->find('all')
                    //->order(['upload_time' => 'desc'])
                    ->contain(['Merchants']);

                foreach ($this->request->query['filter']['filters'] as $filter) {
                    $val = trim($filter['value']);
                    if ($val!='')
                        switch ($filter['field']) {
                        }   //switch
                }
            }
*/
            $exprs = array();
            foreach ($types as $st=>$name) {
                $exprs[] = $query->newExpr()->eq('type', $st);
            }
            $sectionCase = $query->newExpr()
                ->addCase(
                    $exprs,
                    array_values($types),
                    array_fill(0, count($types), 'string')
                );

            //select case & all fields
            $query->select(['section'=>$sectionCase])
                ->select($this->MerchantArticle)
                ->order(['section' => 'asc', 'create_time' => 'desc']);
/*
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
*/
            $total = $query->count();
            if (isset($this->request->query['page']) && isset($this->request->query['pageSize']))
                $query->limit($this->request->query['pageSize'])
                    ->page($this->request->query['page']);

            //$this->log($query->__debugInfo(), 'debug');
            $this->log($query, 'debug');

            $res = $query->toArray();
            //Add data to Array
            foreach ($res as $k=>$r) {
                //$this->log($r->filename, 'debug');
                $res[$k]['filename'] = $r->filename;
                $res[$k]['status_txt'] = ($r['status']>0?'Published':'Unpublished');
                $res[$k]['action_txt'] = ($r['status']>0?'Unpublish':'Publish');
                $res[$k]['action_status'] = ($r['status']>0?-1:1);
                $action = ($r['type']==self::NEWS_TYPE_VALUE?'editNews':'edit');
                $res[$k]['action_url'] = Router::url(['action' => $action, $r['id']]);
            }
            $data = ['status'=>1, 'msg'=>'Success', 'data'=>$res, 'total'=>$total];
        }
/*
        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
*/
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    public function updateStatus()
    {
        $this->log($this->request->data, 'debug');

        $data = ['status' => -1, 'msg' => 'Failed'];
        if ($this->request->is('ajax')) {
            $id = $this->request->data['id'];
            $status = $this->request->data['status'];

            $item = $this->MerchantArticle->get($id); // Return article with id
            // $lists = $query->all();
            $this->log($item, 'debug');

            $item->status = $status;
            $item->publish_time = getCurrentTimeStampString();
            if ($this->MerchantArticle->save($item)) {
                //$this->log($item, 'debug');
                $data = ['status' => 0, 'msg' => 'Success', 'id' => $item->id];
            }

        }
        $this->response->type('json');
        $this->response->body(json_encode($data));
        return $this->response;
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|null Redirects on successful add, renders view otherwise.
     */
/*
    public function addDoc()
    {
        $merchantArticle = $this->MerchantArticle->newEntity();
        if ($this->request->is('post')) {
            $dba = array();

            if (is_array($this->request->data['upfile'])) {
                $file = $this->request->data['upfile'];
                $md5 = md5_file($file['tmp_name']);
                $ext = strtolower(strrchr($file['name'], '.'));

                $newFileName = sprintf("%s_%s%s", basename($file['name'], $ext), $md5, $ext);
                $this->log('move_uploaded_file:'."{$file['tmp_name']} \r\n to {$this->basepath}$newFileName", 'info');
                move_uploaded_file($file['tmp_name'], $this->basepath.$newFileName);
                //todo: sync to apps server
                $dba['path'] = $newFileName;
                $dba['file_md5'] = $md5;
            }

            //todo: check if update
            $dba['title'] = trim($this->request->data['title']);
            $dba['type'] = $this->request->data['type'];
            $dba['username'] = $this->username;
            $dba['status'] = 0; //not live
            $dba['create_time'] = getCurrentTimeStampString();
            $dba['publish_time'] = null;
            $this->log($dba, 'info');

            $merchantArticle = $this->MerchantArticle->patchEntity($merchantArticle, $dba);
            if ($error = $this->MerchantArticle->save($merchantArticle)) {
                $this->Flash->success(__('The content has been saved.'));
                return $this->redirect(['action' => 'addDoc']);
            }
            $this->log($merchantArticle->errors(), 'debug');
            $this->Flash->error(__('The content could not be saved. Please, try again.'));
        }

        $this->set('type_lst', $merchantArticle->doctypes);
        $this->set(compact('merchantArticle'));
        $this->set('_serialize', ['merchantArticle']);
    }
*/
    /*
     * POST file upload
     */
    public function handleUploadFile($key) {
        if (isset($this->request->data[$key]) && is_array($this->request->data[$key])) {
            $file = $this->request->data[$key];
            if (!is_readable($file['tmp_name']))
                return false;

            $md5 = md5_file($file['tmp_name']);
            $ext = strtolower(strrchr($file['name'], '.'));

            $newFileName = sprintf("%s_%s%s", basename($file['name'], $ext), $md5, $ext);
            $this->log('move_uploaded_file:'."{$file['tmp_name']} \r\n to {$this->basepath}$newFileName", 'info');
            move_uploaded_file($file['tmp_name'], $this->basepath.$newFileName);
            //todo: sync to apps server
            return ['path' => $newFileName, 'file_md5' => $md5];
        }

        return false;
    }

    /**
     * Add or Edit article
     *
     * @param string|null $id Merchant Article id.
     * @return \Cake\Network\Response|null Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $isUpdate = false;

        if ($id != null) {
            try {
                $merchantArticle = $this->MerchantArticle->get($id, [
                    'contain' => []
                ]);
                $isUpdate = true;

            } catch (NotFoundException $nfe) {

            }
        }
        $this->log(" edit($id) isUpdate:$isUpdate",'debug');

        if (!$isUpdate) {
            $merchantArticle = $this->MerchantArticle->newEntity();
        }

        if ($this->request->is(['post'])) {
            $dba = array();
            //default value
            $dba['title'] = trim($this->request->data['title']);
            $dba['type'] = $this->request->data['type'];
            $dba['username'] = $this->username;
            //$dba['status'] = 0; //not live
            $files = $this->handleUploadFile('upfile');
            if (is_array($files))
                $dba = array_merge($dba, $files);

            if ($isUpdate) {
            } else {
                // insert new record
                $dba['status'] = 0; //not live
                $dba['create_time'] = getCurrentTimeStampString();
                $dba['publish_time'] = null;
            }
            $this->log($dba,'debug');
            //update table
            $merchantArticle = $this->MerchantArticle->patchEntity($merchantArticle, $dba);
            if ($this->MerchantArticle->save($merchantArticle)) {
                $this->Flash->success(__('The content has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The content could not be saved. Please, try again.'));
        }

        if (! empty($merchantArticle->path)) {
           // $merchantArticle->filename = str_replace('_'.$merchantArticle->file_md5, '', $merchantArticle->path);
        }
        $this->set('type_lst', $merchantArticle->doctypes);
        $this->set(compact('merchantArticle'));
        $this->set('_serialize', ['merchantArticle']);

        //$this->render('add_doc');
    }

    public function editNews($id = null)
    {
        $isUpdate = false;

        if ($id != null) {
            try {
                $merchantArticle = $this->MerchantArticle->get($id, [
                    'contain' => []
                ]);
                $isUpdate = true;
                //$this->log($merchantArticle,'debug');
                //$this->log($merchantArticle['content_date'],'debug');
            } catch (NotFoundException $nfe) {

            }
        }
        $this->log(" edit($id) isUpdate:$isUpdate",'debug');

        if (!$isUpdate) {
            $merchantArticle = $this->MerchantArticle->newEntity();
        }

        if ($this->request->is(['post'])) {
            $dba = array();
            //default value
            $dba['content_time'] = $this->request->data['ndate'];
            $dba['title'] = trim($this->request->data['ntitle']);
            $dba['content'] = trim($this->request->data['neditor']);
            $dba['type'] = 4;   //'News'
            $dba['username'] = $this->username;
            //$dba['status'] = 0; //not live
            if ($isUpdate) {
            } else {
                // insert new record
                $dba['status'] = 0; //not live
                $dba['create_time'] = getCurrentTimeStampString();
                $dba['publish_time'] = null;
            }
            $this->log($dba,'debug');
            //update table
            $merchantArticle = $this->MerchantArticle->patchEntity($merchantArticle, $dba);
            if ($this->MerchantArticle->save($merchantArticle)) {
                $this->Flash->success(__('The content has been saved.'));
                return $this->redirect(['action' => 'index', 'news']);
            } else {
                $this->Flash->error(__('The content could not be saved. Please, try again.'));
            }

        }

        $this->set('type_lst', $merchantArticle->doctypes);
        $this->set(compact('merchantArticle'));
        $this->set('_serialize', ['merchantArticle']);

    }
    /**
     * Delete method
     *
     * @param string|null $id Merchant Article id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        /*
        $this->request->allowMethod(['post', 'delete']);
        $merchantArticle = $this->MerchantArticle->get($id);
        if ($this->MerchantArticle->delete($merchantArticle)) {
            $this->Flash->success(__('The merchant article has been deleted.'));
        } else {
            $this->Flash->error(__('The merchant article could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
        */
    }
}
