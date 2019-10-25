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

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

class SettlementRateController extends AppController
{
    protected $Rates;
    public function initialize()
    {
        parent::initialize();

        $this->Rates = TableRegistry::get(
            'rates',
            [
            'className' => 'App\Model\Table\SettlementRateTable',
            ]
        );
        $this->Merchants = TableRegistry::get(
            'm',
            [
            'className' => 'App\Model\Table\MerchantsTable',
            ]
        );
    }

    public function isUpdatedToday($format = 'json')
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

        $query = $this->Rates->find('all');

        $res = $query->toArray();

        $symbol_rates = [];

        $now = date('Y-m-d H:i:s');

        $symbol_rates_last_updated = null;
        foreach ($res as $item) {
            if (empty($symbol_rates_last_updated) || $item['modify_time'] > $symbol_rates_last_updated) {
                $symbol_rates_last_updated = $item['modify_time'];
            }
        }

        $this->set([
        'result'=>['status'=>'done','today_updated'=>$symbol_rates_last_updated->format('Y-m-d') == date('Y-m-d'), 'last_updated'=>$symbol_rates_last_updated->format('Y-m-d H:i:s') ],
        '_serialize'=>'result',
        ]);
    }

    /**
     * Index view for listing symbol rates with merchants list
     *
     */
    public function index()
    {

        $query = $this->Rates->find('all');

        $res = $query->toArray();

        $symbol_rates = [];

        foreach ($res as $item) {
            $item['merchant_names'] = [];
            $symbol_rates[] = $item;
        }
        
        $this->set('symbol_rates', $symbol_rates);
    }

    /**
     * Add view for creating symbol with rate and description
     *
     */
    public function add()
    {
        $entity = $this->Rates->newEntity();
        if ($this->request->is('post')) {
            $entity = $this->Rates->patchEntity($entity, $this->request->data);

            // Get user information
            $user = $this->Auth->user();
            $entity = $this->Rates->patchEntity($entity, [
                'last_updated' => date('Y-m-d'),
                'last_update_by'=>$user['username'],
                'create_time'=>date('Y-m-d H:i:s'),
            ]);
            
            if ($this->Rates->save($entity)) {
                $this->Flash->success(__('The record has been added.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The record could not be saved. Please, try again.'));
            }
        
            $this->set(compact('entity'));
            $this->set('_serialize', ['entity']);
        }
    }

    /**
     * Edit view for updating symbol description by rate id
     *
     */
    public function edit($id = null)
    {
        $entity = $this->Rates->get($id, [
            'contain' => []
        ]);
        if (empty($entity)) {
            $this->Flash->error(__('The record could not be found.'));
            return $this->redirect(['action' => 'index']);
        }
        if ($this->request->is(['patch', 'post', 'put'])) {
            $entity = $this->Rates->patchEntity($entity, $this->request->data);

            // Get user information
            $user = $this->Auth->user();
            $entity -> last_update_by = $user['username'];
            if ($this->Rates->save($entity)) {
                $this->Flash->success(__('The record has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The record could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('entity'));
        $this->set('_serialize', ['entity']);
    }

    /**
     * List view with batch update feature.
     *
     */
    public function batchUpdate($format = 'json')
    {
        if ($this->request->is('post')) {
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

            // If the batch value is missing, prompt error
            if (empty($this->request->data['symbol_rates'])) {
                $this->set([
                    'result'=>['status'=>'failure','msg'=>'Missing required fields.'],
                    '_serialize'=>'result',
                ]);
                return;
            }
            $rates = $this->request->data['symbol_rates'];

            $now = date('Y-m-d H:i:s');

            // Get user information
            $user = $this->Auth->user();

            // Save each item
            foreach ($rates as $rate_id => $rate_val) {
                $entity = $this->Rates->get($rate_id);

                if (!empty($entity)) {
                    $entity -> rate_value = $rate_val;
                    $entity -> last_updated = date('Y-m-d');
                    $entity -> last_update_by = $user['username'];
                    $entity -> modify_time = $now;
                    $this->Rates->save($entity);
                }
            }

            $this->set([
                'result'=>['status'=>'done','last_updated'=>$now],
                '_serialize'=>'result',
            ]);
            return;
        }

        
        $query = $this->Rates->find('all');

        $res = $query->toArray();

        $symbol_rates = [];

        $symbol_rates_last_updated = null;
        foreach ($res as $item) {
            // Update: 7 Sep 2017 - Not necessary to clear the rate value if value not updated in same day.
            // If the value is not updated by today, ignore the rate value and ask for update.
            if (empty($item['last_updated']) || $item['last_updated']->format('Y-m-d') != date("Y-m-d")) {
                // $item['rate_value'] = null;
            }

            if (empty($symbol_rates_last_updated) || $item['modify_time'] > $symbol_rates_last_updated) {
                $symbol_rates_last_updated = $item['modify_time'];
            }

            $symbol_rates[] = $item;
        }
        
        $this->set('symbol_rates', $symbol_rates);
        $this->set('symbol_rates_last_updated', $symbol_rates_last_updated);
    }
}
