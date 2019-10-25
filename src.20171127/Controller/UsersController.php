<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
	public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        //$this->Auth->allow('add');
		$this->Auth->allow('logout');
    }
	
	public function login()
    {
        if ($this->request->is('post')) {
            $user = $this->Auth->identify();
            if ($user) {
                $this->Auth->setUser($user);
                return $this->redirect($this->Auth->redirectUrl());
            }
            $this->Flash->error(__('Invalid username or password, try again'));
        }
    }

    public function logout()
    {
        return $this->redirect($this->Auth->logout());
    }
    /**
     * Index method
     *
     * @return \Cake\Network\Response|null
     */
    public function index()
    {
        $users = $this->paginate($this->Users);

        $this->set(compact('users'));
        $this->set('_serialize', ['users']);
    }

	public function isAuthorized($user)
	{
		if (in_array($this->request->action, ['login', 'logout'])) {
			return true;
		}

		return parent::isAuthorized($user);
	}

	/*
	 * Update password
	 */
    public function update()
    {
        $session = $this->request->session()->read('Auth.User');
        $user = $this->Users->get($session['id'], [
            'contain' => []
        ]);

        // todo: validationDefault
        if ($this->request->is(['patch', 'post', 'put'])) {
            $auth = $this->Auth->identify();

            $this->log("update():".$user->username, 'debug');
            if (! $auth) {
                $this->Flash->error(__('Invalid current password, try again'));
            } else {
                //old password good
                //$this->log($this->request->data, 'debug');
                $password = $this->request->data['new_password'];
                $password2 = $this->request->data['new_password_confirm'];
                if (empty($password) || $password != $password2) {
                    $this->Flash->error(__('New password not match, Please try again'));
                } else {
                    // save password
                    $user = $this->Users->patchEntity($user, ['password'=>$password]);
                    if ($user->errors()) {
                        // Entity failed validation.
                        $this->log($user->errors(), 'debug');
                        $errors = $user->errors();
                        if (is_array($errors['password'])) {
                            foreach ($errors['password'] as $msg)
                                $this->Flash->error(__($msg));
                            return $this->redirect(['action' => 'update']);
                        }
                    }

                    if ($this->Users->save($user)) {
                        $this->Flash->success(__('New password has been saved.'));
                        return $this->redirect(['action' => 'update']);
                    }

                    $this->Flash->error(__('New password cannot be saved, Please try again'));

                }
            }
        }   //post

        $this->set(compact('user'));
        $this->set('_serialize', ['user']);
    }
    /**
     * View method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $user = $this->Users->get($id, [
            'contain' => []
        ]);

        $this->set('user', $user);
        $this->set('_serialize', ['user']);
    }

    /**
     * Add method
     *
     * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $user = $this->Users->newEntity();
        if ($this->request->is('post')) {
            $user = $this->Users->patchEntity($user, $this->request->data);
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                //return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The user could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('user'));
        $this->set('_serialize', ['user']);
    }

    /**
     * Edit method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Network\Exception\NotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $user = $this->Users->get($id, [
            'contain' => []
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $user = $this->Users->patchEntity($user, $this->request->data);
            if ($this->Users->save($user)) {
                $this->Flash->success(__('The user has been saved.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The user could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('user'));
        $this->set('_serialize', ['user']);
    }

    /**
     * Delete method
     *
     * @param string|null $id User id.
     * @return \Cake\Network\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $user = $this->Users->get($id);
        if ($this->Users->delete($user)) {
            $this->Flash->success(__('The user has been deleted.'));
        } else {
            $this->Flash->error(__('The user could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
