<?php
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;

const WC_DATETIME_FORMAT = 'YmdHis';
const WC_DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';
const WC_NUM_FORMAT_VALUE_DECIMALS = 2;
const WC_NUM_FORMAT_RATE_DECIMALS = 4;

// Wait until getting lock
function tryFileLock($file = "/tmp/Remittance.lock")
{
    //printf("tryFileLock: %s\n", $file);

    if (!file_exists($file)) {
        file_put_contents($file, '');
    }
    $fp = fopen($file, "r");

    /* Activate the LOCK_NB (Non block) option on an LOCK_EX operation */
    // acquire an exclusive lock
    if (flock($fp, LOCK_EX)) {
        //printf("%s tryFileLock: %s\n", date('c'), 'exclusive lock acquired');
        return $fp;
    } else {
        printf("%s tryFileLock: %s\n", date('c'), 'exclusive lock acquired failed');
    }

    return false;
}

function tryFileUnlock($fp)
{
    if (!$fp) {
        return false;
    }
    //printf("%s\n", 'tryFileUnlock');

    flock($fp, LOCK_UN);    // release the lock
    fclose($fp);
    //printf("%s %s\n", date('c'), 'tryFileUnlock DONE');
    return true;
}

function checkValidDate($d)
{
    if (! preg_match('/^(\d{4})(\d{2})(\d{2})$/', $d, $matches) || count($matches)!=4) {
        return false;
    }
    //var_dump($matches);
    return checkdate($matches[2], $matches[3], $matches[1]);
}

function getCurrentTimeStampString()
{
    return date(WC_DATETIME_DISPLAY_FORMAT);
}

function checkValidWeCollectPassword($value)
{
    $r1='/[A-Z]/';  //Uppercase
    $r2='/[a-z]/';  //lowercase
    $r3='/[~!@#$%^&*()\-_=+{};:,<.>?]/';  // whatever you mean by 'special char'
    $r4='/[0-9]/';  //numbers

    if (preg_match_all($r1, $value, $o)<1) {
        return false;
    }

    if (preg_match_all($r2, $value, $o)<1) {
        return false;
    }

//    if(preg_match_all($r3,$value, $o)<1) return FALSE;

    if (preg_match_all($r4, $value, $o)<1) {
        return false;
    }

    if (strlen($value)<8) {
        return false;
    }
    return true;
}

function maskCardNumber($n, $mask = '*')
{
    $n = trim($n);
    if (strlen($n)>24) {  //non bank a/c
        return $n;
    }
    $n = preg_replace('/\D/', '', $n);
    //15-19 digits long, 6214837825678234
    if (($len=strlen($n))<15) {
        return $n;
    }
    return preg_replace('/(\d{4})(\d+)(\d{4})/', '\1'.str_pad('', $len-8, $mask).'\3', $n);
}

function wcSetNumberFormat(&$arr)
{
    // name of data in digits but not value, like account number
    $nonValueFields = ['account_no', 'id_card', 'merchant_ref', 'count', 'product', 'bank_code'];
    // name of exchange rate
    $rateNamings = ['rate','fxrate'];

    if (!is_array($arr)) {
        return;
    }

    foreach ($arr as $k => $v) {
        // ??_date
        if (preg_match('/(^date$|^time$|\w+\_date$|\w+\_time$)/i', $k)) {
            $arr[$k] = date(WC_DATETIME_FORMAT, strtotime("$v"));
            continue;
        }
        if ((is_string($v) && trim($v)=='') || !is_numeric($v)) {
            continue;
        }
        //could be all digits
        if (in_array(strtolower($k), $nonValueFields)) {
            $arr[$k] = "$v";
            continue;
        }
        //conversion rate, not typical value
        if (preg_match('/_rate$/i', $k) || in_array(strtolower($k), $rateNamings)) {
            $v= round($v, WC_NUM_FORMAT_RATE_DECIMALS);
            $v= number_format($v, WC_NUM_FORMAT_RATE_DECIMALS, '.', '');
        } else {
            $v= round($v, WC_NUM_FORMAT_VALUE_DECIMALS);
            $v= number_format($v, WC_NUM_FORMAT_VALUE_DECIMALS, '.', '');
        }
        $arr[$k] = $v;
    }
}

