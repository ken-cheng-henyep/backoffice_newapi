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

use \App\Lib\TransactionFinder;
use \App\Lib\TransactionSearchQuery;
use \App\Lib\JobMetaHelper;

use \App\Lib\Query\QueryHelper;
use \App\Lib\Query\CBDataDriver;

class SettlementTransactionLogExportTask extends QueueTask
{
    public static $pc_api;
    public static $searchTool;

    public function run($job)
    {
        ini_set('memory_limit', '512M');
        // $Value = $job->data('VarilableName');

        $job_id = $job->data('job_id');
        $this->log("Job[$job_id]#Run".':'.self::memoryUsage(), 'info');


        if (self::$pc_api == null) {
            self::$pc_api = new \PayConnectorAPI(false);
        }
        if (self::$searchTool == null) {
            $conn = ConnectionManager::get('default');
            
            self::$searchTool = new TransactionFinder($conn);

            // Setup driver for query helper.
            QueryHelper::$driver = CBDataDriver::shared($conn);
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

            $this->log("Job[$job_id]".'#JobStart:'.self::memoryUsage(), 'debug');
            JobMetaHelper::markStarted($job_id);

            $result = self::$searchTool->query(
                $params,
                null,
                [
                    ['field'=>'state_time','dir'=>'ASC'],
                ]
            );
            $query = $result->query;


            $total = CBDataDriver::shared()->count($query);
            // $total = $query->count();

            if ($total < 1) {
                unset($query);
                unset($result);
                unset($job_data);
                unset($params);
                JobMetaHelper::markFailure($job_id, 'No record found.');
                return false;
            }

            // Export as excel
            $xlsfile = sprintf('xls/SettlementTransaction-%s', time());

            $this->log("Job[$job_id]".'#beforeResultToExcel:'.self::memoryUsage(), 'debug');
            $result = $this->resultToExcel($job_id, $job_data, $params['merchantgroups'], $result, $xlsfile);

            if (!$result) {
                 $this->log("Job[$job_id]".'#markedNonProcessing:'.self::memoryUsage(), 'debug');
                return false;
            }

            unset($query);
            unset($result);
            unset($job_data);
            unset($params);

            $this->log("Job[$job_id]".'#JobEnd:'.self::memoryUsage(), 'debug');
           
            $this->log("Job[$job_id]Process completed", 'info');
        } catch (Exception $exp) {
            unset($query);
            unset($result);
            unset($job_data);
            unset($params);
            
            $this->log("Job[$job_id]Process failure", 'error');
            JobMetaHelper::markFailure($job_id, $exp->getMessage());
        }

        return true;
    }



