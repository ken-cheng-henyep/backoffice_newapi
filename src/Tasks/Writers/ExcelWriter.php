<?php 

namespace App\Tasks\Writers;

use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell_DataType;
use PHPExcel_CachedObjectStorageFactory;
use PHPExcel_Settings;
use PHPExcel_Style_Border;
use PHPExcel_Cell;

use \Cake\Log\Log;

/**
 * Provide a standalized interface to produce spreadsheet content with styling
 */
abstract class ExcelWriter extends ContentWriter 
{
    public $styles = [
        'header'=>[
            'font'=>[
                'bold'=>true,
            ],
            'borders'=>[
                'bottom'=>[
                    'style'=>PHPExcel_Style_Border::BORDER_THIN,
                ],
            ]
        ],
        'money'=>[
        ],
        'rate'=>[
        ],
        'integer'=>[
        ],
        'longText'=>[
            'alignment'=>[
                'vertical'=> \PHPExcel_Style_Alignment::VERTICAL_TOP,
                'wrap'=> true,
            ],
        ],
    ];

    public $numberFormats = [
        'money'=>[
           'code' =>'#,##0.00_-',
        ],
        'rate'=>[
           'code' =>'#,##0.0000_-',
        ],
        'integer'=>[
           'code' =>'#,##0_-',
        ]
    ];

    /**
     * Giving fields, data, styledColumns to build sheet content with styles
     *
     * @param PHPExcel_Worksheet $sheet         The worksheet
     * @param array              $fields        The list of fields for first row
     * @param array              $data          The content of the sheet
     * @param array              $styledColumns The style setting
     * @param array              $sizes         The size of each column
     * @param integer            $offset        The rows offset.
     * @param boolean            $skipHeader    Pass true if the header row is
     *                                          not necessary
     * 
     * @return void
     */
    protected function buildSheet($sheet, $fields, $data, $styledColumns, $sizes = null, $offset = 0, $skipHeader = false)
    {

        $headerRowIndex = $offset + 1;
        $dataRowIndex = ($skipHeader ? $offset: $headerRowIndex) + 1;

        $fieldNames = array_keys($fields);

        $columnFields = [];
        foreach ($fieldNames as $col => $fieldName) {
            $columnFields [ $fieldName] = PHPExcel_Cell::stringFromColumnIndex($col);
        }

        // Translate into column id in spreadsheet
        $maxColumnName = PHPExcel_Cell::stringFromColumnIndex(count($fieldNames) - 1);

        ///// Content /////
        $totalDataRows = count($data);

        // Import data by using fields
        if ($totalDataRows > 0) {
            $sheet->insertNewRowBefore($dataRowIndex+1, $totalDataRows);
            foreach ($data as $idx => $row) {
                foreach ($fieldNames as $col => $fieldName) {
                    if (isset($row[ $fieldName])) {
                        $columnName = $columnFields [ $fieldName];
                        $val = $row[ $fieldName];
                        $cellId = $columnName. ( $idx + $dataRowIndex);
                        if (!is_string($val) || substr(''.$val, 0, 1) == '=') {
                            $sheet->setCellValue($cellId, $val);
                        } else {
                            $sheet->setCellValueExplicit($cellId, $val);
                        }
                    }
                }
            }
        }
        
        // Setup data style
        foreach ($styledColumns as $styleName => $_styledFields) {
            
            foreach ($_styledFields as $fieldName) {
                if (!isset($columnFields [ $fieldName])) {
                    continue;
                }
                $columnName = $columnFields [ $fieldName];

                // Select the range
                $rangeString = $columnName.$dataRowIndex.':'
                    .$columnName.(count($data)+ $headerRowIndex );

                try{
                    $rangeStyle = $sheet->getStyle($rangeString);

                    if (isset($this->styles[$styleName])) {
                        $rangeStyle
                            ->applyFromArray($this->styles[$styleName]);
                    }

                    if (isset($this->numberFormats[$styleName])) {
                        $rangeStyle
                            ->getNumberFormat()
                            ->applyFromArray($this->numberFormats[$styleName]);
                    }
                }catch(\Exception $exp){
                    Log::write('error', 'Exception during styling applying: '
                        .$exp->getMessage().PHP_EOL
                        .'Stacks:'.PHP_EOL
                        .$exp->$exp->getTraceAsString().PHP_EOL
                    );
                }
            }
        }
        
        ///// HEADER /////
        // We import header after data rows for correcting autoSize.
        if (!$skipHeader) {
            // Set Header Style
            if (!empty($this->styles['header'])) {
                $rangeString = "A".$headerRowIndex.":"
                    .$maxColumnName.$headerRowIndex;
                $sheet
                    ->getStyle($rangeString)
                    ->applyFromArray($this->styles['header']);
            }

            $sheet->getDefaultColumnDimension()->setWidth(10);

            // Import the field's name in first row as header
            foreach ($fieldNames as $col => $fieldName) {
                $columnName = $columnFields [ $fieldName];
                $text  =$fields[ $fieldName];
                $cellId = $columnName. $headerRowIndex;
                $sheet->setCellValueExplicit($cellId, $text);

                
                if (isset($sizes[ $fieldName])) {
                    $sheet->getColumnDimension($columnName)->setWidth($sizes[ $fieldName]);
                }
            }
        }

    }
}