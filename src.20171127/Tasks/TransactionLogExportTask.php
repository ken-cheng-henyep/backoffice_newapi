<?php

namespace App\Tasks;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Common\Type;

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

use Cake\Console\Shell;

use App\Lib\TransactionFinder;
use App\Lib\TransactionSearchQuery;
use App\Lib\Queue\QueryHelper;
use App\Lib\JobMetaHelper;

class TransactionLogExportTask extends QueueTask
{
    public function run($job)
    {
        $job_id = $job->data('job_id');
        $this->log("Job[$job_id]#Run".':'.self::memoryUsage(), 'info');

        try {
        // $Value = $job->data('VarilableName');

            $job_meta = JobMetaHelper::getMeta($job_id);
            $job_data = $job_meta['data'];

            $pc_api = new \PayConnectorAPI(false);

            $fetched_data = null;

            $num_loop = 0;


                $this->log('Job['.$job_id.']'."data=".print_r($job_data, true), 'debug');

        // Getting the total number
            $total_record = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, 0, 0, true);

            if (!is_int($total_record)) {
                $this->log('Job['.$job_id.']'."Process failure: Total record number is not integer", 'debug');
                JobMetaHelper::markFailure($job_id, 'Total record number is not integer');
                return false;
            }

            if ($total_record < 1) {
                $this->log('Job['.$job_id.']'."Process failure: No data found", 'debug');
                JobMetaHelper::markFailure($job_id, 'No data found');
                return false;
            }


            if (empty($job_data['startdate'])) {
                $this->log('Job['.$job_id.']'."Process failure: startdate is required", 'debug');
                JobMetaHelper::markFailure($job_id, 'startdate is required');
                return false;
            }

            if (empty($job_data['enddate'])) {
                $this->log('Job['.$job_id.']'."Process failure: enddate is required", 'debug');
                JobMetaHelper::markFailure($job_id, 'enddate is required');
                return false;
            }

            JobMetaHelper::markStarted($job_id);


            $job_data['total_record'] = $total_record;
            $job_data['process_offset'] = 0;

            JobMetaHelper::updateData($job_id, $job_data);


            $this->log('Job['.$job_id.']'."TotalRecord:".$total_record, 'info');


            $offset = 0;
            $limit = 2000;

            // Start the loop for fetching data by batch. (For example, 3000 per patch)
            do {
                $this->log('Job['.$job_id.']'.' memoryUsage:'.self::memoryUsage(), 'debug');

                $fetched_data = $pc_api->getDatabaseTransactions($job_data['startdate'], $job_data['enddate'], isset($job_data['mid']) ?  $job_data['mid'] : '', isset($job_data['status']) ? $job_data['status'] : null, $limit, $offset);
            
                $total_found  = count($fetched_data);

                if (!is_array($fetched_data) || $total_found<1) {
                    break;
                }

                $this->log('Job['.$job_id.']'.' '.self::memoryUsage(), 'debug');

                // Re-format for the data set
                wcSetNumberFormat($fetched_data);
                

                $filename = $job_data['xlsfile'].'_j'.$job_id;
                $basef1 = basename($filename);
                $basef2 = str_replace(['/',' '], '-', $basef1);

                // Prepare the file name (with relative path)
                $filename = str_replace($basef1, $basef2, $filename);

                // Append the content into excel file
                $output = $job_data['output'] = fromArrayToSpoutExcel(['Data'=>$fetched_data,], $filename, '.xlsx');

                $this->log('Job['.$job_id.']'."Offset[$offset/$total_record] count:".$total_found, 'info');

                $process_offset = $job_data['process_offset'] = ($offset + $total_found);


                // Update the progress
                JobMetaHelper::updateProgress($job_id, ($process_offset*1.0 / $total_record) * 1);
                JobMetaHelper::updateData($job_id, $job_data);


                $offset += $total_found;
                $num_loop ++;

                $this->log('Job['.$job_id.']'.' output = '.$output, 'debug');

                // If the process does not end and already start for while, pause few seconds
                if ($num_loop >0 && $num_loop %5 ==0 && $process_offset*1.0 / $total_record < 0.8) {
                    $this->log('Job['.$job_id.']'.' Sleep for while after 5 interaction.', 'debug');
                    sleep(3);

                    $this->log('Job['.$job_id.']'.' '.self::memoryUsage(), 'debug');
                }


                // Check if the status correct
                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    @unlink($output);
                    $this->log('Job['.$job_id.']'.' CancelledByStatusInvalid, status='.JobMetaHelper::getStatus($job_id).'.');
                    return false;
                }
            } while (!empty($fetched_data));


            JobMetaHelper::updateProgress($job_id, ($process_offset*1.0 / $total_record) * 1);
            JobMetaHelper::markComplete($job_id);
       
            $this->log('Job['.$job_id.']'."Process completed", 'debug');
        } catch (Exception $exp) {
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
        }
        
        return true;
    }

    protected function processor()
    {
    }
}
