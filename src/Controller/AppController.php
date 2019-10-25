<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link      http://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link http://book.cakephp.org/3.0/en/controllers.html#the-app-controller
 */
class AppController extends Controller
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

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');
        $this->loadComponent('Auth', [
            'authorize' =>
                //'Controller',
                'Burzum/SimpleRbac.SimpleRbac',
/*                [
			    //'Controller'
                //'Burzum.SimpleRbac.SimpleRbac'
            ],
 */
            //redirect to same page after login & logout
            'loginRedirect' => [
                'controller' => 'Pages',
                'action' => 'index'
            ],
            'logoutRedirect' => [
                'controller' => 'Pages',
                'action' => 'index',
                //'home'
            ]
        ]);
        //$this->Auth->config('authorize', ['Rbac']);
    }
    
    public function isAuthorized($user)
    {
        //$this->log("isAuthorized:".var_export($user,true),'info');
        $this->log("isAuthorized: uid={$user['id']}", 'info');
        //return false;
        // Admin can access every action
        if (isset($user['role']) && $user['role'] === 'admin') {
            return true;
        }

        //$this->log("isAuthorized:".var_export($user,true),'info');
        //Allow all users access
        if (isset($user['role']) && $user['role'] === 'user') {
            return true;
        }
        // Default deny
        return false;
    }
    
    public function beforeFilter(Event $event)
    {
        //action allow without login
        //$this->Auth->allow(['index', 'view', 'display']);
        //$this->Auth->allow(['view']);
        $this->Auth->allow([]);
    }

    /**
     * Before render callback.
     *
     * @param \Cake\Event\Event $event The beforeRender event.
     * @return void
     */
    public function beforeRender(Event $event)
    {
        if (!array_key_exists('_serialize', $this->viewVars) &&
            in_array($this->response->type(), ['application/json', 'application/xml'])
        ) {
            $this->set('_serialize', true);
        }
    }

    

    protected function dataResponse($response, $format = 'json')
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

        $this->set(
            [
            'response' => $response,
            '_serialize' => 'response'
            ]
        );
    }
}
