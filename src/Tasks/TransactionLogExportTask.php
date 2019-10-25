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


use App\Lib\JobMetaHelper;
use App\Lib\CakeDbConnector;

use WC\Query\QueryHelper;
use WC\Backoffice\TransactionQueryBuilder;

class TransactionLogExportTask extends QueueTask
{
    public static $pc_api;
    public static $initiallized = false;
    public static $driver;

    public function run($job)
    {
        $job_id = $job->data('job_id');
        $this->log("Job[$job_id]#Run".':'.self::memoryUsage(), 'info');


        if (!self::$initiallized) {

            // Load Models.
            $this->loadModel("Merchants");
            
            self::$pc_api = new \PayConnectorAPI(false);
            
            $conn = ConnectionManager::get('default');
            
            // Setup driver for query helper.
            CakeDbConnector::setShared($conn);
            
            self::$initiallized = true;
        }
            
        try {
        // $Value = $job->data('VarilableName');

            $job_meta = JobMetaHelper::getMeta($job_id);
            $job_data = $job_meta['data'];


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


            $writer = new Writers\TransactionLogWriter($job_data);
            $writer->on(Writers\ContentWriter::EVENT_DATA_CHANGE, function ($newData) use ($job_id, $job_data, $writer) {
                $job_data = $newData;
                
                $offset = $newData['process_offset'];
                $total_record = $newData['total_record'];

                $this->log('Job['.$job_id.']'."NewData:".print_r($newData, true), 'info');

                JobMetaHelper::updateData($job_id, $newData);
            })
            ->on(Writers\ContentWriter::EVENT_PROGRESS, function ($value) use ($job_id, $writer) {
                JobMetaHelper::updateProgress($job_id, $value);

            })
            ->on(Writers\ContentWriter::EVENT_CYCLE, function () use ($job_id, $writer) {
                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    $this->log('Job['.$job_id.']'."cancelled.", 'info');
                    $writer->cancel();
                }
            });
            
            $filename = $job_data['xlsfile'].'_j'.$job_id;
            $basef1 = basename($filename);
            $basef2 = str_replace(['/',' '], '-', $basef1);

            // Prepare the file name (with relative path)
            $filename = str_replace($basef1, $basef2, $filename);

            if ($writer->save($filename)) {
                JobMetaHelper::updateProgress($job_id, 1);
                JobMetaHelper::markComplete($job_id);

                

                $this->log('Job['.$job_id.']'."Process completed", 'debug');
                $this->log('Job['.$job_id.']'."Data: ".print_r($job_data, true), 'debug');
                return true;
            }
       
            $this->log('Job['.$job_id.']'."Process is not completed", 'debug');
        } catch (\Exception $exp) {
            $this->log('Job['.$job_id.']'."Exception: ".$exp->getMessage(), 'error');
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
        }
        
        return false;
    }
}