function wcRoundNumber(&$n, $isRate = false)
{
    $precision = ($isRate?WC_NUM_FORMAT_RATE_DECIMALS:WC_NUM_FORMAT_VALUE_DECIMALS);
    $n = round($n, $precision);
    return $n;
}

function fromCsvStringToArray($str) {
    $data = str_getcsv($str, "\n"); //parse the rows
    $csv = array_map('str_getcsv', $data);
    array_walk($csv, function(&$a) use ($csv) {
        $a = array_combine($csv[0], $a);
    });
    array_shift($csv); # remove column header
    return $csv;
}
/*
 * 1st row is meta data
*/
function fromExcelToArray($f)
{
    if (! is_readable($f)) {
        return false;
    }

    $data = array();
    $filetype = \PHPExcel_IOFactory::identify($f);
    $objReader = \PHPExcel_IOFactory::createReader($filetype);
    /**  Load $inputFileName to a PHPExcel Object  **/
    $excel = $objReader->load($f);
    $sheet = $excel->getActiveSheet();
    $xlsdata = $sheet->toArray(null, false, false, false);
    $excel_keys = null;
    foreach ($xlsdata as $row => $rdata) {
        if (count($rdata)==0) {
            continue;
        }
        if (!is_array($excel_keys)) {
            $excel_keys = array_map('trim', $rdata);
            $excel_keys = array_map('strtolower', $excel_keys);
        } else {
            $tmp = array();
            foreach ($excel_keys as $i => $k) {
                if (isset($rdata[$i])) {
                    $tmp[$k] = $rdata[$i];
                }
            }
            if (count($tmp)==0) {
                continue;
            }
            $data[] = array_map('trim', $tmp);
        }
    }
    return $data;
}

//function fromArrayToExcelFile($data, $filename, $ext = '.xlsx')
function fromArrayToExcelFile($sheets, $filename, $ext = '.xlsx')
{
    if (!is_array($sheets) || ! count($sheets)) {
        return false;
    }

    set_time_limit(180);    //3 mins
    // set caching configuration
    $cacheMethod = \PHPExcel_CachedObjectStorageFactory::cache_in_memory_gzip;
    \PHPExcel_Settings::setCacheStorageMethod($cacheMethod);

    $excel = new PHPExcel();
    foreach ($sheets as $name => $data) {
        //sheet name
        $name = preg_replace('/[^\w\s-]/', '', $name);
        $sheet = new PHPExcel_Worksheet($excel, trim($name));

        $excel->addSheet($sheet, 0);
        $excel->setActiveSheetIndex(0);
        if (! is_array($data)) {
            continue;
        }

        //$meta = array_keys(array_change_key_case($data[0]));
        $meta = array_keys($data[0]);
        PHPExcel_Cell::setValueBinder(new PHPExcel_Cell_WcColumnValueBinder($meta));

        /**
         * Fill worksheet from values in array
         *
         * @param   array   $source                 Source array
         * @param   mixed   $nullValue              Value in source array that stands for blank cell
         * @param   string  $startCell              Insert array starting from this cell address as the top left coordinate
         * @param   boolean $strictNullComparison   Apply strict comparison when testing for null values in the array
         * @throws Exception
         * @return PHPExcel_Worksheet
         */
        $excel->getActiveSheet()->fromArray($meta, null, 'A1', true);
        $excel->getActiveSheet()->fromArray($data, null, 'A2', true);
        //column auto width
        $lastCol = PHPExcel_Cell::stringFromColumnIndex(count($meta) - 1);
        //first row bold
        $excel->getActiveSheet()->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        foreach (range('A', $lastCol) as $colidx) {
            $excel->getActiveSheet()->getColumnDimension($colidx)->setAutoSize(true);
        }

        //$sheet->getStyle("C1")->getNumberFormat()->setFormatCode('0.00');
    }
    /*
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="your_name.xls"');
header('Cache-Control: max-age=0');
*/
    //$writer = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
    $basef1 = basename($filename);
    $basef2 = str_replace(['/',' '], '-', $basef1);
    //$this->logger->debug("saveToExcel: str_replace($basef1, $basef2, $filename)");

    $filename = str_replace($basef1, $basef2, $filename);
    $filename.= $ext;
    $writer = PHPExcel_IOFactory::createWriter($excel, 'Excel2007');
    $writer->save($filename);
    return $filename;
}

