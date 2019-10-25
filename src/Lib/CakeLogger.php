<?php 

namespace App\Lib;

use Psr\Log\AbstractLogger;
use Cake\Log\Log;

class CakeLogger extends AbstractLogger
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        Log::write($level, $message, $context);
    }

    protected static $_shared = null;
    public static function shared() {
        if (self::$_shared == null) {
            self::$_shared = new CakeLogger();
        }
        return self::$_shared;
    }
}