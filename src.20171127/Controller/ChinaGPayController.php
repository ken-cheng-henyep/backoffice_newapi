<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use ChinaGPayAPI;

/**
 * @property \App\Model\Table\
 */
class ChinaGPayController extends AppController
{
    public function index()
    {
        //show form
    }

    public function json() {
        $this->RequestHandler->ext = 'json';
        if (!$this->request->is('ajax'))
            return false; //todo

        $gpay = new ChinaGPayAPI();
        $id = $this->request->data['id'];
        $data = $gpay->querys($id);
        /*
        //test
        $data=[['id'=>123456, 'return'=>'success'], ['id'=>987654, 'return'=>'fail']];
        sleep(3);
        */
        $res = ['status'=>0, 'data'=>$data];

        $this->set([
            'response' => $res,
            '_serialize' => 'response'
        ]);
        //sleep(3);
    }

}