    /**
     * Writes an excel for merchant.
     *
     * @param      string                           $job_id             The job identifier
     * @param      array                            $job_data           The job data
     * @param      array|string|null                $merchantgroup_ids  The merchantgroup identifiers
     * @param      \App\Lib\TransactionSearchQuery  $result             The result
     * @param      string                           $file               The file
     *                                                                  path
     */
    protected function resultToExcel($job_id, &$job_data, &$merchantgroup_ids, TransactionSearchQuery &$result, $file)
    {

        // Prepare excel file path
        $tpl = ROOT.DIRECTORY_SEPARATOR .'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'transaction_search_template.xlsx';

        $file.= ".xlsx";
        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;


        // prepare local variables
        $merchant_ids =[];
        $data = [];

        // original query object
        $query = $result->query;

        $merchant_accounts = [];

        // If no merchant group selected, assume to select all
        $merchantgroup = null;
        if (!empty($merchantgroup_ids)) {
            if (count($merchantgroup_ids) == '1') {
                $merchantgroup =  self::$searchTool->MerchantGroup->get($merchantgroup_ids[0]);
            }
        }

        $total_record = CBDataDriver::shared()->count($query);

        $job_data['total_record']  = $total_record;
        $job_data['output']  = $file_path;
        JobMetaHelper::updateData($job_id, $job_data);
        // Update the progress
        // $this->QueuedJobs->updateAll([
        //     'data'=> is_array($job_data) ? json_encode($job_data) : null,
        // ], ['id' => $job_id]);


        $this->log('Job['.$job_id.']#Prepared:'.self::memoryUsage(), 'debug');

        // TODO: Using cache storage, some of data cell is lost.
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        $cacheSettings =    array( 'memoryCacheSize ' =>'128MB');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // Open excel file
        $excel = PHPExcel_IOFactory::load($tpl);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        $this->log('Job['.$job_id.']#ExcelTempalteLoaded:'.self::memoryUsage(), 'debug');


        // Sheet 0 - Sales Transaction
        //
        // State Time
        // Trans type
        // Customer
        // Email
        // Account
        // Amount
        // Fee
        // Net Amount
        // Currency
        // FX Rate
        // Converted Amount
        // Merchant Ref.
        // Transaction Id
        // Product
        // IP Address
        // Bank
        // Bank Account
        // Verified Name
        // ID Number
        // Mobile
        //
        

        // Query by paging
        //
        //

        $s_query = clone $query;
        $s_query->where(['state'=> 'SALE']);

        $this->log('Job['.$job_id.']#ClonedQuery(Sales):'.self::memoryUsage(), 'debug');


        $sheet = $excel->getSheet(0);
        
        $this->log('Job['.$job_id.']#StartedSheetProcess(Sales):'.self::memoryUsage(), 'debug');

        // Clear the formula if one merchantgroup selected
        if (empty($merchantgroup)) {
            $sheet->setCellValue('K10', '');
        }


        $s_total_record = CBDataDriver::shared()->count($s_query);

        // Update date value in the sales transaction sheet
        $sheet->setCellValue("B3", $result->startDate->format('Y-m-d'));
        $sheet->setCellValue("B4", $result->endDate->format('Y-m-d'));
        $merchant_accounts = [];

        $this->log('Job['.$job_id.']#MiddleSheetProcess(Sales):'.self::memoryUsage(), 'debug');


        $sheet->setCellValue("B2", '-');
        if (!empty($merchantgroup)) {
            $sheet->setCellValue("B2", 'All');
           
            if (!empty($merchantgroup['id'])) {
                $sheet->setCellValue("B2", $merchantgroup['name']);
            }
        }

        $sheet
        ->setCellValue("A13", 'N/A');

        $this->log('Job['.$job_id.']#BeforeRowsPadding(Sales):'.self::memoryUsage(), 'debug');

        $base_row = 7;
        $inserted_rows = 0;
        $sheet->insertNewRowBefore($base_row+1, $s_total_record);


        $this->log('Job['.$job_id.']#BeforeRowsProcess(Sales):'.self::memoryUsage(), 'debug');

        $s_query->orderBy('state_time', 'ASC');
        QueryHelper::all($s_query, function ($idx, $entity, $total) use (&$sheet, $base_row, &$merchant_ids, &$inserted_rows, $s_total_record, $job_id) {

            // $this->log(compact('idx', 'total'), 'debug');
            // $remain_rows = $s_total_record - $idx;
            // $buffered_rows = min(500, $remain_rows);

            // // Markup the total rows for excel
            // if ($inserted_rows < $buffered_rows && $buffered_rows> 0) {
            //     $sheet->insertNewRowBefore($base_row+1+$inserted_rows, $buffered_rows);
            //     $inserted_rows += $buffered_rows;
            // }

            $r = is_object($entity) ?  $entity->toArray() : $entity;

            if (!empty($r['merchant_id']) && !in_array($r['merchant_id'], $merchant_ids)) {
                $merchant_ids[] = $r['merchant_id'];
            }

            $row = $base_row + $idx;

            $sheet
                ->setCellValue("A$row", $r['state_time'])
                ->setCellValue("B$row", $r['state'])
                ->setCellValue("C$row", $r['customer_name'])
                ->setCellValue("D$row", $r['email'])
                ->setCellValueExplicit("E$row", $r['merchant'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("F$row", $r['amount'])
                ->setCellValue("G$row", $r['fee'])
                ->setCellValue("H$row", $r['net_amount'])
                ->setCellValue("I$row", $r['convert_currency'])
                ->setCellValue("J$row", $r['convert_rate'])
                ->setCellValue("K$row", $r['convert_amount'])
                ->setCellValueExplicit("L$row", $r['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("M$row", $r['transaction_id'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("N$row", $r['product'])
                ->setCellValueExplicit("O$row", $r['ip_address'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("P$row", $r['bank_name'])
                ->setCellValue("Q$row", $r['bank_card_number'])
                ->setCellValue("R$row", $r['verified_name'])
                ->setCellValueExplicit("S$row", $r['id_card_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("T$row", $r['mobile_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("U$row", $r['settlement_status'])
                ;
                

            if ($idx > 0 && $idx % 500 == 0) {
                JobMetaHelper::updateProgress($job_id, .5*$idx / $s_total_record);
                $this->log('Job['.$job_id.']#FetchedTransasction(Sales):'.', P:'.$idx .'/'.$s_total_record.', '.self::memoryUsage(), 'debug');
                if ($idx % 1000 == 0) {
                    sleep(1);
                }
                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    return false;
                }
            }
        });

        // Fetch all possible merchant accounts
        if (!empty($merchant_ids) && count($merchant_ids) > 0) {
            $_query =  self::$searchTool->Merchants->find()
            ->join(
                [
                    'mg' => [
                        'table' => 'merchants_group_id',
                        'type' => 'INNER',
                        'conditions' => ['mg.merchant_id = m.id'],
                        ],
                ]
            )
                ->where(['m.id IN'=> $merchant_ids])
                ->order(['mg.id ASC','processor_account_type ASC','createdate ASC'])
            ;

            $merchant_accounts = $_query->toArray();
        }

        $counter_row = 10 + $s_total_record;
        $sheet->getStyle('F'.$counter_row.':K'.$counter_row)->applyFromArray([
            'font'=>[
                'bold'=>true,
            ],
            'number_format'=>[
               'code' =>'#,##0.00_-',
            ],
            'borders'=>[
                'top'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_THIN,
                ],
                'bottom'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_DOUBLE,
                ],
            ]
        ])->getNumberFormat()->applyFromArray([
           'code' =>'#,##0.00_-',
        ]);

        $sheet->getStyle('F7:H'.(7+$s_total_record+3))
            ->getNumberFormat()->applyFromArray([
               'code' =>'#,##0.00_-',
            ]);
        $sheet->getStyle('J7:J'.(7+$s_total_record+3))
            ->getNumberFormat()->applyFromArray([
               'code' =>'#,##0.0000_-',
            ]);
        if (!empty($merchantgroup['id'])) {
            $sheet->setCellValue('F'.$counter_row, '=SUM(F7:F'.(7+$s_total_record).')');
            $sheet->setCellValue('G'.$counter_row, '=SUM(G7:G'.(7+$s_total_record).')');
            $sheet->setCellValue('H'.$counter_row, '=SUM(H7:H'.(7+$s_total_record).')');
            $sheet->setCellValue('K'.$counter_row, '=SUM(K7:K'.(7+$s_total_record).')');
        }

        if ($s_total_record > 0) {
            $sheet->setCellValue('A'.(12 + $s_total_record), 'Transaction Charges:');
            $sheet->getStyle('A'.(12 + $s_total_record))->applyFromArray(['font'=>['bold'=>true]]);

            // Entering merchant Id
            $base_row = 13 + $s_total_record;
            $idx = 0;

            // We use the sorted  data for listing all available merchants.
            foreach ($merchant_accounts as $merchant_info) {
                $row = $base_row + $idx;
                $sheet
                    ->setCellValue("A$row", $merchant_info['name'] .' @ '.number_format($merchant_info['settle_fee'], 2) .'%');

                $idx++;
            }
        } else {
            // Erase invalid content
            $row = 7 ;
            $sheet
                ->setCellValue("A$row", '')
                ->setCellValue("B$row", '')
                ->setCellValue("C$row", '')
                ->setCellValue("D$row", '')
                ->setCellValueExplicit("E$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("F$row", '')
                ->setCellValue("G$row", '')
                ->setCellValue("H$row", '')
                ->setCellValue("I$row", '')
                ->setCellValue("J$row", '')
                ->setCellValue("K$row", '')
                ->setCellValueExplicit("L$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("M$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("N$row", '')
                ->setCellValueExplicit("O$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("P$row", '')
                ->setCellValue("Q$row", '')
                ->setCellValue("R$row", '')
                ->setCellValueExplicit("S$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("T$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("U$row", '');
        }
        
        if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
            return false;
        }

        $this->log('Job['.$job_id.']#SheetCompleted(Sales):'.self::memoryUsage(), 'debug');
        JobMetaHelper::updateProgress($job_id, .55);
        // $writer->save($file_path);

        $merchant_ids = [];
        $merchant_accounts = [];

        $this->log('Job['.$job_id.']#FileWrote(Sales):'.self::memoryUsage(), 'debug');
        JobMetaHelper::updateProgress($job_id, .6);



        // Sheet 1 - Refund Transaction
        //
        //
        //
        
        $s_query = clone $query;
        $s_query->where()->in('state', ['REFUNDED','PARTIAL_REFUND','REFUND_REVERSED','PARTIAL_REFUND_REVERSED']);

        $sheet = $excel->getSheet(1);

        // Clear the formula if one merchantgroup selected
        if (empty($merchantgroup)) {
            $sheet->setCellValue('K10', '');
        }


        $sheet->setCellValue("B2", '-');
        if (!empty($merchantgroup)) {
            $sheet->setCellValue("B2", 'All');
           
            if (!empty($merchantgroup['id'])) {
                $sheet->setCellValue("B2", $merchantgroup['name']);
            }
        }


        // Update date value in the refund transaction sheet
        $sheet->setCellValue("B3", $result->startDate->format('Y-m-d'));
        $sheet->setCellValue("B4", $result->endDate->format('Y-m-d'));


        // Erase invalid text
        $sheet ->setCellValue("A13", 'N/A');

        $s_total_record = CBDataDriver::shared()->count($s_query);
        // $s_total_record = $s_query->count();

        $base_row = 7;
        $inserted_rows = 0;
        QueryHelper::all($s_query, function ($idx, $entity, $total) use (&$sheet, $base_row, &$merchant_ids, &$inserted_rows, $s_total_record, $job_id) {

            // $this->log(compact('idx', 'total'), 'debug');

            $remain_rows = $s_total_record - $idx;
            $buffered_rows = min(500, $remain_rows);

            // Markup the total rows for excel
            if ($inserted_rows < $buffered_rows && $buffered_rows> 0) {
                $sheet->insertNewRowBefore($base_row+1+$inserted_rows, $buffered_rows);
                $inserted_rows += $buffered_rows;
            }

            // Translate into array if object
            $r = is_object($entity) ? $entity->toArray() : $entity;

            if (!empty($r['merchant_id']) && !in_array($r['merchant_id'], $merchant_ids)) {
                $merchant_ids[] = $r['merchant_id'];
            }


            $row = $base_row + $idx;
            
            $sheet
                ->setCellValue("A$row", $r['state_time'])
                ->setCellValue("B$row", $r['state'])
                ->setCellValue("C$row", $r['customer_name'])
                ->setCellValue("D$row", $r['email'])
                ->setCellValueExplicit("E$row", $r['merchant'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("F$row", $r['amount'])
                ->setCellValue("G$row", $r['fee'])
                ->setCellValue("H$row", $r['net_amount'])
                ->setCellValue("I$row", $r['convert_currency'])
                ->setCellValue("J$row", $r['convert_rate'])
                ->setCellValue("K$row", $r['convert_amount'])
                ->setCellValueExplicit("L$row", $r['merchant_ref'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("M$row", $r['transaction_id'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("N$row", $r['product'])
                ->setCellValueExplicit("O$row", $r['ip_address'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("P$row", $r['bank_name'])
                ->setCellValue("Q$row", $r['bank_card_number'])
                ->setCellValue("R$row", $r['verified_name'])
                ->setCellValueExplicit("S$row", $r['id_card_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("T$row", $r['mobile_number'], PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("U$row", $r['settlement_status'])
                ;

            if ($idx > 0 && $idx % 500 == 0) {
                JobMetaHelper::updateProgress($job_id, .6 + .2*$idx / $s_total_record);
                $this->log('Job['.$job_id.']#FetchedTransasction(Refund): P:'.$idx .'/'.$s_total_record.', '.self::memoryUsage(), 'debug');
                if ($idx % 1000 == 0) {
                    sleep(1);
                }
                if (!JobMetaHelper::is($job_id, JobMetaHelper::STATUS_PROCESSING)) {
                    return false;
                }
            }
        });
        // Fetch all possible merchant accounts
        if (!empty($merchant_ids) && count($merchant_ids) > 0) {
            $_query =  self::$searchTool ->Merchants
                ->find()
                ->join([
                    'mg' => [
                        'table' => 'merchants_group_id',
                        'type' => 'INNER',
                        'conditions' => ['mg.merchant_id = m.id'],
                    ],
                ])
                ->where(['m.id IN'=> $merchant_ids])
                ->order(['mg.id ASC','processor_account_type ASC','createdate ASC'])
            ;

            $merchant_accounts = $_query->toArray();
        }

        $counter_row = 10 + $s_total_record;
        $sheet->getStyle('F'.$counter_row.':K'.$counter_row)->applyFromArray([
            'font'=>[
                'bold'=>true,
            ],
            'number_format'=>[
               'code' =>'#,##0.00_-',
            ],
            'borders'=>[
                'top'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_THIN,
                ],
                'bottom'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_DOUBLE,
                ],
            ]
        ])->getNumberFormat()->applyFromArray([
           'code' =>'#,##0.00_-',
        ]);
        $sheet->getStyle('F7:H'.(7+$s_total_record+3))
            ->getNumberFormat()->applyFromArray([
               'code' =>'#,##0.00_-',
            ]);
        $sheet->getStyle('J7:J'.(7+$s_total_record+3))
            ->getNumberFormat()->applyFromArray([
               'code' =>'#,##0.0000_-',
            ]);
        if (!empty($merchantgroup['id'])) {
            $sheet->setCellValue('F'.$counter_row, '=SUM(F7:F'.(7+$s_total_record).')');
            $sheet->setCellValue('G'.$counter_row, '=SUM(G7:G'.(7+$s_total_record).')');
            $sheet->setCellValue('H'.$counter_row, '=SUM(H7:H'.(7+$s_total_record).')');

            $sheet->setCellValue('K'.$counter_row, '=SUM(K7:K'.(7+$s_total_record).')');
        }


        if ($s_total_record > 0) {
            $sheet->setCellValue('A'.(12 + $s_total_record), 'Refund Fee:');
            $sheet->getStyle('A'.(12 + $s_total_record))->applyFromArray(['font'=>['bold'=>true]]);

            $base_row = 13 + $s_total_record;
            $idx = 0;
            // We use the sorted  data for listing all available merchants.
            foreach ($merchant_accounts as $merchant_info) {
                $row = $base_row + $idx;
                $sheet
                ->setCellValue("A$row", $merchant_info['name'] .' @ '.number_format($merchant_info['refund_fee_cny'], 2) .'CNY');


                // If the user is asking single merchant group, just show only 1 refund fee.
                if (!empty($merchantgroup['id'])) {
                    break;
                }
                $idx++;
            }
        } else {
            // Erase invalid content
            $row = 7;
            $sheet
                ->setCellValue("A$row", '')
                ->setCellValue("B$row", '')
                ->setCellValue("C$row", '')
                ->setCellValue("D$row", '')
                ->setCellValueExplicit("E$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("F$row", '')
                ->setCellValue("G$row", '')
                ->setCellValue("H$row", '')
                ->setCellValue("I$row", '')
                ->setCellValue("J$row", '')
                ->setCellValue("K$row", '')
                ->setCellValueExplicit("L$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("M$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("N$row", '')
                ->setCellValueExplicit("O$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("P$row", '')
                ->setCellValue("Q$row", '')
                ->setCellValue("R$row", '')
                ->setCellValueExplicit("S$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValueExplicit("T$row", '', PHPExcel_Cell_DataType::TYPE_STRING)
                ->setCellValue("U$row", '');
        }

        $merchant_accounts = null;
        
        JobMetaHelper::updateProgress($job_id, .9);
        $this->log('Job['.$job_id.']#SheetCmopleted(Refund):'.self::memoryUsage(), 'debug');


        // $this->QueuedJobs->updateAll([
        //     'progress' => 1,
        // ], ['id' => $job_id]);
        

        // Set first sheet.
        $excel->setActiveSheetIndex(0);
        $writer->save($file_path);

        $this->log('Job['.$job_id.']#FileWrote(Refund):'.self::memoryUsage(), 'debug');
        JobMetaHelper::updateProgress($job_id, 1);
        JobMetaHelper::markComplete($job_id);
        $this->log('Job['.$job_id.']#FlagUpdated:'.self::memoryUsage(), 'debug');

        // Important steps for cleaning records from memory
        $excel->disconnectWorksheets();
        
        unset($writer);
        unset($excel);
        
        return compact('file', 'file_path');
    }
}
