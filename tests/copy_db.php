<?php
require dirname(__DIR__) . '/config/bootstrap.php';

include 'vendor/autoload.php';

$bid = '598ae07567946';

$table = 'remittance_batch';
    //$this->db_name = $db;
    //DB::$host = '10.128.37.120';    //$db_host;
    DB::$user = $user = 'mysqlu';
    DB::$password = $pass = '362gQtSA_QA7QroNS';
    DB::$dbName = $dbName = 'srd_dev';
    DB::$encoding = $encoding = 'utf8';
    $port = '3306';
    DB::$error_handler = false; // since we're catching errors, don't need error handler
    DB::$throw_exception_on_error = true;
    // DB::debugMode(); // echo out each SQL command being run, and the runtime
//DB::useDB('my_other_database');

    copyBatch($bid);

function copyBatch($id) {
    global $table, $user, $pass, $dbName, $encoding, $port;

    $bres = DB::queryFirstRow("SELECT * FROM %b WHERE id=%s", $table, $id);

    var_dump($bres);

    $lres = DB::query("SELECT * FROM remittance_log WHERE batch_id LIKE %s ", $id) ;
    //unset id
    foreach ($lres as $k=>$r) {
        unset($r['id']);
        $lres[$k] = $r;
        /*
        $lres[$k] = array_filter($r, function($av, $ak) {
            return $ak != 'id';
        });
        */
    }
    var_dump($lres);

    $apires = DB::query("SELECT * FROM remittance_api_log WHERE batch_id LIKE %s ", $id) ;
    //var_dump($apires);

    if (is_array($bres)) {
        $db2 = new MeekroDB('10.128.37.120', $user, $pass, $dbName, $port, $encoding);

        $db2->insert($table, $bres);
        $db2->insert('remittance_log', $lres);
        $db2->insert('remittance_api_log', $apires);
    }
}