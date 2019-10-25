<?php

namespace App\Lib\Query;

use Cake\Log\Log;

/**
 * A class for generate SQL string from SQLBuilder\Query and fetching data with IQueryDataDriver.
 * It was used to solve the problem when requesting a huge amount data from database.
 *
 * It also provide quicker way to mapping / walk around all data from database.
 *
 * It's using for excel export task/method.
 */
class QueryHelper
{

    public static $driver ;
    
    /**
     * Create an iterator instance for fetching data. QueryHelper::driver is required to  assigned before execute this method
     *
     * @param      <type>         $query            The query
     * @param      integer        $requestedOffset  The requested offset
     * @param      integer        $requestedTotal   The requested total
     * @param      integer        $perPage          The amount of each page requested.
     *
     * @return     QueryIterator  An iterator instance
     */
    public static function iterator($query, $requestedOffset = 0, $requestedTotal = -1, $perPage = 3000)
    {

        $iterator = new QueryIterator(self::$driver, $query, $requestedOffset, $requestedTotal, $perPage);
        return $iterator;
    }
    /**
     * Query each entity from database. Query fetch is paged for large amount
     * records
     *
     * @param      <type>     $query            The query
     * @param      <type>     $callbable        The callbable
     * @param      integer    $requestedOffset  The requested offset
     * @param      integer    $requestedTotal   The requested total
     * @param      integer    $perPage          The per page
     *
     * @throws     Exception  (description)
     */
    public static function all($query, $callbable = null, $requestedOffset = 0, $requestedTotal = -1, $perPage = 2000)
    {
        if (!is_callable($callbable)) {
            throw new \Exception('Callable function is required.');
        }

        $iterator = new QueryIterator(self::$driver, $query, $requestedOffset, $requestedTotal, $perPage);

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
    }
    /**
     * Queue each entity and map into an array from database. Query fetch is
     * paged for large amount records
     *
     * @param      <type>   $query            The query
     * @param      <type>   $mappingCallback  The mappingCallback function that
     *                                        passing arguments from iterator
     *                                        for index, entity, total
     * @param      integer  $requestedOffset  The requested offset
     * @param      integer  $requestedTotal   The requested total
     * @param      integer  $perPage          The per page
     *
     * @return     array    An array storing all data (Non-entity format)
     */
    public static function map($query, $mappingCallback = null, $requestedOffset = 0, $requestedTotal = -1, $perPage = 2000)
    {
        $output = [];

        // Run the `all` method to fetch entities
        
        $iterator = new QueryIterator(self::$driver, $query, $requestedOffset, $requestedTotal, $perPage);


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
}
