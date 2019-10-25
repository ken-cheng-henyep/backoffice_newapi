<?php
namespace App\Controller;

use App\Controller\AppController;

/**
 * RemittanceLog Controller
 *
 * @property \App\Model\Table\RemittanceLogTable $RemittanceLog
 */
class RemittanceLogController extends AppController
{

    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        $this->paginate = [
            'contain' => ['Batches']
        ];
        $remittanceLog = $this->paginate($this->RemittanceLog);

        $this->set(compact('remittanceLog'));
        $this->set('_serialize', ['remittanceLog']);
    }

    /**
     * View method
     *
     * @param string|null $id Remittance Log id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $remittanceLog = $this->RemittanceLog->get($id, [
            'contain' => ['Batches']
        ]);

        $this->set('remittanceLog', $remittanceLog);
        $this->set('_serialize', ['remittanceLog']);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $remittanceLog = $this->RemittanceLog->newEntity();
        if ($this->request->is('post')) {
            $remittanceLog = $this->RemittanceLog->patchEntity($remittanceLog, $this->request->data);
            if ($this->RemittanceLog->save($remittanceLog)) {
                $this->Flash->success(__('The remittance log has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The remittance log could not be saved. Please, try again.'));
            }
        }
        $batches = $this->RemittanceLog->Batches->find('list', ['limit' => 200]);
        $this->set(compact('remittanceLog', 'batches'));
        $this->set('_serialize', ['remittanceLog']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Remittance Log id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $remittanceLog = $this->RemittanceLog->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $remittanceLog = $this->RemittanceLog->patchEntity($remittanceLog, $this->request->data);
            if ($this->RemittanceLog->save($remittanceLog)) {
                $this->Flash->success(__('The remittance log has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The remittance log could not be saved. Please, try again.'));
            }
        }
        $batches = $this->RemittanceLog->Batches->find('list', ['limit' => 200]);
        $this->set(compact('remittanceLog', 'batches'));
        $this->set('_serialize', ['remittanceLog']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Remittance Log id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $remittanceLog = $this->RemittanceLog->get($id);
        if ($this->RemittanceLog->delete($remittanceLog)) {
            $this->Flash->success(__('The remittance log has been deleted.'));
        } else {
            $this->Flash->error(__('The remittance log could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
