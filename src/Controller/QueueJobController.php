<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use WC\Query\QueryHelper;
use App\Lib\JobMetaHelper;

use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\ForbiddenException;

class QueueJobController extends AppController
{
    public function script()
    {
        $this->response->type('javascript');
        if (!$this->request->is('get')) {
            throw new BadRequestException();
        }
    }

    public function cancel($job_id = null)
    {
        if ($this->request->is('post')) {
            $job_id = $this->request->data('job');
        }
        if ($this->request->is('post')) {
            $job_id = $this->request->data('job');
        }

        $user = $this->Auth->user();

        if ($job_id !== null) {
            $job_meta = JobMetaHelper::getMeta($job_id);
            if (empty($job_meta)) {
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Job is not exist.')
                    ]
                ]);
            }
            
            $job_data = $job_meta['data'];

            // Only accessible for owner
            if (isset($job_data['user']['id']) && $job_data['user']['id'] != $user['id']) {
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Job cannot access.')
                    ]
                ]);
            }
            
            if (!JobMetaHelper::is($job_id, [JobMetaHelper::STATUS_PENDING, JobMetaHelper::STATUS_PROCESSING])) {
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Job cannot be cancel.')
                    ]
                ]);
            }
            

            JobMetaHelper::markCancelled($job_id, 'Requested by user.');
                
            return $this->dataResponse([
                'status'=>'cancelled',
            ]);
        }
    

        return $this->dataResponse([
            'status'=>'error',
            'error'=>[
                'message'=>__('Unknown action')
            ]
        ]);
    }

    
    public function check($job_id = null)
    {
        if ($this->request->is('post')) {
            $job_id = $this->request->data('job');
        }

        $user = $this->Auth->user();

        if ($job_id !== null) {
            $job_meta = JobMetaHelper::getMeta($job_id);
            if (empty($job_meta)) {
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Job is not exist.')
                    ]
                ]);
            }
            
            $job_data = $job_meta['data'];


            // Only accessible for owner
            if (isset($job_data['user']['id']) && $job_data['user']['id'] != $user['id']) {
                return $this->dataResponse([
                    'status'=>'error',
                    'error'=>[
                        'message'=>__('Job cannot access.')
                    ]
                ]);
            }

            if (empty($job_data['output'])) {
                return $this->dataResponse([
                    'status'=>$job_meta['status'],
                    'progress'=>$job_meta['progress'],
                    'warning'=>[
                        'message'=>__('Output file not ready.')
                    ]
                ]);
            }



            if (JobMetaHelper::is($job_id, JobMetaHelper::STATUS_FAIL)) {
                return $this->dataResponse(['status'=>'error','message'=>$job_meta['failure_message']]);
            }

            if (JobMetaHelper::is($job_id, JobMetaHelper::STATUS_CANCELLED)) {
                return $this->dataResponse(['status'=>'cancelled','message'=>$job_meta['failure_message']]);
            }

            $progress = $job_meta['progress']; // Value from 0 to 1

            if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_COMPLETED)) {
                return $this->dataResponse(['status'=>'progress', 'progress'=> $progress]);
            }

            // If completed
            if (isset($job_data['type'])) {
                if ($job_data['type'] == 'excelexport') {
                    $output = $job_data['output'];
                    if (!is_readable($output)) {
                        $this->log("Error to write: $output", 'debug');

                        return $this->dataResponse([
                            'status'=>'error',
                            'error'=>[
                                'message'=>__('Error to find the output file.')
                            ]
                        ]);
                    } else {
                        $this->log("uname:".$this->Auth->user('username'), 'debug');
                        $this->log("output file: $output", 'debug');
                        //$this->Flash->success('We will get back to you soon.'. $output);

                        $output_name = basename($output);
                        $output_url = Router::url(['controller'=>'QueueJob', 'action' => 'serveFile', 'xls/'.$output_name]);

                        return $this->dataResponse([
                            'status'=>'done',
                            'url'=>$output_url,
                            'download' => true,
                            'name' => $output_name]);
                    }
                }
            }
            return $this->dataResponse([
                'status'=>'done',
                'error'=>[
                    'message'=>__('Job completed.')
                ]
            ]);
        }

        return $this->dataResponse([
            'status'=>'error',
            'error'=>[
                'message'=>__('Unknown action')
            ]
        ]);
    }
    

    /**
     * Serve file in tmp folder
     *
     * @param  string $f File path
     * @return Resposne The response object
     */
    public function serveFile($f)
    {
        //$path = Configure::read('WC.data_path').$f;
        $path = TMP.$f;
        $this->log("serveFile($path)", 'debug');

        if (!is_readable($path)) {
            throw new \Exception('Requested file is not exist or has been removed.');
        } else {
            $this->response->file($path, ['download' => true, 'name' => basename($f)]);
        }

        return $this->response;
    }
}
