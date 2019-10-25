<?php

namespace App\Tasks;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Console\Shell;

class QueueTask extends Shell
{

    public function run($job)
    {
        return true;
    }

    public static function memoryUsage()
    {
        return "Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'/'.((memory_get_peak_usage() / 1024 / 1024)<<0).'MB';
    }
}
