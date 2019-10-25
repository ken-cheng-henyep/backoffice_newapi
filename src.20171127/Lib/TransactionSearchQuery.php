<?php

namespace App\Lib;

use Cake\Datasource\ConnectionManager;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Event\Event ;

class TransactionSearchQuery
{
    public $query;
    public $startDate;
    public $endDate;
}
