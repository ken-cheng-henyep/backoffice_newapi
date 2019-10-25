<?php

namespace App\Tasks\Writers;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;


use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;
use PHPExcel_Style_Border;
use PHPExcel_Cell;

use Cake\Console\Shell;

use \App\Lib\JobMetaHelper;

use \WC\Query\QueryHelper;
use \WC\Backoffice\SettlementService;
use \WC\Backoffice\Reconciliation\QueryBuilder;
use \WC\Backoffice\Reconciliation\QuerySet;

class ReconcilationBatchWriter extends ExcelWriter
{
    protected $service;

    protected $querySet;

    /**
     * Setup the writer by passing the necessary objects.
     *
     * @param SettlementService $service  The settlement service instance
     * @param QuerySet          $querySet The prepared 
     * 
     * @return void
     */
    public function config(SettlementService $service,QuerySet $querySet) 
    {
        $this->service = $service;
        $this->querySet = $querySet;
    }

    /**
     * Write the file
     *
     * @param string $file The output file path
     * 
     * @return boolean
     */
    public function save($file) 
    {
        $result =  $this->resultToExcel($this->querySet, $file);
        if (is_array($result)) {
            $this->data = is_array($this->data) ? array_merge($this->data, $result) : $result;
        }
        
        return true;
    }
    
