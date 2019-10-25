<?php 

namespace App\Tasks\Writers;

use WC\Query\QueryHelper;
use WC\Query\Resultset;

use \PayConnectorAPI;

class TransactionLogWriter extends ContentWriter 
{
    /**
     * Saving the output content by path
     *
     * @param  string $output
     * 
     * @return void
     */
    public function save($output)
    {
        $job_data = $this->data;
        
        $offset = 0;
        $limit = 2000;


        $pc_api = new \PayConnectorAPI(false);
        $total_record = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, 0, 0, true);

        if (!is_int($total_record)) {
            throw new \Exception('Total record number is not integer');
        }

        if ($total_record < 1) {
            throw new \Exception('No data found');
        }

        $this->started();
        
        $job_data['total_record'] = $total_record;
        $job_data['process_offset'] = 0;

        $num_loop  =0;
        $fetched_data = null;
        // Start the loop for fetching data by batch. (For example, 3000 per patch)
        do {
                        
            $fetched_data = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, $limit, $offset);
        
            $total_found  = count($fetched_data);

            $process_offset = $job_data['process_offset'] = ($offset + $total_found);
            $this->data = $job_data;
            $this->dataChange($job_data);

            if (!is_array($fetched_data) || $total_found<1) {
                break;
            }

            // Re-format for the data set
            wcSetNumberFormat($fetched_data);
            
            // Append the content into excel file
            $job_data['output'] = fromArrayToSpoutExcel(['Data'=>$fetched_data,], $output, '.xlsx');


            $this->progress(($process_offset*1.0 / $total_record) * 1);
            
            $this->data = $job_data;
            $this->dataChange($job_data);


            $offset += $total_found;

            // $this->log('Job['.$job_id.']'.' output = '.$output, 'debug');
            $this->cycled();

            // If the process does not end and already start for while, pause few seconds
            if (!$this->cancelled) {
                if ($num_loop >0 && $num_loop %5 ==0 && $process_offset*1.0 / $total_record < 0.8) {
                    $this->sleep();
                }
            }

            $num_loop ++;

        } while ($this->cancelled);

        // If no record fetched, return false
        if ($num_loop < 1) {
            return false;
        }

        $this->progress(1);
        $this->ended();

        return !$this->cancelled;
    }
}