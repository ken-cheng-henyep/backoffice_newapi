<?php

namespace App\Lib\Query;

use Cake\Log\Log;

/**
 * Class for fetching data in iterator liked style.
 *
 * This class is relied to package corneltek/sqlbuilder to build ORM instance and fetch data by an instance of IQueryDataDriver.
 *
 * Before reading the data, it need to perform <code>start()</code> method for starting connection with database. All data will be fetched by calling simple <code>\Iterator</code> defined methods :
 *     - <code>next()</code> for moving pointer to load next row
 *     - <code>current()</code> for getting the row
 *     - <code>key()</code> for the index number of pointer
 *     - <code>rewind()</code> for reset the pointer from the beginning
 *     - <code>valid()</code> for determining is the current pointer valid(/exist)
 *
 *
 *     and also extra helper methods:
 *     - <code>seekTo($position)</code>
 *     - <code>hasNext()</code>
 *     - <code>totalRecord()</code>
 *     - <code>totalPage()</code>
 *     - <code>currentPage()</code>
 *     - <code>all($callable, $offset, $total)</code> for fetching all data with a callable function
 *     - <code>map($callable, $offset, $total)</code> for mapping all data with a callable function
 *
 */
class QueryIterator implements \Iterator
{
    private $query;
    private $total_page = 0;
    private $total_record= 0;
    private $page= 0;
    private $paged_index= 0;
    private $index = 0;
    
    private $entities;
    private $entity;
    private $started = false;

    private $driver = null;
    private $req_offset = 0;
    private $req_total = -1;
    
    /**
     * Constructor
     *
     * @param      IQueryDataDriver  $driver      The driver
     * @param      <type>            $query       The query object. It will be cloned.
     * @param      integer           $req_offset  The offset
     * @param      <type>            $req_total   The request total
     * @param      integer           $per_page    The per page
     */
    public function __construct(IQueryDataDriver $driver, $query, $req_offset = 0, $req_total = -1, $per_page = 2000)
    {
        $this->driver = $driver;
        $this->query = clone $query;
        $this->req_offset = $req_offset;
        $this->req_total = $req_total;
        $this->per_page = $per_page;

        // Log::write('debug', 'QueryIterator@'.__LINE__.': '.print_r(['req_offset'=>$this->req_offset,'req_total'=>$this->req_total, 'per_page'=>$this->per_page], true));
    }

    /**
     * { function_description }
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function totalRecord()
    {
        $this->start();
        return $this->total_record;
    }

    /**
     * { function_description }
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public function currentPage()
    {
        return $this->page;
    }

    /**
     * The number of total page by requested amount.
     *
     * @return     integer The number of total page by requested amount.
     */
    public function totalPage()
    {
        return $this->total_page;
    }

    /**
     * Start to load data from database
     *
     * @return     self  The instance itself for execute command in chain style.
     */
    public function start()
    {
        if ($this->started) {
            return $this;
        }

        // If there are any range is asking
        if ($this->req_offset > 0) {
            $this->query->offset($this->req_offset);
        }

        if ($this->req_total > 0) {
            $this->query->limit($this->req_total);
        }

        $this->total_record = $this->driver->count($this->query);
        $this->index = $this->req_offset -1;
        $this->page = 0;
        $this->total_page = 0;

        $this->started = true;


        $this->updatePagedResult();

        // $this->dispatch('change', [ $this]);

        return $this;
    }
 
    /**
     * Moving the pointer to the beginning position
     *
     * @return     self The instance itself
     */
    public function rewind()
    {
        if (!$this->started) {
            $this->start();
        }

        $this->index = $this->req_offset -1;

        $this->updatePagedResult();

        // $this->dispatch('change', [ $this]);

        return $this;
    }

    /**
     * Reset the pointer and temporary read data.
     *
     * @return     self  ( description_of_the_return_value )
     */
    public function reset()
    {
        unset($this->entity);
        unset($this->entities);
        $this->started = false;
        $this->index = -1;
        $this->total_record = 0;
        $this->entity = null;
        $this->entities = [];
        $this->page = 0;
        $this->total_page = 0;

        return $this;
    }

    /**
     * { function_description }
     *
     * @param      integer  $index  The index
     *
     * @return     <type>   ( description_of_the_return_value )
     */
    public function seekTo($index = 0)
    {
        if (!$this->started) {
            return $this;
        }

        $this->index = $index;

        $this->updatePagedResult();

        // $this->dispatch('change', [ $this]);

        return $this;
    }

    /**
     * { function_description }
     *
     * @return     mixed  Database fetched context. Data type depends on
     */
    public function current()
    {
        return $this->entity;
    }

    /**
     * Getting the pointer index number.
     *
     * @return     integer  The index number of the pointer
     */
    public function key()
    {
        return $this->index;
    }

    /**
     * Moving the pointer to next
     *
     * @return     QueryIterator  The instance itself
     */
    public function next()
    {
        if (!$this->started) {
            return $this;
        }

        ++$this->index;
        $this->updatePagedResult();

        // Log::write('debug', __METHOD__.': info='.print_r([
        //     'index'=>$this->index,
        //     'paged_index'=>$this->paged_index,
        //     'page'=>$this->page,
        //     'total_page'=>$this->total_page,
        //     'total_record'=>$this->total_record,
        // ], true));
        
        return $this;
    }

