<?php
    $logfile = __DIR__.'/postback.log';
    //output POST parameters
    print("POST parameters:\n");
    foreach ($_POST as $key => $value) {
        print("$key: $value\r\n");
    }

    //save to log file
    disklog($logfile, data('c'));   //current time
    foreach ($_POST as $key => $value) {
        disklog($logfile, "$key: $value");
    }

    function disklog($filename, $string) {
        $fp = fopen($filename, 'a');
        fwrite($fp, $string."\n");
        fclose($fp);
    }
?>