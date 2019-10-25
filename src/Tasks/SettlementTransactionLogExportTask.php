<?php

namespace App\Tasks;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;


use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Common\Type;

use Cake\Console\Shell;


use \App\Lib\JobMetaHelper;
use \App\Lib\CakeDbConnector;

use WC\Query\QueryHelper;
use WC\Backoffice\TransactionQueryBuilder;
use WC\Backoffice\MerchantService;

class SettlementTransactionLogExportTask extends QueueTask
{
    public static $pc_api;
    public static $txFinder;
    public static $merchantService;
    public static $initiallized = false;
    public static $driver;

    public function run($job)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        // $Value = $job->data('VarilableName');


        $job_id = $job->data('job_id');
        $this->log("Job[$job_id]#Run".':'.self::memoryUsage(), 'info');


        if (!self::$initiallized) {

            // Load Models.
            $this->loadModel("Merchants");
            
            self::$pc_api = new \PayConnectorAPI(false);
            
            $conn = ConnectionManager::get('default');
            
            // Setup driver for query helper.
            CakeDbConnector::setShared($conn);
            
            self::$txFinder = new TransactionQueryBuilder();
            self::$merchantService = new MerchantService(QueryHelper::$db);

            self::$initiallized = true;
        }

        try {
            $job_meta = JobMetaHelper::getMeta($job_id);
            $job_data = $job_meta['data'];

            $params = $job_meta['data']['params'];


            if (empty($params['start_date']) && empty($params['start_date_ts'])) {
                $this->log("Job[$job_id]Process failure - Start date is required'", 'error');
                JobMetaHelper::markFailure($job_id, 'Start date is required');
                return false;
            }

            if (empty($params['end_date']) && empty($params['end_date_ts'])) {
                $this->log("Job[$job_id]Process failure - End date is required'", 'error');
                JobMetaHelper::markFailure($job_id, 'End date is required');
                return false;
            }

            $startDate = new \DateTime( !empty($params['start_date_ts']) ? date('Y-m-d H:i:s', $params['start_date_ts']/1000) : $params['end_date'] );
            $endDate = new \DateTime( !empty($params['end_date_ts']) ? date('Y-m-d H:i:s', $params['end_date_ts']/1000) : $params['end_date'] );

            $this->log("Job[$job_id]".'#JobStart:'.self::memoryUsage(), 'debug');
            JobMetaHelper::markStarted($job_id);

            $query = self::$txFinder->query(
                $params,
                null,
                [
                    ['field'=>'state_time','dir'=>'ASC'],
                ]
            );

            $total = QueryHelper::$db->count($query);
            // $total = $query->count();

            if ($total < 1) {
                unset($query);
                unset($result);
                unset($job_data);
                unset($params);
                JobMetaHelper::markFailure($job_id, 'No record found.');
                return false;
            }
            
            $writer = new Writers\SettlementTransactionLogWriter($job_data);
            $writer->config(QueryHelper::$db, self::$merchantService, $query, $startDate, $endDate);
            $writer->on(Writers\ContentWriter::EVENT_DATA_CHANGE, function ($newData) use ($job_id, $job_data, $writer) {
                JobMetaHelper::updateData($job_id, $newData);
            })
            ->on(Writers\ContentWriter::EVENT_PROGRESS, function ($value) use ($job_id, $writer) {
                $this->log('Job['.$job_id.']'."progress=".number_format($value*100, 2, '.', ',').'%', 'info');
                JobMetaHelper::updateProgress($job_id, $value);
            })
            ->on(Writers\ContentWriter::EVENT_CYCLE, function () use ($job_id, $writer) {

                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    $this->log('Job['.$job_id.']'."cancelled.", 'info');
                    $writer->cancel();
                }
            });
            
            // Export as excel
            $xlsfile = sprintf('xls/SettlementTransaction-%s', time());

            if ($writer->save($xlsfile)) {

                unset($query);
                unset($result);
                unset($job_data);
                unset($params);
                $this->log("Job[$job_id]".'#JobEnd:'.self::memoryUsage(), 'debug');
                $this->log("Job[$job_id]Process completed", 'info');

                JobMetaHelper::markComplete($job_id);

                return true;
            }
             
            $this->log("Job[$job_id]".'#markedNonProcessing:'.self::memoryUsage(), 'debug');
            JobMetaHelper::markFailure($job_id, 'Incompleted.');
            
        } catch (Exception $exp) {
            unset($query);
            unset($result);
            unset($job_data);
            unset($params);
            
            $this->log("Job[$job_id]Process failure", 'error');
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
        }

        return false;
    }
}
