<?php 

namespace App\Tasks\Writers;

use \Nekoo\EventEmitter;
use Psr\Log\LoggerTrait;
use Cake\Log\Log;
/**
 * The abstract class for content writer.
 * Created for standardize all the writer interface for different 
 * kind of process.
 */
abstract class ContentWriter 
{
    use LoggerTrait;
    use EventEmitter;


    const EVENT_START = "started";
    const EVENT_END = "ended";
    const EVENT_DATA_CHANGE = "dataChanged";
    const EVENT_SLEEP = "sleep";
    const EVENT_AWAKE = "awake";
    const EVENT_CYCLE = "cycle";
    const EVENT_PROGRESS = "progress";

    protected $data;
    protected $templatePath;

    protected $started = false;
    protected $cancelled = false;

    private $_eventListeners = [];
    /**
     * Constructor
     *
     * @param mixed  $data         The data for processing
     * @param string $templatePath The content template path
     */
    public function __construct($data, $templatePath = null)
    {
        $this->data = $data;
        $this->templatePath;
    }

    /**
     * Write into log
     *
     * @param string $level   Log level
     * @param string $message Message
     * @param array  $context The context
     * 
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        Log::write($level, $message, $context);
    }

    public function data($newData = null)
    {
        if (func_num_args() == 1) { 
            $this->data = $newData;
            return $this;
        }
        return $this->data;
    }

    /**
     * Save the finished content to the path
     *
     * @param string $path The path of output content
     * 
     * @return boolean Returning true if the process completed.
     */
    public function save($path)
    {
        return false;
    }

    /**
     * A method for cleaning necessary content in memory
     *
     * @return void
     */
    public function destroy() 
    {
        if (!$this->started) {
            throw new Exception('Process need to turn-off before destroy it.');
        }
        $this->data = null;
        $this->cancelled = false;
    }

    /**
     * A method for cleaning necessary content in memory
     *
     * @return void
     */
    public function cancel() 
    {
        if ($this->started) {
            throw new Exception('Process need to turn-off before destroy it.');
        }

        $this->cancelled = true;
    }

    protected function started() {
        $this->started = true;
        $this->emit(self::EVENT_START);
    }
    
    protected function ended() {
        $this->started = false;
        $this->emit(self::EVENT_END);
    }

    protected function sleep($duration = 3) {
        $this->emit(self::EVENT_SLEEP);
        sleep($duration);
        $this->emit(self::EVENT_AWAKE);
    }
    
    protected function dataChange($newData) {
        $oldData = $this->data;
        $this->data = $newData;
        $this->emit(self::EVENT_DATA_CHANGE, $newData, $oldData);
    }
    
    protected function progress($value) {
        $this->emit(self::EVENT_PROGRESS, $value);
    }

    protected function cycled() {
        $this->emit(self::EVENT_CYCLE);
    }
    
}