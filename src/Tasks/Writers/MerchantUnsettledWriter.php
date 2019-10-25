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

class MerchantUnsettledWriter extends ExcelWriter
{

    protected $service;

    public function config($service)
    {
        $this->service = $service;
    }

    public function save($file)
    {
        $this->resultToExcel($file);

        return true;
    }
    /**
     * Writes an excel for merchant.
     *
     * @param SettlementBatchData $batchData The batch data
     * @param string              $file      The file path
     *
     * @return void
     */
    protected function resultToExcel($file)
    {
        $job_data = $this->data;

        $template_path = ROOT.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'merchant_unsettled_template.xlsx';

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

        // $this->log('Job['.$job_id.']#InstanceCreated:'.self::memoryUsage(), 'info');

        
        $tabNames = ['Merchants'];

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
    protected function tabMerchants($excel)
    {
        $sheet = $excel->getSheet(0);
        $sheet->setTitle('Merchants');

        $data = $this->service->getStoredMerchantUnsettled(false);

        $fields = [
            'name'=>'Name',
            'merchant_id'=>'Merchant ID',
            'min_date'=>'From',
            'max_date'=>'To',
            'currency'=>'Currency',
            'amount'=>'Amount',
            'count'=>'Count',
        ];
        
        $sizes = [
            'name'=>48,
            'merchant_id'=>42,
            'min_date'=>12,
            'max_date'=>12,
            'currency'=>12,
            'amount'=>16,
            'count'=>12,
        ];

        $styledColumns = [
            'money'=>['amount'],
            'integer'=>['count'],
        ];

        $sheet->setCellValue('A2', 'Date: '.(new \DateTime())->format('Y/m/d'));

        // Start content from row 4
        $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 3);
    }
}
