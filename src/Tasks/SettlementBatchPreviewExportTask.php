<?php

namespace App\Tasks;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;

use Cake\Console\Shell;

use App\Tasks\Writers\ContentWriter;
use App\Lib\JobMetaHelper;
use App\Lib\CakeDbConnector;
use App\Lib\CakeLogger;

use WC\Backoffice\SettlementService;
use WC\Backoffice\MerchantService;
use WC\Backoffice\TransactionQueryBuilder;
use WC\Query\QueryHelper;

/**
 * A shell command that exporting data
 */
class SettlementBatchPreviewExportTask extends QueueTask
{
    public static $pc_api;
    public static $settlementService;
    public static $merchantService;
    public static $initiallized = false;
    public static $driver;
    public static $SettlementBatch;

    /**
     * Execute the queue job.
     *
     * @param QueueJob $job The object storing queue job information.
     * 
     * @return void
     */
    public function run($job)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        // $Value = $job->data('VarilableName');

        $job_id = $job->data('job_id');
        $this->log("Job[$job_id]#Run".':'.self::memoryUsage(), 'info');

        // Setup database connection and shared in the memory
        if (!self::$initiallized) {
            
            self::$pc_api = new \PayConnectorAPI(false);
            
            
            $conn = ConnectionManager::get('default');
            
            // Setup driver for query helper.
            CakeDbConnector::setShared($conn);

            
            self::$settlementService = new SettlementService(QueryHelper::$db);
            self::$settlementService->setLogger(CakeLogger::shared());
            self::$merchantService = new MerchantService(QueryHelper::$db);

            self::$SettlementBatch = TableRegistry::get('SettlementBatch');

            self::$initiallized = true;
        }

        try {
            $job_meta = JobMetaHelper::getMeta($job_id);
            $job_data = $job_meta['data'];

            $config = isset($job_data['config']) && is_array($job_data['config']) ? $job_data['config'] : [];
            $params = isset($job_data['params']) && is_array($job_data['params']) ? $job_data['params'] : [];
            $batchId = assume($job_data, 'batchId', null);
            $state = assume($job_data, 'state', 'OPEN');

            $this->log("Job[$job_id]".'#Data:'.print_r($job_data, true), 'debug');
            
            // Temporary path.
            $xlsfile = sprintf('xls/SettlementBatchPreview-TMP-%s', time());

            // Export as excel
            if (!empty($batchId)) {
                $batchRow = self::$SettlementBatch->get($batchId);
                
                $masterMerchant = self::$merchantService->getMasterMerchant($batchRow['merchant_id']);
                $batchData = self::$settlementService->batchBuilder->load($batchRow, $masterMerchant);
                
                $this->log("Job[$job_id]".'# Loaded Batch Data with row: '.print_r($batchRow, true), 'debug');
    
                if ($state == 'SETTLED') {
                    $xlsfile = sprintf('xls/SettlementBatch-%s-%s', $batchId, time());
                } else if ($state == 'OPEN') {
                    $xlsfile = sprintf('xls/SettlementBatchPreview-%s-%s', $batchId, time());
                } else {
                    throw new \Exception('Unsupported state.');
                }
            } else {

                if (empty($params['start_date']) ) {
                    $this->log("Job[$job_id]Process failure - Start date is required'", 'error');
                    throw new \Exception('Start date is required');
                }

                if (empty($params['end_date']) ) {
                    $this->log("Job[$job_id]Process failure - End date is required'", 'error');
                    throw new \Exception('End date is required');
                }
                $xlsfile = sprintf('xls/SettlementBatchPreview-%sto%s-%s', $params['start_date'], $params['end_date'], time());

                $this->log("Job[$job_id]".'# Creating Batch Data with params: '.print_r($params, true), 'debug');

                $masterMerchant = self::$merchantService->getMasterMerchant($config['merchant_id']);
                $batchData = self::$settlementService->batchBuilder->create($masterMerchant, $params);
            }

            if (empty($batchData)) {
                $this->log('Empty batch data for batchRow: '.print_r($batchRow, true), 'error');
                throw new \Exception('Empty batch data for batchRow');
            }

            $this->log("Job[$job_id]".'#JobStart:'.self::memoryUsage(), 'debug');
            JobMetaHelper::markStarted($job_id);

            $this->log("Job[$job_id]".'#beforeResultToExcel:'.self::memoryUsage(), 'debug');

            $writer = new Writers\SettlementBatchWriter($job_data);
            $writer->config(self::$settlementService, $batchData);
            $writer->on(Writers\ContentWriter::EVENT_PROGRESS, function ($value) use ($job_id, $job_data) {
                JobMetaHelper::updateProgress($job_id, $value);
            })
            ->on(Writers\ContentWriter::EVENT_DATA_CHANGE, function ($newData) use ($job_id, $job_data) {
                JobMetaHelper::updateData($job_id, $newData);
            })
            ->on(Writers\ContentWriter::EVENT_CYCLE, function () use ($job_id, $writer) {

                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    $this->log('Job['.$job_id.']'."cancelled.", 'info');
                    $writer->cancel();
                }
            });
            if ($writer->save($xlsfile)) {

                JobMetaHelper::markComplete($job_id);

                unset($query, $result, $job_data, $params);

                $this->log("Job[$job_id]".'#JobEnd:'.self::memoryUsage(), 'debug');
                $this->log("Job[$job_id]Process completed", 'info');

                return true;
            }
            
            $this->log("Job[$job_id]".'#markedFailure:'.self::memoryUsage(), 'debug');
            JobMetaHelper::markFailure($job_id, 'Return false.');

        } catch (Exception $exp) {
            $this->log("Job[$job_id]Process failure", 'error');
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
        }
        unset($query, $result, $job_data, $params);

        return false;
    }
}
