<?php

class TransactionLogWriter
{
    public function run($job_data, $responsor)
    {

        // Getting the total number
        $total_record = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, 0, 0, true);

        if (!is_int($total_record)) {
            $responsor->failure('Total record number is not integer');
            return false;
        }

        if ($total_record < 1) {
            $responsor->failure('No data found');
            return false;
        }


        if (empty($job_data['startdate'])) {
            $responsor->failure('startdate is required');
            return false;
        }

        if (empty($job_data['enddate'])) {
            $responsor->failure('enddate is required');
            return false;
        }

        JobMetaHelper::markStarted($job_id);


        $job_data['total_record'] = $total_record;
        $job_data['process_offset'] = 0;

        JobMetaHelper::updateData($job_id, $job_data);


        $this->log(__METHOD__.'@'.__LINE__.':'."TotalRecord:".$total_record, 'info');


        $offset = 0;
        $limit = 3000;

        do {
            $this->log(__METHOD__.'@'.__LINE__.':'.self::memoryUsage(), 'debug');

            $fetched_data = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, $limit, $offset);
        
            $total_found  = count($fetched_data);

            if (!is_array($fetched_data) || $total_found<1) {
                break;
            }

            $this->log(__METHOD__.'@'.__LINE__.':'.self::memoryUsage(), 'debug');

            wcSetNumberFormat($fetched_data);
            // $output = $pc_api->saveToExcel2($data, $xlsfile, $num_loop> 0 || count($data) >= 10000 ? '.csv':'.xlsx');
            $output = $job_data['output'] = $pc_api->saveToExcel2($fetched_data, $job_data['xlsfile'].'_j'.$job_id, '.xlsx');


            $this->log(__METHOD__.'@'.__LINE__.':'."Offset[$offset/$total_record] count:".$total_found, 'info');

            $process_offset = $job_data['process_offset'] = ($offset + $total_found);

            JobMetaHelper::updateProgress($job_id, ($process_offset*1.0 / $total_record) * 1);
            JobMetaHelper::updateData($job_id, $job_data);


            $offset += $total_found;
            $num_loop ++;
            $this->log(__METHOD__.'@'.__LINE__.': output = '.$output, 'debug');

            // If the process does not end and already start for while, pause few seconds
            if ($num_loop >0 && $num_loop %2 ==0 && $process_offset*1.0 / $total_record < 0.8) {
                $this->log(__METHOD__.'@'.__LINE__.': Sleep for while', 'debug');
                sleep(3);
            }

            // $this->log(__METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB', 'debug');
        } while (!empty($fetched_data));
    }
}