    /**
     * Protected function for writing an excel file for selected transaction in
     * reconciliation.
     *
     * @param mixed  $querySet The search queries set.
     * @param string $file     The output file path
     *
     * @return array 
     */
    protected function resultToExcel($querySet, $file)
    {
        // Prepare excel file path
        $tpl = ROOT.DIRECTORY_SEPARATOR .'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'settlement_reconciliation_template.xlsx';

        $file = $file.".xlsx";

        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;

        // $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        // $cacheSettings =    array( ' memoryCacheSize ' =>'64MB');
        // PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);


        $moneyStyle = ['numberFormat'=>['code' =>'#,##0.00_-',],'bold'=>false];

        // // Open excel file
        $excel = PHPExcel_IOFactory::load($tpl);

        $this->log('debug', __METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB');

        // Getting the summary sheet
        $sheet = $excel->getSheet(0);

        // Setup date in the report.
        if (!empty($this->data['start_date'])) {
            $text = 'Date: '.date('Y/m/d', strtotime($this->data['start_date']));

            if (!empty($this->data['end_date']) && $this->data['start_date'] != $this->data['end_date']) {
                $text .= ' - '.date('Y/m/d', strtotime($this->data['end_date']));
            }

            $sheet->setCellValue('A2', $text);
        } else {
            $sheet->setCellValue('A2', '');
        }

        $querySet->merchants->setOrderBy([['name','ASC']]);
        $querySet->acquirers->setOrderBy([['name','ASC']]);

        // Preparing Summary Tab
        $merchantsRs = $this->service->resultset($querySet->merchants);
        $merchants_count = $merchantsRs->count();

        $acquirerRs = $this->service->resultset($querySet->acquirers);
        $acquirer_count = $acquirerRs->count();


        // Prepend the empty rows
        
        $acquirer_row_from = 7 ;
        $acquirer_row_to = $acquirer_row_from+$acquirer_count;
        $sheet->insertNewRowBefore($acquirer_row_from+1, $acquirer_count);


        $merchant_row_from = 12 + $acquirer_count ;
        $merchant_row_to = $merchant_row_from + $merchants_count + $acquirer_count;
        $sheet->insertNewRowBefore($merchant_row_from+1, $merchants_count);

        ///// merchants /////

        $fields = [
            'name'=>'Merchant',
            'currency'=>'P. Currency',
            'payment_amount'=>'Payment Amount',
            'payment_total_tx' =>'Payment Count',
            'payment_fee'=>'Payment Fee',
            'refund_amount'=>'Refund Amount',
            'refund_total_tx'=>'Refund Count',
            'refund_fee'=>'Refund Fee',
            'net_amount'=>'Net Amount',
        ];

        $field_names = array_keys($fields);
        $row_offset = $merchant_row_from;

        // $sheet->insertNewRowBefore($row_offset+1, $merchants_count);
        //
        // Insert label at the head
        $last_column = 'A'.$merchant_row_from;
        foreach ($field_names as $column_index => $field_name) {
            $column_id = chr(65+ $column_index);
            $row_id = $row_offset - 1;
            
            $last_column = $column_id.$row_id;
            $sheet->setCellValue($last_column, $fields[$field_name]);
        }
        $sheet->getStyle('A'.$merchant_row_from.':'.$last_column)->applyFromArray(['bold'=>true]);
        // Fetch records one by one
        $merchantsRs->all(function ($entity, $index) use (&$fields, &$field_names, &$sheet, $row_offset) {


            foreach ($field_names as $column_index => $field_name) {
                $column_id = chr(65+ $column_index);
                $row_id = $row_offset + $index;
                
                $sheet->setCellValue($column_id.$row_id, $entity[$field_name]);
            }
        });

        // Set number format
        // Payment Amount
        $sheet->getStyle('C'.($merchant_row_from).':C'.($merchant_row_to))
            ->applyFromArray($moneyStyle);
        // Payment Fee, Refund Amount
        $sheet->getStyle('E'.($merchant_row_from).':F'.($merchant_row_to))
            ->applyFromArray($moneyStyle);
        // Refund Fee, Net Amount
        $sheet->getStyle('H'.($merchant_row_from).':I'.($merchant_row_to))
            ->applyFromArray($moneyStyle);

        $this->log('debug', __METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB');


        /// acquirers /////
        $fields = [
            'name'=>'Processor',
            'currency'=>'P. Currency',
            'amount'=>'Amount',
            'total_tx'=>'Count',
            'fee'=>'Fee',
            'net_amount'=>'Net Amount',
        ];

        $field_names = array_keys($fields);

        $row_offset = $acquirer_row_from;
        
        // Prepend the empty rows
        // $sheet->insertNewRowBefore($row_offset+1, $acquirer_count);
        //
        // Insert label at the head
        $last_column = 'A'.$row_offset;
        foreach ($field_names as $column_index => $field_name) {
            $column_id = chr(65+ $column_index);
            $row_id = $row_offset - 1;
            $last_column = $column_id.$row_id;
            $sheet->setCellValue($last_column, $fields[$field_name]);
        }
        // $sheet->getStyle('A'.$row_offset.':'.$last_column)->applyFromArray(['bold'=>true]);
        

        $acquirerRs->all(function ($entity, $index) use (&$fields, &$field_names, &$sheet, $row_offset) {
            foreach ($field_names as $column_index => $field_name) {
                $column_id = chr(65+ $column_index);
                $row_id = $row_offset + $index;
                $sheet->setCellValue($column_id.$row_id, $entity[$field_name]);
            }
        });

        // Set number format
        // Amount
            $sheet->getStyle('C'.($acquirer_row_from).':C'.($acquirer_row_to))
            ->applyFromArray($moneyStyle);
        // Fee & Net Amount
        $sheet->getStyle('E'.($acquirer_row_from).':F'.($acquirer_row_to))
            ->applyFromArray($moneyStyle);

        ///// Transactions /////
        $sheet = $excel->getSheet(1);

        $transactionRs = $this->service->resultset($querySet->transactions);

        $total_record = $transactionRs->count();
        $item_per_page = 3000;
        $total_page = ceil($total_record / $item_per_page);

        $transaction_row_from = 4 ;
        $row_offset = $transaction_row_from ;
        
        $fields = [
            'state_time'=>'State Time',
            'state'=>'State',
            'customer_name'=>'Customer Name',
            'email'=>'Email',
            'merchantgroup_name'=>'Merchant',
            'merchant'=>'Account',
            'processor_name'=>'Processor',
            'currency'=>'P. Currency',
            'amount'=>'Amount',
            'processor_fee'=>'Processor Fee',
            'processor_net_amount'=>'Net Amount',
            // 'fee'=>'WeCollect Fee',
            // 'net_amount'=>'Merchant Net Amount',
            'merchant_ref'=>'Merchant Ref',
            'transaction_id'=>'Transaction ID',
            'product'=>'Product',
            'ip_address'=>'IP Address',
        ];
        $field_names = array_keys($fields);

        $moneyFields = ['amount','processor_fee','processor_net_amount','fee'];

        $fields_columns  = [];
        foreach ($field_names as $column_index => $field_name) {
            $fields_columns[$field_name] = PHPExcel_Cell::stringFromColumnIndex($column_index);
        }

        // Append the rows with styles
        $sheet->insertNewRowBefore($transaction_row_from+1, $total_record);

        $transactionRs->all(function ($entity, $index) use (&$fields, &$field_names, &$sheet, $row_offset, $total_record, $fields_columns, $moneyFields) {

            foreach ($field_names as $column_index => $field_name) {
                $column_id = $fields_columns[$field_name];
                $row_id =  $row_offset + $index;
                $cell_id = $column_id.$row_id;

                $value = $entity[$field_name];
                if (in_array($field_name, $moneyFields)) {
                    $value = floatval($value);
                    $sheet->setCellValueExplicit($cell_id, $value, \PHPExcel_Cell_DataType::TYPE_NUMERIC);
                } else {
                    $sheet->setCellValueExplicit($cell_id, $value, \PHPExcel_Cell_DataType::TYPE_STRING);
                }
            }
            
            if ($index > 0 && $index %100 == 0) {
                $this->log('debug', __METHOD__.'@'.__LINE__.':'.$index.'/'.$total_record);
            }
        });

        //// Below code does not working!
        // $transaction_row_to = ($transaction_row_from + $total_record);
        // foreach ($moneyFields as $field_name) {

        //     if (!isset($fields_columns[$field_name])) {
        //         continue;
        //     }

        //     $column_id = $fields_columns[$field_name];

        //     $range_string = $column_id.$transaction_row_from.':'.$column_id.$transaction_row_to;
        //     // Set number format
        //     $sheet->getStyle($range_string)->applyFromArray($moneyStyle);
        //     $this->log('debug', __METHOD__.'@'.__LINE__.':'."Number Format for range ".$range_string);
        // }

        // Erase from memory
        $items = null;
        $fields = null;
        $field_names = null;
        $sheet = null;


        $this->log('debug', __METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB');

        // Set first sheet.
        $excel->setActiveSheetIndex(0);


        // Setup writer
        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');

        // Turn off for faster speed
        $writer->setPreCalculateFormulas(false);

        // Save to local file
        $writer->save($file_path);


        $this->log('debug', __METHOD__.'@'.__LINE__.':'."Memory usage: ".((memory_get_usage() / 1024 / 1024)<<0).'MB');

        return compact('file', 'file_path');
    }

}
