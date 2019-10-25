<?php


namespace App\Lib;

use Josegonzalez\CakeQueuesadilla\Queue\Queue;
use Cake\ORM\TableRegistry;
use Cake\Log\Log;
use SQLBuilder\Universal\Query\SelectQuery;
use SQLBuilder\Universal\Query\InsertQuery;

/**
 * Class for job meta.
 */
class JobMeta
{

    public $id;
    public $queue = 'default';
    public $data = null ;
    public $fail_message;
    public $status = STATUS_PENDING;
    public $progress = 0;
    public $complete_date;
    public $start_date;
    public $create_date;
}

/**
 * Class for job meta helper.
 */
class JobMetaHelper
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAIL = 'fail';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Determines the job status
     *
     * @param string       $job_id The job identifier
     * @param string|array $status The requested status
     *
     * @return boolean Return true if the status code is correct
     */
    public static function is($job_id, $status = self::STATUS_PROCESSING)
    {
        $job_status = self::getStatus($job_id);
        
        if (is_array($status) && in_array($job_status, $status)) {
            return true;
        }

        if (is_string($status) && $job_status == $status) {
            return true;
        }

        return false;
    }

    /**
     * Determines the job status
     *
     * @param string $job_id The job identifier
     *
     * @return string The status name
     */
    public static function getStatus($job_id)
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        return $entity->status;
    }

    /**
     * Gets the meta.
     *
     * @param string  $job_id           The job identifier
     * @param boolean $useArrayInReturn The use array in return
     *
     * @return array The meta.
     */
    public static function getMeta($job_id, $useArrayInReturn = true)
    {
        $entity= self::getEntity($job_id);
        if (empty($entity)) {
            return null;
        }
        if (empty($entity)) {
            return null;
        }
        $entityDict = $entity->toArray();
        if (!empty($entityDict)) {
            $entityDict['data'] = json_decode($entityDict['data'], $useArrayInReturn);
        }
        return $entityDict;
    }

    /**
     * Update job progress
     *
     * @param string  $job_id   The job identifier
     * @param integer $progress The progress
     *
     * @return boolean Return true if record saved successfully
     */
    public static function updateProgress($job_id, $progress = 0)
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, [self::STATUS_PROCESSING])) {
            return false;
        }


        $JobMeta = self::getTable();

        $entity->progress = $progress;

        $JobMeta->save($entity);
        return true;
    }

    /**
     * Update job data
     *
     * @param string     $job_id  The job identifier
     * @param array|null $newData The new data
     *
     * @return boolean Return true if record saved successfully
     */
    public static function updateData($job_id, $newData = null)
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, ['pending',self::STATUS_PROCESSING])) {
            return false;
        }

        if (self::updateEntity($job_id, ['data'=>json_encode($newData)])) {
            return true;
        }
        return false;
    }

    /**
     * Mark the job as start for processnig status
     *
     * @param string $job_id The job identifier
     *
     * @return boolean  Return true if record saved successfully
     */
    public static function markStarted($job_id)
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, [self::STATUS_PENDING])) {
            return false;
        }

        if (self::updateEntity($job_id, ['status'=>self::STATUS_PROCESSING, 'start_date'=>date('Y-m-d H:i:s')])) {
            return true;
        }
        return false;
    }

    /**
     * Mark the job as completed status
     *
     * @param string $job_id The job identifier
     *
     * @return boolean Return true if record saved successfully
     */
    public static function markComplete($job_id)
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, ['started',self::STATUS_PROCESSING])) {
            return false;
        }

        if (self::updateEntity($job_id, ['status'=>self::STATUS_COMPLETED, 'complete_date'=>date('Y-m-d H:i:s')])) {
            return true;
        }
        return false;
    }

    /**
     * Mark the job as fail status
     *
     * @param string $job_id The job identifier
     * @param string $msg    The failure message
     *
     * @return boolean Return true if record saved successfully
     */
    public static function markFailure($job_id, $msg = '')
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, ['started',self::STATUS_PROCESSING])) {
            return false;
        }

        if (self::updateEntity($job_id, ['status'=>self::STATUS_FAIL, 'failure_date'=>date('Y-m-d H:i:s'), 'failure_message'=>$msg])) {
            return true;
        }
        return false;
    }



    /**
     * Mark the job as cancelled status
     *
     * @param string $job_id The job identifier
     * @param string $msg    The failure message
     *
     * @return boolean Return true if record saved successfully
     */
    public static function markCancelled($job_id, $msg = '')
    {
        $entity = self::getEntity($job_id);
        if (empty($entity)) {
            return false;
        }
        if (!in_array($entity->status, ['started',self::STATUS_PROCESSING, self::STATUS_PENDING])) {
            return false;
        }

        if (self::updateEntity($job_id, ['status'=>self::STATUS_CANCELLED, 'failure_date'=>date('Y-m-d H:i:s'), 'failure_message'=>$msg])) {
            return true;
        }
        return false;
    }
    /**
     * Add a new job for queue worker
     *
     * @param string $cls   The callable of the class
     * @param array  $data  The data
     * @param string $queue The queue
     *
     * @throws \Exception During saving record
     *
     * @return string Job ID
     */
    public static function add($cls, $data = [], $queue = 'default')
    {
        $table = self::getTable();

        Log::write('debug', 'JobMetaHelper::add, saving new.');

        // $entity = $table->newEntity();
        // $entity->task = $cls;
        // $entity->queue = $queue;
        // $entity->data = json_encode($data);
        // $entity->status = self::STATUS_PENDING;
        // $entity->create_date = date('Y-m-d H:i:s');

        $success = false;
        $newId = null;
        try {
            $query = new InsertQuery();
            $query->insert([
                'task'=>$cls,
                'queue'=>$queue,
                'data'=> json_encode($data),
                'status'=> self::STATUS_PENDING,
                'create_date'=> date('Y-m-d H:i:s'),
            ])->into('jobs_meta');
            $stmt = CakeDbConnector::shared()->execute($query);
            
            $newId = $stmt->lastInsertId();
            if (!empty($newId)) {
                $success = true;
            }
            //$success = $table->save($entity);
        } catch (\Exception $exp) {
            Log::write('error', 'Error when creating new job: '.$exp->getMessage().PHP_EOL.'SQL='.CakeDbConnector::shared()->toSql($query));
        }
        if (!$success) {
            throw new \Exception('Unable to save job meta information.');
        }

        $params ['job_id'] = $newId;
        $params ['data'] = $data;

        Log::write('debug', 'JobMetaHelper::add, new id is going to add into queue: '.$newId);

        Queue::push([$cls,'run'], $params, ['queue' => $queue]);
        return $newId;
    }

    /**
     * Gets the table.
     *
     * @return Table The CakePHP table object .
     */
    protected static function getTable()
    {
        $table = TableRegistry::get('jm', ['table'=>'jobs_meta']);

        if (empty($table) || !is_object($table)) {
            throw new Exception('Table instance not exist.');
        }

        return $table;
    }
    
    /**
     * Gets the job entity.
     *
     * @param string $job_id The job identifier
     *
     * @return Entity The job entity.
     */
    protected static function getEntity($job_id)
    {
        $table = self::getTable();
        try {
            $entity = $table->get($job_id);
        } catch (\Exception $exp) {
            return null;
        }

        return $entity;
    }

    /**
     * Update job entity with new property values
     *
     * @param string $job_id   The job identifier
     * @param array  $newProps The new properties
     *
     * @return boolean Return true if record saved successfully
     */
    protected static function updateEntity($job_id, $newProps = null)
    {
        $entity = self::getEntity($job_id);
        $table = self::getTable();

        if ($table->patchEntity($entity, $newProps)) {
            $table->save($entity);
            return true;
        }
        return false;
    }
}
