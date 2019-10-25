<?php


namespace App\Lib\Query;

use NilPortugues\Sql\QueryBuilder\Builder\MySqlBuilder;

use App\Lib\Query\QueryHelper;
use App\Lib\Query\QueryIterator;
use App\Lib\Query\IQueryDataDriver;

use Cake\Database\ConnectionManager;
use Cake\Log\Log;

use SQLBuilder\Driver\MySQLDriver;
use SQLBuilder\ArgumentArray;

/**
 *  An adapter class for connecting CakePHP with SQLBuilder
 */
class CBDataDriver implements IQueryDataDriver
{

    public $driver;
    public $args;
    public $conn;

    protected static $instance = null;
    public static function shared($conn = null)
    {
        if (self::$instance == null) {
            self::$instance = new CBDataDriver($conn);
        } else {
            if ($conn  != null) {
                self::$instance->conn = $conn;
            }
        }
        return self::$instance;
    }

    public function __construct($conn)
    {
        if ($conn != null) {
            $this->conn = $conn;
        } else {
            $this->conn = ConnectionManager::get('default');
        }
        $this->args = new ArgumentArray();
        $this->driver = new MySQLDriver();
    }
    
    /**
     * Count the total rows for the query
     *
     * @param      <type>  $query  The query
     *
     * @return     integer The number of total records
     */
    public function count($query)
    {
        // Clone the orginial object for inline customlization
        $_query = clone $query;
        $sql = $_query -> toSql($this->driver, $this->args);

        Log::write('debug', __METHOD__.'@'.__LINE__.': sql='.$sql);

        $stmt = $this->conn-> execute($sql);

        return $stmt->count();
    }

    /**
     * Fetching records. Passing 2nd & 3rd arguments to limit the output by a range.
     *
     * @param      <type>     $query         The query
     * @param      integer    $start_offset  The start offset
     * @param      integer    $end_offset    The end offset
     *
     * @throws     Exception  If start / end offset isnt valid
     *
     * @return     Array      Returnning the list of rows. Each row stored in an assoicated array
     */
    public function fetch($query, $start_offset = 0, $end_offset = 0)
    {
        // Clone the orginial object for inline customlization
        $_query = clone $query;

        if ($start_offset < 0 || $end_offset < 0 || $end_offset < $start_offset) {
            throw new Exception('FetchingPageContentWithRange invalid');
            // Log::write('debug', __METHOD__.'@'.__LINE__.': FetchingPageContentWithRange invalid : '.print_r(compact('start_offset', 'end_offset'), true));
            // return [];
        }
        $_query->limit($end_offset - $start_offset)->offset($start_offset);

        // Generate sql from query object
        $sql = $_query -> toSql($this->driver, $this->args);

        // Log::write('debug', __METHOD__.'@'.__LINE__.': sql='.$sql);
        
        $stmt = $this->conn-> execute($sql);

        $items = $stmt->fetchAll('assoc');
        return $items;
    }
}