    /**
     * Determines if it has next.
     *
     * @return     boolean  True if has next, False otherwise.
     */
    public function hasNext()
    {
        return $this->index + 1 - $this->req_offset < $this->total_record &&  $this->total_record >0;
    }

    /**
     * Determine is the current pointer valid.
     *
     * @return     boolean  Return true if the pointer is valid.
     */
    public function valid()
    {
        return isset($this->entities[$this->paged_index]);
    }

    /**
     * Query each entity from database. Query fetch is paged for large amount
     * records.
     *
     * Be careful: Run an extra map/all method within the given callable function may cause large memory allocation.
     *
     * @param      mixed          $callbable        The callbable function,
     *                                              required
     * @param      integer        $requestedOffset  The requested offset
     * @param      integer        $requestedTotal   The requested total
     * @param      integer        $perPage          The per page
     *
     * @throws     Exception      (description)
     *
     * @return     QueryIterator  The instance itself
     */
    public function all($callbable, $requestedOffset = -1, $requestedTotal = -1, $perPage = -1)
    {
        if (!is_callable($callbable)) {
            throw new \Exception('Callable function is required.');
        }

        $iterator = new QueryIterator($this->driver, $this->query, $requestedOffset> 0 ?  $requestedOffset : $this->req_offset, $requestedTotal> 0 ? $requestedTotal: $this->req_total, $perPage > 0 ? $perPage : $this->per_page);

        $iterator->start();
        $total = $iterator->totalRecord() ;
        
        while ($iterator->hasNext()) {
            $iterator->next();

            $index = $iterator->key();
            $entity = $iterator->current();

            call_user_func_array($callbable, [$index, $entity, $total, $requestedOffset, $requestedTotal]);
        };

        $iterator->reset();
        unset($iterator);

        return $this;
    }
    /**
     * Queue each entity and map into an array from database. Query fetch is
     * paged for large amount records.
     *
     * Be careful: Run an extra map/all method within the given callable function may cause large memory allocation.
     *
     * @param      mixed     $mappingCallback  The mappingCallback function that
     *                                         passing arguments from iterator
     *                                         for index, entity, total
     * @param      integer   $requestedOffset  The requested offset
     * @param      integer   $requestedTotal   The requested total
     * @param      integer   $perPage          The per page
     *
     * @return     array     An array storing all fetched data.
     */
    public function map($mappingCallback = null, $requestedOffset = -1, $requestedTotal = -1, $perPage = -1)
    {
        $output = [];

        // Clone the object from Iterator
        $iterator = new QueryIterator($this->driver, $this->query, $requestedOffset> 0 ?  $requestedOffset : $this->req_offset, $requestedTotal> 0 ? $requestedTotal: $this->req_total, $perPage > 0 ? $perPage : $this->per_page);

        $iterator->start();
        $total = $iterator->totalRecord() ;

        while ($iterator->hasNext()) {
            $iterator->next();

            $index = $iterator->key();
            $entity = $iterator->current();
            
            $mappedItem = $entity;
        
            // If that is callable, run the customized mapping function in external callback
            if (is_callable($mappingCallback)) {
                $mappedItem = call_user_func_array($mappingCallback, [$index, $entity, $total, $requestedOffset , $requestedTotal]);
            }
            $output[] = $mappedItem;
        };
        
        $iterator->reset();
        unset($iterator);

        return $output;
    }

    /**
     * { function_description }
     */
    protected function updateCounter()
    {
        $this->total_page = ceil($this->total_record / $this->per_page);
        $this->page = floor($this->index / $this->per_page) + 1;
        $this->paged_index = $this->index - ($this->page - 1) * $this->per_page - $this->req_offset;
    }

    /**
     * { function_description }
     */
    protected function updatePagedResult()
    {
        $old_page = $this->page;

        $this->updateCounter();

        // var_dump([__METHOD__, $this->page, $this->total_page, $this->total_record, $this->index, $this->paged_index]);

        if ($this->page != $old_page) {
            $this->fetchEntities();
        }

        unset($this->entity);
        $this->entity = null;
        if (isset($this->entities[$this->paged_index])) {
            $this->entity = $this->entities[$this->paged_index];
        }
    }

    /**
     * Fetches entities.
     */
    protected function fetchEntities()
    {
        unset($this->entities);

        $start_offset =  $this->req_offset   + ($this->page - 1) * $this->per_page;
        $end_offset = $start_offset + $this->per_page;

        // Log::write('debug', 'QueryIterator@'.__LINE__.': '.print_r([
        //     'req_offset'=>$this->req_offset,
        //     'req_total'=>$this->req_total,
        //     'start_offset'=>$start_offset,
        //     'end_offset'=>$end_offset,
        //     'total_record'=>$this->total_record,
        // ], true));
        
        // if the paged range is larger than request, use requested amount for last page
        if ($this->req_total > 0 && $end_offset - $this->req_offset > $this->req_total) {
            $end_offset = $start_offset + $this->req_total;
        }


        // Log::write('debug', 'QueryIterator@'.__LINE__.': '.print_r([
        //     'req_offset'=>$this->req_offset,
        //     'req_total'=>$this->req_total,
        //     'start_offset'=>$start_offset,
        //     'end_offset'=>$end_offset,
        //     'total_record'=>$this->total_record,
        // ], true));

        $this->entities = $this->driver->fetch($this->query, $start_offset, $end_offset);
    }
}
