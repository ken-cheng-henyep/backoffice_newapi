<?php 

namespace App\Tasks\Writers;


use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;
use PHPExcel_Style_Border;
use PHPExcel_Cell;
use PHPExcel_Style_Fill;

use \WC\Query\QueryHelper;
use \WC\Backoffice\Settlement\BatchState;

use \RemittanceReportReader;

use \Cake\Log\Log;

class SettlementBatchWriter extends ExcelWriter
{

    protected $service;
    protected $batchData;

    public function config($service, $batchData) 
    {
        $this->service = $service;
        $this->batchData = $batchData;
        $this->styles['header'] = null;
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
     * @param string $file The file path
     * 
     * @return void
     */
    protected function resultToExcel($file)
    {
        $batchData = $this->batchData;
        $job_data = $this->data;

        $template_path = ROOT.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR.'settlement_report_preview_sample.xlsx';

        $file.= ".xlsx";
        $file_path = ROOT.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.$file;

        $job_data['output']  = $file_path;
        // $this->log('Job['.$job_id.']#OutputPatH:'.$file_path, 'debug');

        $this->dataChange($job_data);

        // $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_sqlite3;
        // $cacheSettings =    array( 'memoryCacheSize ' =>'128MB');
        // PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);

        // Open excel file by a template file
        $excel = PHPExcel_IOFactory::load($template_path);
        
        $tabNames = ['Summary','Sales','Refund','BatchRemittance','BatchRemittanceAdj','InstantRemittance','InstantRemittanceAdj', 'BatchRemittanceDetail'];

        $sheetTobeRemoved = [];
        foreach ($tabNames as $idx => $tabName) {
            $_tabName = "tab$tabName";
            $this->$_tabName($excel, $batchData);
            $this->progress(.8* ($idx + 1)/ count($tabNames));

            $this->cycled();
        }
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
     * Create summary sheet
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabSummary($excel, $batchData)
    {
        $defaultCurrency = $batchData->defaultCurrency;
        $merchantCurrency = $batchData->merchantCurrency;
        $querySet = $batchData->querySet;

        // $this->log('tabSummary: '.print_r($batchData->particulars, true), 'debug');

        // Prepare data-set for the settlement batch.
        $particulars = [];

        $broughtForward = $batchData->getParticularData('broughtForward', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'broughtForward-'.$merchantCurrency,
            'particular'=>'broughtForward',
            'particular_name'=>'Brought Forward',
            'currency'=>$merchantCurrency,
            'converted_amount'=>$broughtForward['converted_amount'],
            'remarks'=>$broughtForward['remarks'],
            'type'=>'remark_change',
        ];

        $salesSummaryDefault = $batchData->getParticularData('sales', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'sales-'.$defaultCurrency,
            'particular'=>'sales',
            'particular_name'=>'Payment Processing',
            'currency'=>$defaultCurrency,
            'amount'=>$salesSummaryDefault['amount'],
            'converted_amount'=>$salesSummaryDefault['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $salesSummaryMerchant = $batchData->getParticularData('sales', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'sales-'.$merchantCurrency,
            'particular'=>'sales',
            'particular_name'=>'Payment Processing',
            'currency'=>$merchantCurrency,
            'amount'=>$salesSummaryMerchant['amount'],
            'converted_amount'=>$salesSummaryMerchant['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $refundSummaryDefault = $batchData->getParticularData('refund', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'refund-'.$defaultCurrency,
            'particular'=>'refund',
            'particular_name'=>'Refund',
            'currency'=>$defaultCurrency,
            'amount'=>$refundSummaryDefault['amount'],
            'converted_amount'=>$refundSummaryDefault['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $refundSummaryMerchant = $batchData->getParticularData('refund', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'refund-'.$merchantCurrency,
            'particular'=>'refund',
            'particular_name'=>'Refund',
            'currency'=>$merchantCurrency,
            'amount'=>$refundSummaryMerchant['amount'],
            'converted_amount'=>$refundSummaryMerchant['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $batchRemittance = $batchData->getParticularData('batchRemittance', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'batchRemittance-'.$defaultCurrency,
            'particular'=>'batchRemittance',
            'particular_name'=>'Batch Remittance',
            'currency'=>$defaultCurrency,
            'amount'=>$batchRemittance['amount'],
            'converted_amount'=>$batchRemittance['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];
        
        $batchRemittanceAdj = $batchData->getParticularData('batchRemittanceAdj', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'batchRemittanceAdj-'.$defaultCurrency,
            'particular'=>'batchRemittanceAdj',
            'particular_name'=>'Batch Remittance Adj.',
            'currency'=>$defaultCurrency,
            'amount'=>$batchRemittanceAdj['amount'],
            'converted_amount'=>$batchRemittanceAdj['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $instantRemittance = $batchData->getParticularData('instantRemittance', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'instantRemittance-'.$defaultCurrency,
            'particular'=>'instantRemittance',
            'particular_name'=>'Instant Remittance',
            'currency'=>$defaultCurrency,
            'amount'=>$instantRemittance['amount'],
            'converted_amount'=>$instantRemittance['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];
        
        $instantRemittanceAdj = $batchData->getParticularData('instantRemittanceAdj', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'instantRemittanceAdj-'.$defaultCurrency,
            'particular'=>'instantRemittanceAdj',
            'particular_name'=>'Instant Remittance Adj.',
            'currency'=>$defaultCurrency,
            'amount'=>$instantRemittanceAdj['amount'],
            'converted_amount'=>$instantRemittanceAdj['converted_amount'],
            'remarks'=>'',
            'type'=>'subgrid',
        ];

        $carryForward = $batchData->getParticularData('carryForward', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'carryForward-'.$merchantCurrency,
            'particular'=>'carryForward',
            'particular_name'=>'Carry Forward',
            'currency'=>$merchantCurrency,
            'converted_amount'=>$carryForward['converted_amount'],
            'remarks'=>$carryForward['remarks'],
            'type'=>'amount_remark_change',
        ];
        
        $adhocAdj = $batchData->getParticularData('adhocAdj', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'adhocAdj-'.$merchantCurrency,
            'particular'=>'adhocAdj',
            'particular_name'=>'Ad-hoc Adjustment',
            'currency'=>$merchantCurrency,
            'converted_amount'=>$adhocAdj['converted_amount'],
            'remarks'=>$adhocAdj['remarks'],
            'type'=>'amount_remark_change',
        ];

        $settlementAmountDefault = $batchData->getParticularData('settlementAmount', $batchData->defaultCurrency);
        $particulars[] = [
            'id'=>'settlementAmount-'.$defaultCurrency,
            'particular'=>'settlementAmount',
            'particular_name'=>'Settlement Amount',
            'currency'=>$defaultCurrency,
            'amount'=>$settlementAmountDefault['amount'],
            'converted_amount'=>$settlementAmountDefault['converted_amount'],
            'type'=>'none',
        ];
        
        $settlementAmountMerchant = $batchData->getParticularData('settlementAmount', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'settlementAmount-'.$merchantCurrency,
            'particular'=>'settlementAmount',
            'particular_name'=>'Settlement Amount',
            'currency'=>$merchantCurrency,
            'amount'=>$settlementAmountMerchant['amount'],
            'converted_amount'=>$settlementAmountMerchant['converted_amount'],
            'type'=>'none',
        ];
        
        $settlementHandlingFeeMerchant = $batchData->getParticularData('settlementHandlingFee', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'settlementHandlingFee-'.$merchantCurrency,
            'particular'=>'settlementHandlingFee',
            'particular_name'=>'Settlement Handling Fee',
            'currency'=>$merchantCurrency,
            'converted_amount'=>$settlementHandlingFeeMerchant['converted_amount'],
            'type'=>'none',
        ];

        $totalSettlementAmount = $batchData->getParticularData('totalSettlementAmount', $batchData->merchantCurrency);
        $particulars[] = [
            'id'=>'totalSettlementAmount-'.$merchantCurrency,
            'particular'=>'totalSettlementAmount',
            'particular_name'=>'Total Settlement Amount',
            'currency'=>$merchantCurrency,
            'converted_amount'=>$totalSettlementAmount['converted_amount'],
            'type'=>'none',
        ];
        
        $footnotes = $batchData->getParticularData('footnotes');
        $particulars[] = [
            'id'=>'footnotes',
            'particular'=>'footnotes',
            'particular_name'=>'Footnotes',
            'remarks'=>$footnotes['remarks'],
            'type'=>'remark_change',
        ];
        // $this->log("Job[$job_id]footnotes=".print_r($footnotes, true), 'debug');

        $sheet = $excel->getSheet(0);
        // $sheet->setTitle('Summary');

        $fields = [
            'particular_name'=>'Particular',
            'currency'=>'Currency',
            'amount'=>'Amount',
            'converted_amount'=>'Converted Amount',
            'remarks'=>'Remarks'
        ];

        $styledColumns = [];
        $styledColumns['longText'] = ['particular_name','currency','remarks'];
        $styledColumns['money'] = ['amount','converted_amount'];

        
        $sizes = [
            'particular_name'=>30,
            'currency'=>12,
            'converted_amount'=>20,
            'amount'=>20,
            'remarks'=>50,
        ];

        $removedCounter = 0;

        $row = 0;

        // A10 - Report Title
        // Change the report title to Transaction Summary Report.
        if ($totalSettlementAmount['converted_amount'] == 0) {
            $sheet->setCellValue('A10', 'Transaction Summary Report');
        }

        // B15
        $sheet->setCellValueByColumnAndRow(1, 15, $batchData->masterMerchant['name']);

        // E13
        $sheet->setCellValueByColumnAndRow(4, 13, 'Report Date: '.$batchData->reportDate->format('Y/m/d'));

        // A13
        $sheet->setCellValueByColumnAndRow(0, 13, 'Transaction Date: '.$batchData->startDate->format('Y/m/d'));
        if ($batchData->startDate->format('Y/m/d') != $batchData->endDate->format('Y/m/d')) {
            $sheet->setCellValueByColumnAndRow(0, 13, 'Transaction Date: '.$batchData->startDate->format('Y/m/d').' to '.$batchData->endDate->format('Y/m/d'));
        }

        // B16
        $sheet->setCellValueByColumnAndRow(1, 16, $batchData->merchantCurrency);
        
        // $row ++;
        // $sheet->setCellValueByColumnAndRow(0, $row, 'FX Package');
        // $sheet->setCellValueByColumnAndRow(1, $row, $batchData->fxPackage);
        
        // if (!empty($batchData->settlementRate)) {
        //     $row ++;
        //     $sheet->setCellValueByColumnAndRow(0, $row, 'Settlement Rate');
        //     $sheet->setCellValueByColumnAndRow(1, $row, $batchData->settlementRate);

        //     $columnName = PHPExcel_Cell::stringFromColumnIndex($row);
        //     $sheet->getStyle($columnName.'2')
        //         ->applyFromArray($this->styles['rate'])
        //         ->getNumberFormat()
        //             ->applyFromArray($this->numberFormats['rate'])
        //         ;
        // }

        if (!empty($batchData->id)) {
            // B17
            $sheet->setCellValueByColumnAndRow(1, 17, $batchData->id);
        }

        $footerMessageTpl = 'The net settlement amount %s %s has been remitted to your corporate account no. %s held at %s on %s.';
        $footerMessage = sprintf(
            $footerMessageTpl, 
            $batchData->merchantCurrency, 
            number_format($totalSettlementAmount['converted_amount'], 2), 
            !empty($batchData->bankAccount)? $batchData->bankAccount: 'N/A', 
            !empty($batchData->bankName) ? $batchData->bankName : 'N/A', 
            $batchData->reportDate->format('Y/m/d')
        );

        // No message if under zero 
        if ($totalSettlementAmount['converted_amount'] <= 0) {
            $footerMessage = '';
        }

        $sheet->setCellValueByColumnAndRow(0, 46, $footerMessage);
        if (!empty($footnotes['remarks'])) {
            $sheet->setCellValueByColumnAndRow(0, 47, 'Remarks: '.$footnotes['remarks']);
        } else {
            $sheet->setCellValueByColumnAndRow(0, 47, '');
        }

        // $this->buildSheet($sheet, $fields, $particulars, $styledColumns, $sizes, 10);

        // Merchant currency at header (E19)
        $sheet->setCellValueByColumnAndRow(4, 19, $batchData->merchantCurrency);


        // Row 20: Brought Forward 

        if ($broughtForward['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 20 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(4, 20 - $removedCounter, $broughtForward['converted_amount']);
        } else {
            $sheet->removeRow(20 - $removedCounter);
            $removedCounter ++;
        }
        
        if ($broughtForward['converted_amount'] != 0 && !empty($broughtForward['remarks'])) {
            $sheet->setCellValueByColumnAndRow(0, 21 - $removedCounter, 'Remarks: '.$broughtForward['remarks']);
        } else {
            $sheet->removeRow(21 - $removedCounter);
            $removedCounter ++;
        }
        if (!($broughtForward['converted_amount'] != 0 || !empty($broughtForward['remarks']))) {
            $sheet->removeRow(22 - $removedCounter);
            $removedCounter ++;
        }

        // Row 23: Payment Process
        if ($salesSummaryDefault['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 23 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $salesSummaryDefault['amount'] / $salesSummaryDefault['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 23 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 23 - $removedCounter, $salesSummaryDefault['amount']);
            $sheet->setCellValueByColumnAndRow(4, 23 - $removedCounter, $salesSummaryDefault['converted_amount']);
        } else {
            $sheet->removeRow(23 - $removedCounter);
            $removedCounter ++;
        }

        if ($salesSummaryMerchant['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 24 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(3, 24 - $removedCounter, $salesSummaryMerchant['amount']);
            $sheet->setCellValueByColumnAndRow(4, 24 - $removedCounter, $salesSummaryMerchant['converted_amount']);
        } else {
            $sheet->removeRow(24 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 26: Refund Process
        if ($refundSummaryDefault['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 26 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $refundSummaryDefault['amount'] / $refundSummaryDefault['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 26 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 26 - $removedCounter, $refundSummaryDefault['amount']);
            $sheet->setCellValueByColumnAndRow(4, 26 - $removedCounter, $refundSummaryDefault['converted_amount']);
        } else {
            $sheet->removeRow(26 - $removedCounter);
            $removedCounter ++;
        }

        if ($refundSummaryMerchant['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 27 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(3, 27 - $removedCounter, $refundSummaryMerchant['amount']);
            $sheet->setCellValueByColumnAndRow(4, 27 - $removedCounter, $refundSummaryMerchant['converted_amount']);
        } else {
            $sheet->removeRow(27 - $removedCounter);
            $removedCounter ++;
        }
        if (!($refundSummaryDefault['converted_amount'] != 0 || $refundSummaryMerchant['converted_amount'] != 0) ) {
            $sheet->removeRow(28 - $removedCounter);
            $removedCounter ++;
        }

        // Row 29: Batch Remittance
        if ($batchRemittance['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 29 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $batchRemittance['amount'] / $batchRemittance['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 29 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 29 - $removedCounter, $batchRemittance['amount']);
            $sheet->setCellValueByColumnAndRow(4, 29 - $removedCounter, $batchRemittance['converted_amount']);
        } else {
            $sheet->removeRow(29 - $removedCounter);
            $removedCounter ++;
        }

        // Row 30: Batch Remittance Adj.
        if ($batchRemittanceAdj['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 30 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $batchRemittanceAdj['amount'] / $batchRemittanceAdj['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 30 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 30 - $removedCounter, $batchRemittanceAdj['amount']);
            $sheet->setCellValueByColumnAndRow(4, 30 - $removedCounter, $batchRemittanceAdj['converted_amount']);
        } else {
            $sheet->removeRow(30 - $removedCounter);
            $removedCounter ++;
        }
        if (!($batchRemittanceAdj['converted_amount'] != 0 || $batchRemittance['converted_amount'] != 0) ) {
            $sheet->removeRow(31 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 32: Instant Remittance
        if ($instantRemittance['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 32 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $instantRemittance['amount'] / $instantRemittance['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 32 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 32 - $removedCounter, $instantRemittance['amount']);
            $sheet->setCellValueByColumnAndRow(4, 32 - $removedCounter, $instantRemittance['converted_amount']);
        } else {
            $sheet->removeRow(32 - $removedCounter);
            $removedCounter ++;
        }

        // Row 33: Instant Remittance Adj.
        if ($instantRemittanceAdj['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 33 - $removedCounter, $batchData->defaultCurrency);
            $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $instantRemittanceAdj['amount'] / $instantRemittanceAdj['converted_amount']);
            $sheet->setCellValueByColumnAndRow(2, 33 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 33 - $removedCounter, $instantRemittanceAdj['amount']);
            $sheet->setCellValueByColumnAndRow(4, 33 - $removedCounter, $instantRemittanceAdj['converted_amount']);
        } else {
            $sheet->removeRow(33 - $removedCounter);
            $removedCounter ++;
        }
        if (!($instantRemittance['converted_amount'] != 0 || $instantRemittanceAdj['converted_amount'] != 0) ) {
            $sheet->removeRow(34 - $removedCounter);
            $removedCounter ++;
        }

        // Row 35: Carry Forward 
        if ($carryForward['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 35 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(4, 35 - $removedCounter, $carryForward['converted_amount']);
        } else {
            $sheet->removeRow(35 - $removedCounter);
            $removedCounter ++;
        }
        if ($carryForward['converted_amount'] != 0 && !empty($carryForward['remarks'])) {
            $sheet->setCellValueByColumnAndRow(0, 36 - $removedCounter, 'Remarks: '.$carryForward['remarks']);
        } else {
            $sheet->removeRow(36 - $removedCounter);
            $removedCounter ++;
        }
        if (!($carryForward['converted_amount'] != 0 || !empty($carryForward['remarks']))) {
            $sheet->removeRow(37 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 38: Ad-hoc Adjustment
        if ($adhocAdj['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 38 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(4, 38 - $removedCounter, $adhocAdj['converted_amount']);
        } else {
            $sheet->removeRow(38 - $removedCounter);
            $removedCounter ++;
        }
        if ($adhocAdj['converted_amount'] != 0 && !empty($adhocAdj['remarks'])) {
            $sheet->setCellValueByColumnAndRow(0, 39 - $removedCounter, 'Remarks: '.$adhocAdj['remarks']);
        } else {
            $sheet->removeRow(39 - $removedCounter);
            $removedCounter ++;
        }
        if (!($adhocAdj['converted_amount'] != 0 || !empty($adhocAdj['remarks']))) {
            $sheet->removeRow(40 - $removedCounter);
            $removedCounter ++;
        }

        // Row 41: Settlement Amount Default
        if ($settlementAmountDefault['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 41 - $removedCounter, $batchData->defaultCurrency);
            $text = '';
            if (isset($settlementAmountDefault['converted_amount']) && $settlementAmountDefault['converted_amount'] > 0) {
                $text = sprintf('@%s1:%s%0.4f', $batchData->merchantCurrency, $batchData->defaultCurrency, $settlementAmountDefault['amount'] / $settlementAmountDefault['converted_amount']);
            }
            $sheet->setCellValueByColumnAndRow(2, 41 - $removedCounter, $text);
            $sheet->setCellValueByColumnAndRow(3, 41 - $removedCounter, $settlementAmountDefault['amount']);
            $sheet->setCellValueByColumnAndRow(4, 41 - $removedCounter, $settlementAmountDefault['converted_amount']);
        } else {
            $sheet->removeRow(41 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 42: Settlement Amount Merchant
        if ($settlementAmountMerchant['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 42 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(3, 42 - $removedCounter, $settlementAmountMerchant['amount']);
            $sheet->setCellValueByColumnAndRow(4, 42 - $removedCounter, $settlementAmountMerchant['converted_amount']);
        } else {
            $sheet->removeRow(42 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 43: Settlement Handling Fee
        if ($settlementHandlingFeeMerchant['converted_amount'] != 0) {
            $sheet->setCellValueByColumnAndRow(1, 43 - $removedCounter, $batchData->merchantCurrency);
            $sheet->setCellValueByColumnAndRow(4, 43 - $removedCounter, $settlementHandlingFeeMerchant['converted_amount']);
        } else {
            $sheet->removeRow(43 - $removedCounter);
            $removedCounter ++;
        }
        
        // Row 44: Total Settlement Amount
        $sheet->setCellValueByColumnAndRow(1, 44 - $removedCounter, $batchData->merchantCurrency);
        $sheet->setCellValueByColumnAndRow(4, 44 - $removedCounter, $totalSettlementAmount['converted_amount']);
        
        
        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ]);
        }
    }
    /**
     * Tab: Sales (Multiple currency)
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabSales($excel, $batchData)
    {
        $_sheet = $excel->getSheetByName('Deposit');
        $sheetIndex = $excel->getIndex($_sheet);
        // $_sheet->setCellValue('A7', 'Processing Date: '. (new \DateTime())->format('d-M-Y'));

        if ($batchData->state == BatchState::SETTLED) {
            $resetStyle = [
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ];
            $_sheet->setCellValue('A1', '')
                ->getStyle()
                ->applyFromArray($resetStyle);
        }

        $sheetCounter = 0;


        $fields = [
            'state_date'=>'Date',
            'state'=>'Trans Type',
            'customer_name'=>'Customer',
            'merchant'=>'Account',
            'tx_currency'=>'P. Currency',
            'tx_amount'=>'Amount',
            'tx_fee'=>'Fee',
            'tx_net_amount'=>'Net Amount',
            'settle_currency'=>'M. Currency',
            'convert_rate'=>'FX Rate',
            'tx_convert_amount'=>'Converted Amount',
            'merchant_ref'=>'Merchant Ref',
            'transaction_id'=>'Transaction ID',
        ];
        
        $sizes = [
            'state'=>12,
            'customer_name'=>24,
            'merchant'=>24,
            'tx_currency'=>12,
            'tx_amount'=>20,
            'tx_fee'=>16,
            'tx_net_amount'=>20,
            'state_date'=>12,
            'complete_time'=>12,
            'merchant_ref'=>24,
            'transaction_id'=>40,
            'count'=>12,
            'settle_currency'=>12,
            'amount'=>20,
            'tx_convert_amount'=>20,
            'convert_rate'=>12,
        ];

        $styledColumns = [
            'money'=>['tx_amount','tx_fee','tx_net_amount','converted_amount','tx_convert_amount'],
            'rate'=>['convert_rate'],
        ];

        foreach ([$batchData->defaultCurrency, $batchData->merchantCurrency] as $currencyIndex => $currency) {

            $query = clone $batchData->querySet->sales;
            $query->where()->equal('tx.currency', $currency);
            $rs = $this->service->resultset($query);
            
            $count = $rs->count();

            // Do not clone the sheet if empty result
            if ($count < 1) {
                continue;
            }
            $data = $rs->map(function($entity, $index) use($batchData){
                $entity = $batchData->querySet->mapTransactionRow($entity, $index);
                $entity['tx_amount'] = round((float) $entity['tx_amount'], 2);
                $entity['tx_fee'] = round((float)  $entity['tx_fee'], 2);
                $entity['tx_net_amount'] =round((float)  $entity['tx_net_amount'], 2);
                $entity['converted_amount'] = round((float)  $entity['converted_amount'], 2);
                $entity['tx_convert_amount'] = round((float)  $entity['tx_convert_amount'], 2);
                $entity['convert_rate'] = round((float)  $entity['convert_rate'], 4);
                return $entity;
            });
            $sheet = $_sheet->copy();
            $sheet->setTitle('Deposit - '.$currency);
            
            $excel->addSheet($sheet, $sheetIndex + $sheetCounter);   
            
            $content_offset = 9;
            $sheet->removeRow(9+1, 1);
            
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
            
            $sheetCounter ++;
        }
        $excel -> removeSheetByIndex($sheetIndex + $sheetCounter);
    }

    /**
     * Tab: Refund
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabRefund($excel, $batchData)
    {
        $_sheet = $excel->getSheetByName('Refund');
        $sheetIndex = $excel->getIndex($_sheet);
        // $_sheet->setCellValue('A7', 'Processing Date: '. (new \DateTime())->format('d-M-Y'));

        if ($batchData->state == BatchState::SETTLED) {
            $resetStyle = [
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ];
            $_sheet->setCellValue('A1', '')
                ->getStyle()
                ->applyFromArray($resetStyle);
        }

        $sheetCounter = 0;

        $fields = [
            'state_date'=>'Date',
            'state'=>'Trans Type',
            'customer_name'=>'Customer',
            'merchant'=>'Account',
            'tx_currency'=>'P. Currency',
            'tx_amount'=>'Amount',
            'tx_fee'=>'Fee',
            'tx_net_amount'=>'Net Amount',
            'settle_currency'=>'M. Currency',
            'convert_rate'=>'FX Rate',
            'tx_convert_amount'=>'Converted Amount',
            'merchant_ref'=>'Merchant Ref',
            'transaction_id'=>'Transaction ID',
        ];

        $styledColumns = [
            'money'=>['tx_amount','tx_fee','tx_net_amount','converted_amount', 'tx_convert_amount'],
            'rate'=>['convert_rate'],
        ];
        
        $sizes = [
            'state'=>12,
            'customer_name'=>24,
            'merchant'=>24,
            'tx_currency'=>12,
            'tx_amount'=>20,
            'tx_fee'=>16,
            'tx_net_amount'=>20,
            'state_date'=>12,
            'complete_time'=>12,
            'merchant_ref'=>24,
            'transaction_id'=>40,
            'count'=>12,
            'settle_currency'=>12,
            'amount'=>20,
            'tx_convert_amount'=>20,
            'convert_rate'=>12,
        ];
        
        foreach ([$batchData->defaultCurrency, $batchData->merchantCurrency] as $currencyIndex => $currency) {

            $query = clone $batchData->querySet->refund;
            $query->where()->equal('tx.currency', $currency);
            $rs = $this->service->resultset($query);
            
            $count = $rs->count();

            // Do not clone the sheet if empty result
            if ($count < 1) {
                continue;
            }
            $data = $rs->map(function($entity, $index) use($batchData){
                $entity = $batchData->querySet->mapTransactionRow($entity, $index);
                $entity['tx_amount'] = round((float) $entity['tx_amount'],2);
                $entity['tx_fee'] = round((float)  $entity['tx_fee'], 2);
                $entity['tx_net_amount'] =round((float)  $entity['tx_net_amount'], 2);
                $entity['tx_convert_amount'] =round((float)  $entity['tx_convert_amount'], 2);
                $entity['converted_amount'] = round((float)  $entity['converted_amount'], 2);
                $entity['convert_rate'] = round((float)  $entity['convert_rate'], 4);
                return $entity;
            });
            $sheet = $_sheet->copy();
            $sheet->setTitle('Refund - '.$currency);
            
            $excel->addSheet($sheet, $sheetIndex + $sheetCounter);   
            
            $content_offset = 9;
            $sheet->removeRow(9+1, 1);
            
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
            
            $sheetCounter ++;
        }
        $excel -> removeSheetByIndex($sheetIndex + $sheetCounter);
    }

    /**
     * Tab: Batch Remittance Summary
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabBatchRemittance($excel, $batchData)
    {
        $sheet = $excel->getSheetByName('BatchRemittanceSummary');
        $sheetIndex = $excel->getIndex($sheet);

        $sheet->setTitle('Batch Remittance Summary');

        $result = $batchData->querySet->getResult('batchRemittance');
        $data = $result->data;

        $fields = [
            'id'=>'Batch ID',
            'upload_time'=>'Upload Time',
            'complete_time'=>'Complete Time',
            'count'=>'Count',
            'amount'=>'Amount',
            'convert_currency'=>'Currency',
            'convert_amount'=>'Converted Amount',
            // 'target_name'=>'Channel',
        ];

        $styledColumns = [
            'money'=>['amount','convert_amount'],
            'integer'=>['count'],
        ];
        
        $sizes = [
            'id'=>16,
            'upload_time'=>24,
            'complete_time'=>24,
            'count'=>12,
            'currency'=>12,
            'amount'=>20,
            'convert_amount'=>20,
            'convert_rate'=>12,
            // 'target_name'=>24,
        ];
        $sheet->setCellValue('A7', 'Complete Date: '. (new \DateTime())->format('d-M-Y'));
        
        
        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ]);
        }

        $sheet->removeRow(9+1, 1);
        if (count($data) > 0) {
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
        } else {
            $excel -> removeSheetByIndex($sheetIndex);
        }
    }
    
    /**
     * Tab: Batch Remittance ${yyyymmdd}
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabBatchRemittanceDetail($excel, $batchData)
    {

        $result = $batchData->querySet->getResult('batchRemittance');
        $groupedDates = [];
        // List out all dates from batchRemittance
        foreach ($result->data as $row) {
            $groupDate = date('Ymd', strtotime($row['complete_time']));
            $groupedDates [ $groupDate ] [] = $row;
        }
        
        $fields = [
            'beneficiary_name'=>'Name',
            'account'=>'Account No.',
            'bank_name'=>'Bank Name',
            'bank_branch'=>'Bank Branch',
            'province'=>'Province',
            'city'=>'City',
            'id_number'=>'ID Card No.',
            'convert_currency'=>'Currency',
            '_columnI'=>'Transaction Amount Received',// Column I
            '_columnJ'=>'Transaction Amount Client Received', // Column J
            '_columnK'=>'Gross Amount for Remittance', // Column K =J
            '_columnL'=>'Service Charge', // Column L
            '_columnM'=>'Amount paid by Merchant',  // Column M =K + L
            '_columnN'=>'Amount paid by Merchant (USD)',  // Column N =M + batch rate (B7)
            'merchant_ref'=>'Ref.',
            'id_type'=>'ID Card Type',
        ];

        $styledColumns = [
            'money'=>['_columnI','_columnJ','_columnK','_columnL','_columnM','_columnN'],
            'rate'=>['convert_rate','rate'],
            'integer'=>['index','id_type'],
        ];
        try{

            $_sheet = $excel->getSheetByName('BatchRemittanceDetailSample');
            $sheetIndex = $excel->getIndex($_sheet);
            
            
            if ($batchData->state == BatchState::SETTLED) {
                $_sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                    'fill'=>[
                        'type' => PHPExcel_Style_Fill::FILL_NONE,
                    ]
                ]);
            }
        }catch(\Exception $exp){
            $this->log('error', 'Cannot find the sheet for copy batch remittance detail');
            return;
        }
        $processingDate = (new \DateTime())->format('d-M-Y');
        $sheetCounter = 0;
        $content_offset = 10;

        $currency = $batchData->defaultCurrency;

        $reader = new \RemittanceReportReader();
        foreach ($groupedDates as $groupDate => $remittanceBatchess) {
            $counter = 0;
            foreach ($remittanceBatchess as $batchOffset => $remittanceBatch) {
                $data = [];
                $batchId = $remittanceBatch['id'];

                $batchDetails = $reader->getBatchDetails($batchId, false);
                if (count($batchDetails) < 1) {
                    continue;
                }

                $rate_cell_id = 'B7';

                $rate = null;


                // if ($remittanceBatch['currency'] != 'CNY') {
                //     $rate = $remittanceBatch['convert_rate'];
                // }

                foreach ($batchDetails as $idx => $_row) {
                    $row_offset = $content_offset + $idx+1;

                    if ($rate === null) {
                        if ($_row['currency'] == 'CNY') {
                            $rate = round($_row['convert_rate'], 4);
                        } else {
                            $rate = round(1 / $_row['convert_rate'], 4);
                        }
                    }

                    // Alias value 
                    $row = $row_offset;

                    if ($_row['currency'] == 'CNY') {
                        $_row['_columnI'] = round($_row['amount'], 2);
                        $_row['_columnJ'] = round($_row['amount'], 2);
                        $_row['_columnL'] = round(floatval($_row['paid_amount']), 2) - round($_row['amount'], 2);
                        // $_row['_columnM'] = round(floatval($_row['paid_amount']), 2);
                    } else {

                        $_row['_columnI'] = round($_row['amount'], 2);
                        $_row['_columnJ'] = round($_row['convert_amount'], 2);
                        $_row['_columnL'] = round(floatval($_row['convert_paid_amount']), 2) - round($_row['convert_amount'], 2);
                        // $_row['_columnM'] = round(floatval($_row['convert_paid_amount']), 2);
                    }
                    $_row['_columnM'] = '=J'.$row_offset.'+L'.$row_offset;
                    $_row['_columnK'] = '=J'.$row_offset;
                    $_row['_columnN'] = '=M'.$row_offset.'/'.$rate_cell_id;
                    
                    $_row ['index'] = ++$counter;
                    $_row ['batch_id'] = $batchId;
                    $data [] = $_row;
                }
                $sheet  = $_sheet->copy();
                $sheet->setTitle('Batch Remittance '.$groupDate .' - '.($batchOffset+1));

                $excel->addSheet($sheet, $sheetIndex +  $sheetCounter);   

                $sheet->setCellValue('B6', $batchId);
                $sheet->setCellValue($rate_cell_id, $rate);
                // $sheet->setCellValue('A8', 'Processing Date: '. $processingDate);

                // $sheet->insertNewRowBefore(count($data), 11+1); 
                
                // $sheet->removeRow(11+count($data)+2, 2);

                try{
                    
                    // $sheet->removeRow(10+count($data)+2, 2);
                    $this->buildSheet($sheet, $fields, $data, $styledColumns, null, 10, true);
    
                }catch(\Exception $exp){
                    $this->log('error', $exp->getMessage().PHP_EOL.'Trace:'. PHP_EOL.$exp->getTraceAsString().PHP_EOL);
                    return;
                }
                $sheetCounter++;
            }
        }
        $excel->removeSheetByIndex($sheetIndex + $sheetCounter);
    }

    /**
     * Tab: Batch Remittance Adjustment
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabBatchRemittanceAdj($excel, $batchData)
    {
        $sheet = $excel->getSheetByName('BatchRemittanceAdj');
        $sheetIndex = $excel->getIndex($sheet);

        $result = $batchData->querySet->getResult('batchRemittanceAdj');
        $data = $result->data;

        $fields = [
            'tx_time'=>'Date',
            'mtx_id'=>'Reference ID',
            'amount'=>'CNY Amount',
            'currency'=>'Currency',
            'converted_amount'=>'Converted Amount',
            'convert_rate'=>'Rate',
        ];

        $styledColumns = [
            'money'=>['amount','converted_amount'],
            'rate'=>['convert_rate'],
        ];
        
        $sizes = [
            'tx_time'=>24,
            'mtx_id'=>24,
            'currency'=>12,
            'amount'=>20,
            'converted_amount'=>20,
            'convert_rate'=>12,
        ];
        // $sheet->setCellValue('A7', 'Processing Date: '. (new \DateTime())->format('d-M-Y'));

        
        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ]);
        }

        $sheet->removeRow(9+1, 1);
        if (count($data) > 0) {
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
        } else {
            $excel -> removeSheetByIndex($sheetIndex);
        }
    }

    /**
     * Tab: Instant Remittance
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabInstantRemittance($excel, $batchData)
    {

        $sheet = $excel->getSheetByName('InstantRemittance');
        $sheetIndex = $excel->getIndex($sheet);

        $result = $batchData->querySet->getResult('instantRemittance');
        $data = $result->data;

        foreach ($data as $idx => $row) {
            $row['client_received'] = $row['amount'] * -1;
            $row['fee'] = $row['fee'] * -1;
            $row['paid_amount'] = $row['paid_amount'] * -1;
            $row['converted_amount'] = $row['converted_amount'] * -1;
            $row['gross_amount'] = $row['gross_amount'] * -1;
            $row['amount'] = $row['amount'] * -1;
            $data[ $idx] = $row;
        }

        $fields = [
            'ir_time'=>'Time',
            'name'=>'Beneficiary Name',
            'account'=>'Beneficiary Account No.',
            'bank_name'=>'Bank Name',
            'bank_branch'=>'Bank Branch',
            'province'=>'Province',
            'city'=>'City',
            'id_number'=>'ID Card No.',
            'amount'=>'Transaction Amount Received',
            'client_received'=>'Transaction Amount Client Received',
            'gross_amount'=>'Transaction Amount Client Received',
            'fee'=>'Service Charge',
            'paid_amount'=>'Amount paid by Merchant',
            'convert_currency'=>'Currency',
            'converted_amount'=>'Converted Amount paid by Merchant',
            'convert_rate'=>'Exchange Rate',
            'merchant_ref'=>'Merchant Reference',
            'id_type'=>'ID Card Type',
            'ir_id'=>'Trans ID',
            'remarks'=>'Remarks',
        ];

        $styledColumns = [
            'money'=>['amount', 'client_received', 'converted_amount','gross_amount','fee','paid_amount'],
            'rate'=>['convert_rate'],
        ];
        
        $sizes = [
            'tx_time'=>24,
            'name'=>24,
            'account'=>24,
            'bank_name'=>30,
            'bank_branch'=>30,
            'province'=>18,
            'city'=>18,
            'id_number'=>18,
            'convert_currency'=>12,
            'amount'=>20,
            'client_received'=>20,
            'gross_amount'=>20,
            'fee'=>16,
            'paid_amount'=>20,
            'converted_amount'=>20,
            'convert_rate'=>12,
            'merchant_ref'=>24,
            'id_type'=>24,
            'ir_id'=>48,
            'target_name'=>24,
        ];
        // $sheet->setCellValue('A7', 'Processing Date: '. (new \DateTime())->format('d-M-Y'));

        
        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ]);
        }

        $sheet->removeRow(9+1, 1);
        if (count($data) > 0) {
            
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
        } else {
            $excel -> removeSheetByIndex($sheetIndex);
        }
    }

    /**
     * Tab: Batch Remittance Adjustment
     *
     * @param PHPExcel  $excel     The excel
     * @param BatchData $batchData The batch data
     * 
     * @return void
     */
    protected function tabInstantRemittanceAdj($excel, $batchData)
    {

        $sheet = $excel->getSheetByName('InstantRemittanceAdj');
        $sheetIndex = $excel->getIndex($sheet);

        $sheet->setTitle('Instant Remittance Adj.');

        $result = $batchData->querySet->getResult('instantRemittanceAdj');
        $data = $result->data;

        $fields = [
            'ir_time'=>'Date',
            'mtx_id'=>'Reference ID',
            'convert_currency'=>'Currency',
            'amount'=>'Amount',
            'converted_amount'=>'Converted Amount',
            'convert_rate'=>'Rate',
        ];

        $styledColumns = [
            'money'=>['amount','converted_amount'],
            'rate'=>['convert_rate'],
        ];

        $sizes = [
            'tx_time'=>24,
            'mtx_id'=>24,
            'convert_currency'=>12,
            'amount'=>20,
            'converted_amount'=>20,
            'convert_rate'=>12,
        ];
        // $sheet->setCellValue('A7', 'Processing Date: '. (new \DateTime())->format('d-M-Y'));

        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '');
        }
        
        if ($batchData->state == BatchState::SETTLED) {
            $sheet->setCellValue('A1', '')->getStyle()->applyFromArray([
                'fill'=>[
                    'type' => PHPExcel_Style_Fill::FILL_NONE,
                ]
            ]);
        }

        $sheet->removeRow(9+1, 1);
        if (count($data) > 0) {
            //$sheet->insertNewRowBefore(count($data), 9+1); 
            $this->buildSheet($sheet, $fields, $data, $styledColumns, $sizes, 9, true);
        } else {
            $excel -> removeSheetByIndex($sheetIndex);
        }
    }

}