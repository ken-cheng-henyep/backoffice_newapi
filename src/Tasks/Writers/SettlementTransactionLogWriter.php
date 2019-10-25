<?php


namespace App\Tasks\Writers;

use WC\Query\QueryHelper;
use WC\Query\Resultset;


use SQLBuilder\Universal\Query\SelectQuery;

use \PayConnectorAPI;

use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;

use App\Tasks\QueueTask;

class SettlementTransactionLogWriter extends ContentWriter
{
    
    /**
     * Saving the output content by path
     *
     * @param string $output The file outptu location
     * 
     * @return void
     */
    public function save($output)
    {
        return $this->resultToExcel($output);
    }

    protected $driver;
    protected $query;
    protected $merchantService;
    protected $startDate;
    protected $endDate;

    /**
     * Setup the writer
     *
     * @param [type]      $driver
     * @param [type]      $merchantService
     * @param SelectQuery $query           The query object that generate the output
     * @param DateTime    $startDate
     * @param DateTime    $endDate
     * 
     * @return void
     */
    public function config($driver, $merchantService, $query, $startDate, $endDate) 
    {
        $this->driver = $driver;
        $this->query = $query;
        $this->merchantService = $merchantService;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }
    /**
     * Writes an excel for merchant.
     *
     * @param string $file The file path
     * 
     * @return boolean Return true if executed succesfully 
     */
    protected function resultToExcel($file)
    {
        $startDate = $this->startDate;
        $endDate = $this->endDate;

        $moneyFormat = [
            'code' =>'#,##0.00_-',
        ];

        $rateFormat = [
            'code' =>'#,##0.0000_-',
        ];
        
        $job_data = $this->data;

        // Prepare excel file path
        $tpl = ROOT.DIRECTORY_SEPARATOR .'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'transaction_search_template.xlsx';

        $file.= ".xlsx";
        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;


        // prepare local variables
        $merchant_ids =[];
        $data = [];

        // original query object
        $query = $this->query;

        $merchant_accounts = [];

        // If no merchant group selected, assume to select all
        $merchantgroup = null;
        if (!empty($job_data['params']['merchantgroup_ids'])) {

            $this->debug('Merchant groups provided: '. print_r($job_data['params']['merchantgroup_ids'], true));

            // If only 1 merchant group selected, assume that is merchant based.
            if (count($job_data['params']['merchantgroup_ids']) == 1) {

                $mgQuery = new SelectQuery();
                $mgQuery->select('*');
                $mgQuery->from('merchants_group');
                $mgQuery->where()->equal('id', $job_data['params']['merchantgroup_ids'][0]);

                $this->debug('Merchant group found: SQL:'. $this->driver->toSql($mgQuery));

                $rs = new Resultset($this->driver, $mgQuery); 
                $rs->start();

                $merchantgroup = $rs->next();

                $this->debug('Merchant group found: '. print_r($merchantgroup, true));

            }
        }

        $total_record = $this->driver->count($query);

        $job_data['total_record']  = $total_record;
        $job_data['output']  = $file_path;
        $this->dataChange($job_data);
        

        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        $cacheSettings = array( 'memoryCacheSize ' =>'128MB');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // Open excel file
        $excel = PHPExcel_IOFactory::load($tpl);
        
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        // Turn off for faster speed
        $writer->setPreCalculateFormulas(false);

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



        $sheet = $excel->getSheet(0);
        
        // Clear the formula if one merchantgroup selected
        if (empty($merchantgroup)) {
            $sheet->setCellValue('K12', '');
        }


        $s_total_record = $this->driver->count($s_query);

        // Update date value in the sales transaction sheet
        $sheet->setCellValue("B5", $startDate->format('Y-m-d'));
        $sheet->setCellValue("B6", $endDate->format('Y-m-d'));
        $merchant_accounts = [];

        $sheet->setCellValue("B4", '-');
        if (!empty($merchantgroup)) {
            $sheet->setCellValue("B4", 'All');
           
            if (!empty($merchantgroup['id'])) {
                $sheet->setCellValue("B4", $merchantgroup['name']);
            }
        }

        $sheet
        ->setCellValue("A15", 'N/A');

        $base_row = 9;
        $inserted_rows = 0;
        $sheet->insertNewRowBefore($base_row+1, $s_total_record);

        $s_query->orderBy('state_time', 'ASC');

        $rs = new Resultset($this->driver, $s_query);
        $rs->all(function ($entity, $index, $total) use (&$sheet, $base_row, &$merchant_ids, &$inserted_rows, $s_total_record) {

            // $this->log(compact('idx', 'total'));
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

            $row = $base_row + $index;

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
                

            if ($index > 0 && $index % 500 == 0) {
                $this->progress( .5*$index / $s_total_record);
                // JobMetaHelper::updateProgress($job_id, .5*$index / $s_total_record);
                // $this->log('Job['.$job_id.']#FetchedTransasction(Sales):'.', P:'.$index .'/'.$s_total_record.', '.QueueTask::memoryUsage());
                if ($index % 1000 == 0) {
                    $this->sleep(1);
                }

                $this->cycled();

                if ($this->cancelled) {
                    return false;
                }
            }
        });

        // Fetch all possible merchant accounts
        if (!empty($merchant_ids) && count($merchant_ids) > 0) {

            $mQuery = new SelectQuery();
            $mQuery->select('*');
            $mQuery->from('merchants', 'm')->where()->in('id', $merchant_ids);
            $mQuery->groupBy('m.id');
            $mQuery->orderBy('name');
            $merchant_accounts = (new Resultset($this->driver, $mQuery))->map();
        }

        $counter_row = 12 + $s_total_record;
        $last_row = 9 + $s_total_record;

        $sheet->getStyle('F'.$counter_row.':K'.$counter_row)->applyFromArray([
            'font'=>[
                'bold'=>true,
            ],
            'number_format'=>$moneyFormat,
            'borders'=>[
                'top'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_THIN,
                ],
                'bottom'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_DOUBLE,
                ],
            ]
        ])->getNumberFormat()->applyFromArray($moneyFormat);

        $sheet->getStyle('F7:H'.$counter_row)
            ->getNumberFormat()->applyFromArray($moneyFormat);
        $sheet->getStyle('J7:J'.$counter_row)
            ->getNumberFormat()->applyFromArray($rateFormat);
        if (!empty($merchantgroup['id'])) {
            $sheet->setCellValue('F'.$counter_row, '=SUM(F7:F'.$last_row.')');
            $sheet->setCellValue('G'.$counter_row, '=SUM(G7:G'.$last_row.')');
            $sheet->setCellValue('H'.$counter_row, '=SUM(H7:H'.$last_row.')');
            $sheet->setCellValue('K'.$counter_row, '=SUM(K7:K'.$last_row.')');
        }

        if ($s_total_record > 0) {
            $sheet->setCellValue('A'.(12 + $s_total_record), 'Transaction Charges:');
            $sheet->getStyle('A'.(12 + $s_total_record))->applyFromArray(['font'=>['bold'=>true]]);

            // Entering merchant Id
            $base_row = 15 + $s_total_record;
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
            $row = 9;
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

        $this->cycled();
        if ($this->cancelled) {
            return false;
        }

        $this->progress(.55);
        
        $merchant_ids = [];
        $merchant_accounts = [];
        
        $this->progress(.6);

        $this->info('Sales record completed.');

        // Sheet 1 - Refund Transaction
        
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
        $sheet->setCellValue("B3", $startDate->format('Y-m-d'));
        $sheet->setCellValue("B4", $endDate->format('Y-m-d'));


        // Erase invalid text
        $sheet ->setCellValue("A13", 'N/A');

        $s_total_record = $this->driver->count($s_query);
        // $s_total_record = $s_query->count();
        $this->log('debug', '# Total sales record:'.$s_total_record);

        $base_row = 7;
        $inserted_rows = 0;

        $rs = new Resultset($this->driver, $s_query);
        $rs->all(function ($entity, $index, $total) use (&$sheet, $base_row, &$merchant_ids, &$inserted_rows, $s_total_record) {

            // $this->log(compact('index', 'total'));

            $remain_rows = $s_total_record - $index;
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


            $row = $base_row + $index;
            
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

            if ($index > 0 && $index % 250 == 0) {

                $this->progrss($job_id, .6 + .2*$index / $s_total_record);

                if ($index % 1000 == 0) {
                    $this->log('debug', '# Memory:'.QueueTask::memoryUsage());
                    $this->sleep(1);
                }
                $this->cycled();
                if ($this->cancelled) {
                    return false;
                }
        
            }
        });
        $this->log('debug', '# Memory:'.QueueTask::memoryUsage());
        $merchant_accounts = null;
        $this->progress(.7);
        if ($this->cancelled) {
            return false;
        }

        // Fetch all possible merchant accounts
        if (!empty($merchant_ids) && count($merchant_ids) > 0) {

            $mQuery = new SelectQuery();
            $mQuery->select('*');
            $mQuery->from('merchants', 'm')->where()->in('id', $merchant_ids);
            $mQuery->groupBy('m.id');
            $mQuery->orderBy('name');
            $merchant_accounts = (new Resultset($this->driver, $mQuery))->map();
        }

        $counter_row = 10 + $s_total_record;

        $last_row = 7 + $s_total_record;

        $sheet->getStyle('F'.$counter_row.':K'.$counter_row)->applyFromArray([
            'font'=>[
                'bold'=>true,
            ],
            'number_format'=>$moneyFormat,
            'borders'=>[
                'top'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_THIN,
                ],
                'bottom'=>[
                    'style'=>\PHPExcel_Style_Border::BORDER_DOUBLE,
                ],
            ]
        ])->getNumberFormat()->applyFromArray($moneyFormat);
        $sheet->getStyle('F7:H'.$counter_row)
            ->getNumberFormat()->applyFromArray($moneyFormat);
        $sheet->getStyle('J7:J'.$counter_row)
            ->getNumberFormat()->applyFromArray($rateFormat);
        if (!empty($merchantgroup['id'])) {
            $sheet->setCellValue('F'.$counter_row, '=SUM(F7:F'.$last_row.')');
            $sheet->setCellValue('G'.$counter_row, '=SUM(G7:G'.$last_row.')');
            $sheet->setCellValue('H'.$counter_row, '=SUM(H7:H'.$last_row.')');
            $sheet->setCellValue('K'.$counter_row, '=SUM(K7:K'.$last_row.')');
        }
        
        $this->progress(.8);


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
        if ($this->cancelled) {
            return false;
        }

        $merchant_accounts = null;
        $this->progress(.9);

        // Set first sheet.
        $excel->setActiveSheetIndex(0);

        $writer->save($file_path);
        
        // Important steps for cleaning records from memory
        $excel->disconnectWorksheets();

        $this->log('debug', '# Memory:'.QueueTask::memoryUsage());
        $this->progress(1);
        
        unset($writer);
        unset($excel);
        
        return true;
    }
}