function fromArrayToSpoutCsv($data, $filename, $ext = '.csv', $logger = null)
{
    if (!is_array($data) || ! count($data)) {
        return false;
    }

    ini_set('memory_limit', '-1');
    set_time_limit(300);    //5 mins

    $filename.= $ext;

    $is_exist = file_exists($filename);
    if ($logger) {
        $logger->debug('>>>> Looking for file: '.$filename.' (Exist: '.($is_exist ? 'Yes':'No').')');
    }



    // Read the example of appending rows at spout guide:
    // http://opensource.box.com/spout/guides/add-data-to-existing-spreadsheet/

    $writer = WriterFactory::create(Type::CSV);
    //first row bold
    $writer->openToFile($filename.'.tmp');

    $meta = array_keys($data[0]);

    $counter = 0;
    // For existing file, assuming that is appending mode.
    if ($is_exist) {
        $reader = ReaderFactory::create(Type::CSV);
        $reader->open($filename);
        $reader->setShouldFormatDates(true); // this is to be able to copy dates
        
        // Only one sheet there.
        foreach ($reader->getSheetIterator() as $sheetIdx => $sheet) {
            if ($logger) {
                $logger->debug('Sheet'. $sheetIdx.' started');
            }

            foreach ($sheet->getRowIterator() as $idx => $row) {
                $writer->addRow($row);
                $counter++;
            }
            if ($logger) {
                $logger->debug('Sheet'. $sheetIdx.' ended at '.$counter);
            }
        }
        $reader->close();
        if ($logger) {
            $logger->debug('File closed for file '.$filename);
        }
    } else {
        $writer->addRow($meta);
    }

    foreach ($data as $idx => $row) {
        $r = [];
        foreach ($meta as $idx => $name) {
            $r[ $idx ] = '';
            if (isset($row[$name])) {
                $r[ $idx] = $row[$name];
            }
        }
        $writer->addRow($r);
        $counter++;
    }
    if ($logger) {
        $logger->debug('Data wrote '.$counter);
    }

    $writer->close();

    // Move to original file
    if ($is_exist) {
        if ($logger) {
            $logger->debug('moving files: '.$filename);
        }
        @unlink($filename);
    }
    rename($filename.'.tmp', $filename);
    return $filename;
}

