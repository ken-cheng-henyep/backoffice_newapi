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

use \App\Lib\TransactionFinder;
use \App\Lib\TransactionSearchQuery;
use \App\Lib\JobMetaHelper;
use \App\Lib\SettlementProcessQueries;
use \App\Lib\SettlementBatchBuilder;

use \WC\Query\QueryHelper;

class SettlementBatchListWriter extends ExcelWriter
{
    protected $service;
    protected $merchantService;

    public function config($service, $merchantService) 
    {
        $this->service = $service;
        $this->merchantService = $merchantService;
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
        $this->resultToExcel($file);

        return true;
    }
    /**
     * Writes an excel for merchant.
     *
     * @param string $file The output file path
     * 
     * @return void
     */
    protected function resultToExcel($file)
    {
        $job_data = $this->data;

        $template_path = ROOT.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'settlement_batch_list_template.xlsx';
        
        $file.= ".xlsx";
        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;

        $job_data['output']  = $file_path;
        // $this->log('Job['.$job_id.']#OutputPatH:'.$file_path, 'debug');

        $this->dataChange($job_data);

        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        $cacheSettings =    array( 'memoryCacheSize ' =>'128MB');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // Open excel file by a template file
        $excel = PHPExcel_IOFactory::load($template_path);
        
        $tabNames = ['SettlementBatch'];

        foreach ($tabNames as $idx => $tabName) {
            $_tabName = "tab$tabName";
            $this->$_tabName($excel);
            $this->progress(1.0* ($idx + 1)/ count($tabNames));

            $this->cycled();
        }

        // $this->log('Job['.$job_id.']#SheetCmopleted:'.self::memoryUsage(), 'info');


        // Set first sheet.
        $excel->setActiveSheetIndex(0);

        $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
        // Turn off for faster speed
        $writer->setPreCalculateFormulas(false);
        $writer->save($file_path);

        // $this->log('Job['.$job_id.']#FileWrote:'.self::memoryUsage(), 'info');
        $this->progress(1);
        
        // Important steps for cleaning records from memory
        $excel->disconnectWorksheets();
        
        unset($writer);
        unset($excel);
        
        $this->ended();

        return compact('file', 'file_path');
    }
    
    /**
     * Tab: Merchants
     *
     * @param PHPExcel $excel The excel
     * 
     * @return void
     */
    protected function tabSettlementBatch($excel)
    {
        $sheet = $excel->getActiveSheet();
        $sheet->setTitle('Settlement Batch');

        $this->service->debug('Writing batch with data: '.print_r($this->data, true));

        $data = $this->service->findBatches(isset($this->data['conditions']) ? $this->data['conditions'] : null);
        foreach ($data as $idx => $row) {
            $row['total_settlement_amount'] =  floatval($row['total_settlement_amount']);
            $data[$idx] = $row;
        }
        $this->service->debug('Number records found: '.count($data));

        $fields = [
            'batch_id'=>'Batch ID',
            'report_date'=>'Report Date',
            'from_date'=>'Start Date',
            'to_date'=>'End Date',
            'merchant_id'=>'Merchant ID',
            'merchant_name'=>'Merchant',
            'settlement_currency'=>'Currency',
            'total_settlement_amount'=>'Total Settlement Amount',
            'state'=>'Status',
        ];
        
        $sizes = [
            'batch_id'=>48,
            'report_date'=>12,
            'from_date'=>12,
            'to_date'=>12,
            'merchant_id'=>42,
            'merchant_name'=>48,
            'settlement_currency'=>12,
            'total_settlement_amount'=>18,
            'state'=>12,
        ];

        $styledColumns = [
            'money'=>['total_settlement_amount'],
        ];

        $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 5);

        $text = 'Date: ';
        if (!empty($this->data['start_date'])) {
            $text .= date('Y/m/d', strtotime($this->data['start_date']));
            if (!empty($this->data['end_date'])) {
                $text .= ' - '. date('Y/m/d', strtotime($this->data['end_date']));   
            }    
        } else {
            $text .= 'N/A';
        }
        $sheet->setCellValue('A2', $text);

        $text = 'Merchants: ';
        if (!empty($this->data['merchant_id'])) {
            $merchant = $this->merchantService->getMasterMerchant($this->data['merchant_id']);
            $text .= $merchant['name'];
        } else {
            $text .= 'All';
        }
        $sheet->setCellValue('A3', $text);

        $text = 'Status: ';
        if (!empty($this->data['state'])) {
            $text .= $this->data['state'];
        } else {
            $text .= 'All';
        }
        $sheet->setCellValue('A4', $text);
    }

    
}