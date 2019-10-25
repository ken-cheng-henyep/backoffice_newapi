<?php
namespace App\Lib\Query;

interface IQueryDataDriver
{
    /**
     * Count the total rows for the query
     *
     * @param      <type>  $query  The query
     *
     * @return     integer The number of total records
     */
    public function count($query) ;

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
    public function fetch($query, $page = 0, $perPage = 0);
}
