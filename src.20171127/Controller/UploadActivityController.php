<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Core\Configure;
use Cake\Database\Type;
use Cake\ORM\TableRegistry;
use Cake\Datasource\Exception\RecordNotFoundException;
/**
 * UploadActivity Controller
 *
 * @property \App\Model\Table\UploadActivityTable $UploadActivity
 */
class UploadActivityController extends AppController
{
	const STATUS_OK = 0;
	const STATUS_NG = -1; //Not good
	const STATUS_PENDING = -2;
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
		$this->paginate= [
		'order' => ['UploadActivity.upload_time' => 'desc'
			]
		];

		
        //$uploadActivity = $this->paginate($this->UploadActivity);
		$query = $this->UploadActivity->find('all')
				->where(['status' => self::STATUS_OK])
				//pending
				->orWhere(['status' => self::STATUS_PENDING])
				->contain(['Merchants']);
		//$query->hydrate(false);
		
		$uploadActivity = $this->paginate($query);
		//$this->log($uploadActivity, 'debug');
		/*
		$uploadActivity = $this->paginate($this->UploadActivity->find('all')
				->where(['status' => self::STATUS_OK])
				//pending
				->orWhere(['status' => self::STATUS_PENDING])
				->join([
					'm'=>[
					'table' => 'merchants',
					//'alias' => 'm',
					'type' => 'LEFT',
					'conditions' => 'm.id = merchant',
					]], ['m.name'=>'varchar'])
				);
		*/
		$this->set('status_ok', self::STATUS_OK);
		$this->set('status_pending', self::STATUS_PENDING);
		
        $this->set(compact('uploadActivity'));
        $this->set('_serialize', ['uploadActivity']);
    }

    /**
     * View method
     *
     * @param string|null $id Upload Activity id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $uploadActivity = $this->UploadActivity->get($id, [
            'contain' => []
        ]);

        $this->set('uploadActivity', $uploadActivity);
        $this->set('_serialize', ['uploadActivity']);
    }

	public function viewFile($id = null)
	{
		//$this->view($id);
		$uploadActivity = $this->UploadActivity->get($id, [
            'contain' => []
        ]);
		
		$basepath = Configure::read('WC.data_path');
		
		if (!empty($this->request->query('json'))) {
			$mime = 'application/json';
			$output = $basepath.$uploadActivity->json_file;
			$output_name = basename($output);
            $force_dl = false;
		} else {
			//xlsx
			$mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			$output = $basepath.$uploadActivity->output_file;
			$output_name = $uploadActivity->source_file;
            $force_dl = true;
		}
				//$this->response->download('filename_for_download.json');
		$this->response->header(array(
				"Content-Type: $mime",
				'Pragma: no-cache'
				));
				
		//$this->log($this->request, 'info');
		$this->log("output: $output", 'info');
		if (is_readable($output) && is_file($output))
		{
		    if ($force_dl)
			    $this->response->file($output,['download' => true, 'name' => $output_name]);
            else
                $this->response->file($output);

		// Return response object to prevent controller from trying to render
		// a view.
			return $this->response;
		
		}
		
		$this->Flash->error(__('File not found: ').$uploadActivity->output_file);
		$this->set('uploadActivity', $uploadActivity);
        $this->set('_serialize', ['uploadActivity']);
	}
	
    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $uploadActivity = $this->UploadActivity->newEntity();
        if ($this->request->is('post')) {
            $uploadActivity = $this->UploadActivity->patchEntity($uploadActivity, $this->request->data);
            if ($this->UploadActivity->save($uploadActivity)) {
                $this->Flash->success(__('The upload activity has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The upload activity could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('uploadActivity'));
        $this->set('_serialize', ['uploadActivity']);
    }
	
	public function log2DB($data)
    {
		$this->log("log2DB:", 'info');
		$this->log($data, 'info');
		
		//update existing records
		$query = $this->UploadActivity->query();
		$query->update()
			->set(['status' => self::STATUS_NG])
			->where(['status !=' => self::STATUS_NG, 'merchant_id'=>$data['merchant_id'],'currency'=>$data['currency'], 'settle_time'=>$data['settle_time']])
			//, tx_time'=>$data['tx_time']])
			->execute();
	
        $uploadActivity = $this->UploadActivity->newEntity();
        if (!empty($data)) {
            $uploadActivity = $this->UploadActivity->patchEntity($uploadActivity, $data);
			$this->log($uploadActivity, 'info');
			$this->log($uploadActivity->errors(), 'debug');
            if ($this->UploadActivity->save($uploadActivity)) {
				
                return TRUE;
            } else {
                
            }
        }
		//$this->log($this->UploadActivity->validationErrors,'debug');
		//$this->log($this->UploadActivity->getDataSource()->getLog(false, false),'debug');
		return FALSE;
/*        
		$this->set(compact('uploadActivity'));
        $this->set('_serialize', ['uploadActivity']);
		 */
    }


	public function isExist($data)
    {
		$this->log("isExist:", 'info');
		$this->log($data, 'info');
		
		if (!empty($data)) {
			$total  = $this->UploadActivity->find()->where($data)->count();
			return ($total>0);
		}
		return FALSE;
	}
    /**
     * Edit method
     *
     * @param string|null $id Upload Activity id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $uploadActivity = $this->UploadActivity->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $uploadActivity = $this->UploadActivity->patchEntity($uploadActivity, $this->request->data);
            if ($this->UploadActivity->save($uploadActivity)) {
                $this->Flash->success(__('The upload activity has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The upload activity could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('uploadActivity'));
        $this->set('_serialize', ['uploadActivity']);
    }


	public function approve($id = null)
    {
        $this->log("approve($id)", 'debug');
        //exclude update NG item
        try {
            $uploadActivity = $this->UploadActivity->get($id, [
                'conditions' => ['status !=' => self::STATUS_NG],
                'contain' => []
            ]);
        } catch (RecordNotFoundException $e) {
            return $this->redirect(['action' => 'index']);
        }

        $this->log($uploadActivity, 'debug');
        //if ($this->request->is(['patch', 'post', 'put', 'get'])) {
        if ($this->request->is(['post', 'get'])) {

            if ($this->request->query['type']=='true') {	//Approve
                    $updates = ['status'=> self::STATUS_OK, 'update_time'=>date('Y-m-d H:i:s')];
                    $msg = 'The upload activity has been approved.';
            } else {
                    $updates = ['status'=> self::STATUS_NG, 'update_time'=>date('Y-m-d H:i:s')];
                    $msg = 'The upload activity has been deleted.';
            }

            $uploadActivity = $this->UploadActivity->patchEntity($uploadActivity, $updates);
            $this->log($uploadActivity, 'debug');

            if ($this->UploadActivity->save($uploadActivity)) {
                    $this->Flash->success(__($msg));
                    //return $this->redirect(['action' => 'index']);
            } else {
                    $this->Flash->error(__('The upload activity could not be saved. Please, try again.'));
            }

        }

		return $this->redirect(['action' => 'index']);
		/*
        $this->set(compact('uploadActivity'));
        $this->set('_serialize', ['uploadActivity']);
		 */
    }
    /**
     * Delete method
     *
     * @param string|null $id Upload Activity id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $uploadActivity = $this->UploadActivity->get($id);
        if ($this->UploadActivity->delete($uploadActivity)) {
            $this->Flash->success(__('The upload activity has been deleted.'));
        } else {
            $this->Flash->error(__('The upload activity could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