function fromArrayToSpoutExcel($sheets, $filename, $ext = '.xlsx', $logger = null)
{
    if (!is_array($sheets) || ! count($sheets)) {
        return false;
    }
    ini_set('memory_limit', '-1');
    set_time_limit(300);    //5 mins
    $writer = WriterFactory::create(Type::XLSX); // for XLSX files
//$writer = WriterFactory::create(Type::CSV); // for CSV files
//$writer = WriterFactory::create(Type::ODS); // for ODS files
    /*
    $basef1 = basename($filename);
    $basef2 = str_replace(['/',' '],'-', $basef1);
    //$this->logger->debug("saveToExcel: str_replace($basef1, $basef2, $filename)");
    $filename = str_replace($basef1, $basef2, $filename);
    */
    $filename.= $ext;

    $is_exist = file_exists($filename);
    if ($logger) {
        $logger->debug('>>>> Looking for file: '.$filename.' (Exist: '.($is_exist ? 'Yes':'No').')');
    }


    $sheets_indexes = array_keys($sheets);
    
    $writer->openToFile($filename.'.tmp');
    $firstSheet = $writer->getCurrentSheet();


    // Setup header style
    //
    $style = (new StyleBuilder())
        ->setFontBold()
//        ->setShouldWrapText()
/*        ->setFontSize(15)
        ->setFontColor(Color::BLUE)
        ->setBackgroundColor(Color::YELLOW)
*/
        ->build();

    $counter = 0;
    // For existing file, assuming that is appending mode.
    if ($is_exist) {
        $reader = ReaderFactory::create(Type::XLSX);
        $reader->open($filename);
        $reader->setShouldFormatDates(true); // this is to be able to copy dates
            
        foreach ($reader->getSheetIterator() as $sheetIdx => $sheet) {
            if ($logger) {
                $logger->debug('Sheet'. $sheetIdx.' started');
            }

            foreach ($sheet->getRowIterator() as $rowIdx => $row) {
                if ($rowIdx == 1) {
                    $writer->addRowWithStyle($row, $style);
                } else {
                    $writer->addRow($row);
                }
            }
                $counter++;
        }
        if ($logger) {
            $logger->debug('Sheet'. $sheetIdx.' ended at '.$counter);
        }


            // Insert records

        if ($logger) {
            $logger->debug('File closed for file '.$filename);
        }
        $reader->close();
    }
    $sheetIdx = 0;
    foreach ($sheets as $name => $data) {
        if (! is_array($data)) {
            continue;
        }

        if (!$is_exist) {
            if (count($sheets_indexes) > 1 && $sheetIdx > 0) {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
            } else {
                $sheet = $writer->getCurrentSheet();
            }
            $name = preg_replace('/[^\w\s-]/', '', $name);
            $sheet->setName($name);
            
            if (count($data) > 0) {
                $meta = array_keys($data[0]);

                //$meta = array_keys(array_change_key_case($data[0]));
                $writer->addRowWithStyle($meta, $style);
            }
        }

        if (count($data) > 0) {
            //first row bold
            $writer->addRows($data);
        }


        $sheetIdx ++;
    }

    $writer->close();

    // Move to original file
    if ($is_exist) {
        if ($logger) {
            $logger->debug('moving files: '.$filename);
        }
        @unlink($filename);
    }
    rename($filename.'.tmp', $filename);
    return $filename;
}

function unicode_trim($s)
{
    //return preg_replace('/^[\pZ\pC]?([\PZ\PC]*)[\pZ\pC]?$/u', '$1', $str);
    $s = preg_replace('/^[\pZ\pC]?([\PZ\PC]*)[\pZ\pC]?$/u', '$1', $s);
    $s = str_replace(['\u00a0',chr(194).chr(160)], '', $s);
    return $s;
}

function simpleCallURL(
    $url,
    $param = null,
    $cookie = '',
    $useragent = 'PHP/5.5',
    //$useragent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_2) AppleWebKit/535.2 (KHTML, like Gecko) Chrome/15.0.874.120 Safari/535.2',
    $header = null,
    $isPost = true,
    $returnHeader = false,
    $followLocation = false,
    $timeout = 180,
    $referer = ''
) {
    $ch = curl_init();
    if ($isPost) {
        curl_setopt($ch, CURLOPT_POST, true);
    } else {
        curl_setopt($ch, CURLOPT_HTTPGET, true);
    }

    if (is_array($header)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    } else {
//curl_setopt($ch, CURLOPT_HEADER, false);
    }    curl_setopt($ch, CURLINFO_HEADER_OUT, true); //1
    if (is_array($param) || $param!=null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    //timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 180);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout
    if ($followLocation) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }
    //todo
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if (! $useragent=='') {
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    }

    if ($cookie != '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($referer!='') {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }

//  dlog("URL:$url\nCookie:\n$cookie\nReferer:$referer\n");
//curl_setopt($ch,CURLOPT_PROXY,'127.0.0.1:8888');
    if ($returnHeader) {
        curl_setopt($ch, CURLOPT_HEADER, true); //1
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //1
    $result=curl_exec($ch);
//  $contenttype=curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return $result;
}
