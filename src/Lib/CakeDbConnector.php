<?php


namespace App\Lib;

use NilPortugues\Sql\QueryBuilder\Builder\MySqlBuilder;

use \WC\Query\QueryHelper;
use \WC\Query\QueryResult;
use \WC\Query\DBAdapter;

use \Cake\Log\Log;

use SQLBuilder\Driver\MySQLDriver;
use SQLBuilder\ArgumentArray;


use SQLBuilder\ToSqlInterface;

/**
 *  An adapter class for connecting CakePHP with SQLBuilder
 */
class CakeDbConnector implements DBAdapter
{

    public $driver;
    public $args;
    public $conn;

    protected static $instance = null;

    /**
     * Geting the singleton object in the memory
     */
    public static function shared($conn = null)
    {
        if (self::$instance == null) {
            self::$instance = new CakeDbConnector($conn);
        } else {
            if ($conn  != null) {
                self::$instance->conn = $conn;
            }
        }
        return self::$instance;
    }

    /**
     * Set the singleton object in the memory
     *
     * @param Connection $conn The database conenction object
     * 
     * @return void
     */
    public static function setShared($conn = null) 
    {
        $instance = self::shared($conn);
        QueryHelper::$db = $instance;
    }

    /**
     * Constructor
     *
     * @param Connection $conn The database conenction object
     */
    public function __construct($conn)
    {
        if ($conn != null) {
            $this->conn = $conn;
        } else {
            $this->conn = null;
        }
        $this->args = new ArgumentArray();
        $this->driver = new MySQLDriver();
    }

    /**
     * Convert to SQL string
     *
     * @param SelectQuery $query The query
     * 
     * @return string
     */
    public function toSql(ToSqlInterface $query)
    {
        $sql = $query -> toSql($this->driver, $this->args);
        return $sql;
    }
   
    /**
     * Execute a batch of SQL statements
     *
     * @param callback $callback The callable function
     *
     * @return mixed The execution result
     */
    public function transactional($callback)
    {
        return $this->conn-> transactional($callback);
    }

    /**
     * Execute a query
     *
     * @param SelectQuery $query The query
     *
     * @return mixed The execution result
     */
    public function execute(ToSqlInterface $query)
    {
        // Clone the orginial object for inline customlization
        $sql = $this->toSql($query);

        // Log::write('debug', __METHOD__.'@'.__LINE__.': sql='.$sql);

        try{
            $stmt = $this->conn-> execute($sql);
        }catch(\Exception $exp){
            Log::write('error', __METHOD__.'@'.__LINE__.', Query Error'.PHP_EOL.'sql: '.$sql. PHP_EOL.'Backtrace:'.PHP_EOL.print_r(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT & DEBUG_BACKTRACE_IGNORE_ARGS), true) );
            throw $exp;
        }
        return $stmt;
    }

    
    /**
     * Count the total rows for the query
     *
     * @param SelectQuery $query The query
     *
     * @return integer The number of total records
     */
    public function count(ToSqlInterface $query)
    {
        $_query = clone $query;
        // $_query->setSelect(['count(*)'=> 'total']);
        // Clone the orginial object for inline customlization
        $sql = $this->toSql($_query);


        try{
            $stmt = $this->conn-> execute($sql);
            return $stmt->count();

            // $row = $stmt->fetch('assoc');
            // Log::write('debug', __METHOD__.'@'.__LINE__.': Query, Count='.print_r($row, true).PHP_EOL.'sql='.$sql);

            // if (isset($row['total'])) {
            //     return (int) $row['total'];
            // }
        }catch(\Exception $exp){
            Log::write('error', __METHOD__.'@'.__LINE__.', Query Error'.PHP_EOL.'sql: '.$sql);
            throw $exp;
        }
        return 0;
    }

    /**
     * Fetching records. 
     * 
     * Passing 2nd & 3rd arguments to limit the output by a range.
     *
     * @param SelectQuery $query        The query
     * @param integer     $start_offset The start offset
     * @param integer     $end_offset   The end offset
     *
     * @throws Exception  If start / end offset isnt valid
     *
     * @return array      Returnning the list of rows. 
     *                    Each row stored in an assoicated array
     */
    public function fetch(ToSqlInterface $query, $start_offset = 0, $end_offset = 0)
    {
        // Clone the orginial object for inline customlization
        $_query = clone $query;

        if ($start_offset >= 0 && $end_offset >= 0) {
            if ($end_offset < $start_offset) {
                Log::write('error', __METHOD__.'@'.__LINE__.': FetchingPageContentWithRange invalid : '.print_r(compact('start_offset', 'end_offset'), true));
                throw new \Exception('FetchingPageContentWithRange invalid');
                // return [];
            }
            $_query->limit($end_offset - $start_offset)->offset($start_offset);
        }

        // Generate sql from query object
        $sql = $this->toSql($_query);

        try{
            $stmt = $this->conn-> execute($sql);
            // Log::write('debug', __METHOD__.'@'.__LINE__.', Queried'.PHP_EOL.'sql: '.$sql);
        }catch(\Exception $exp){
            Log::write('error', __METHOD__.'@'.__LINE__.', Query Error'.PHP_EOL.'sql: '.$sql);
            throw $exp;
        }

        $items = $stmt->fetchAll('assoc');
        return $items;
    }
}
