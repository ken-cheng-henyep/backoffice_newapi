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
use App\Lib\TransactionSearchResult;
use App\Lib\Queue\QueryHelper;
use App\Lib\JobMetaHelper;

class ReconciliationExportTask extends QueueTask
{
    /**
     * { function_description }
     *
     * @param      <type>   $job    The job
     *
     * @return     boolean  Return true if the process is completed.
     */
    public function run($job)
    {
        $job_id = $job->data('job_id');
        try {
            $this->log("#JobStart($job_id)".':'.self::memoryUsage(), 'info');

            $job_meta = JobMetaHelper::getMeta($job_id);
            $job_data = $job_meta['data'];
            

            
            $this->log("#JobEnd($job_id)".':'.self::memoryUsage(), 'info');
        } catch (Exception $exp) {
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
            return false;
        }
        
        return true;
    }
}
