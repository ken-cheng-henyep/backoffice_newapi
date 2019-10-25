<?php
/**
 * @author MGriesbach@gmail.com
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://github.com/MSeven/cakephp_queue
 */

//namespace Queue\Shell\Task;
//namespace Cake\Console;
//namespace Shell\Task;
//namespace Cake\Shell\Task;
namespace App\Shell\Task;

use Queue\Shell\Task\QueueTask;
/**
 * A Simple QueueTask example.
 */
class QueueRemittanceTestTask extends QueueTask {
//class RemittanceAPITask extends QueueTask {

    /**
     * Timeout for run, after which the Task is reassigned to a new worker.
     *
     * @var int
     */
    public $timeout = 10;   //10;

    /**
     * Number of times a failed instance of this task should be restarted before giving up.
     *
     * @var int
     */
    public $retries = 0;    //2;

    /**
     * Example add functionality.
     * Will create one example job in the queue, which later will be executed using run();
     *
     * @return void
     */
    public function add() {
        $this->out('CakePHP Queue Example task.');
        $this->hr();
        $this->out('This is a very simple example of a QueueTask.');
        $this->out('I will now add an example Job into the Queue.');
        $this->out('This job will only produce some console output on the worker that it runs on.');
        $this->out(' ');
        $this->out('To run a Worker use:');
        $this->out('	bin/cake queue runworker');
        $this->out(' ');
        $this->out('You can find the sourcecode of this task in: ');
        $this->out(__FILE__);
        $this->out(' ');
        /*
         * Adding a task of type 'example' with no additionally passed data
         */
        if ($this->QueuedJobs->createJob('RemittanceTest', null, ['priority'=>10])) {
            //hi priority
            $this->out('OK, job created, now run the worker');
        } else {
            $this->err('Could not create Job');
        }
    }

    /**
     * Example run function.
     * This function is executed, when a worker is executing a task.
     * The return parameter will determine, if the task will be marked completed, or be requeued.
     *
     * @param array $data The array passed to QueuedTask->createJob()
     * @param int|null $id The id of the QueuedTask
     * @return bool Success
     */
    public function run(array $data, $id) {
        $this->hr();
        $this->out('CakePHP Queue RemittanceTest task.');
        $this->out(var_export($data, true));
        $this->out("ID: $id");
        $this->log("RUN ID: $id", 'debug');
        //if cannot get lock, wait until get lock or timeout
        $fp = tryFileLock();
        // call API
        $this->log("$id API start", 'debug');
        sleep(10);
        $this->log("$id API end", 'debug');
//exit('exit without unlock');
        tryFileUnlock($fp);

        $this->out('run DONE');
	    return true;


        $this->out(' ->Success, the RemittanceAPI Job was run.<-');
        $this->out(' ');
        $this->out(' ');
        return true;
    }

}
