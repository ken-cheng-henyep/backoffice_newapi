<?php
require dirname(__DIR__) . '/config/bootstrap.php';

include 'vendor/autoload.php';

DB::$user = 'mysqlu';
DB::$password = '362gQtSA_QA7QroNS';
DB::$dbName = 'srd_dev';
DB::$encoding = 'utf8';
DB::$error_handler = false; // since we're catching errors, don't need error handler
DB::$throw_exception_on_error = true;

define('DATABASE_TABLE', 'banks');

/*
$mid='testonly';
$mid='ab124bac-a6a4-11e4-8537-0211eb00a4cc';
$mid='9dd5a398-c897-11e4-a1b7-0211eb00a4cc';
//FxPro Financial Services Ltd
$mid='bb3903bc-c58e-11e6-9728-0211eb00a4cc';
// ICM Capital Limited
$mid='5d253a18-0f92-11e7-9480-0211eb00a4cc';

$wallet = new MerchantWallet($mid);
$st = $wallet->createAccount($name='Basic Account', $amt=0);

var_dump($st);
exit;
*/
updateMasterMerchant();
exit;

//update batch compelte rate
$reader = new RemittanceReportReader('srd_dev');
DB::debugMode();

_updateBatchTable();

exit;

$bid='5912b5b1012b0';
$complete_rate='6.8940';
$bid='59155b23e4da7';
$complete_rate='6.8928';

$bid='5926810f21d99';
$complete_rate='6.8810';
$bid='592e71ee5a77e';
$complete_rate='6.8286';

$reader->updateBatch($bid, FALSE, 0, $complete_rate);

/*
 * Update currency of batch table
 */
function _updateBatchTable() {
    $sql = "SELECT l.* FROM remittance_batch b join remittance_log l on (b.id = l.batch_id) 
WHERE (b.currency is null or b.convert_currency is null ) AND (l.currency is not null or l.convert_currency is not null); ";

    $res = DB::query($sql);

    //var_dump($res[0]);
    foreach ($res as $r) {
        if (empty($r['currency']))
            continue;
        $updates = ['currency'=>$r['currency'], 'convert_currency'=>$r['convert_currency'],
            //'id'=>$r['batch_id']
        ];
        //var_dump($updates);
        //DB::update('remittance_batch', $updates, "id=%s", $r['batch_id']);
    }
}

/*
 * update master group id to PC banking merchant id
 */
function updateMasterMerchant() {
    $sql = "SELECT * FROM merchants_group_id WHERE master = 1 and id != merchant_id ;";
    $res = DB::query($sql);

    var_dump($res[0]);
    $lst = [
        ['id'=>'amana', 'merchant_id'=>'b1f09b56-4cfe-11e7-be04-0242ac110002'],
        ['id'=>'axicorp', 'merchant_id'=>'f4c1e576-2be3-11e6-822f-0211eb00a4cc'],
        ['id'=>'bestleader', 'merchant_id'=>'da5a8850-6b76-11e6-9133-0211eb00a4cc'],
        ['id'=>'caizhangdie', 'merchant_id'=>'052e3402-df26-11e4-95af-0211eb00a4cc'],
        ['id'=>'cccbullion', 'merchant_id'=>'6e068052-a62f-11e6-a5f7-0211eb00a4cc'],
        ['id'=>'chancellor', 'merchant_id'=>'a525bed2-38ec-11e6-9e34-0211eb00a4cc'],
        ['id'=>'csf', 'merchant_id'=>'47da5cd6-5ae5-11e6-ad2b-0211eb00a4cc'],
        ['id'=>'drivewealth', 'merchant_id'=>'b7b22b38-0c3e-11e6-9cf2-0211eb00a4cc'],
        ['id'=>'ftg', 'merchant_id'=>'de4db6ea-49b9-11e7-8061-0242ac110002'],
        ['id'=>'fullerton', 'merchant_id'=>'af840818-14f4-11e7-946e-0211eb00a4cc'],
        ['id'=>'gmoz_hk', 'merchant_id'=>'23606ab0-d3e6-11e6-94a5-0211eb00a4cc'],
        ['id'=>'golday', 'merchant_id'=>'451e1344-c1cd-11e6-9cbf-0211eb00a4cc'],
        ['id'=>'hantec_au', 'merchant_id'=>'1eeac382-e8e9-11e6-87ea-0211eb00a4cc'],
        ['id'=>'hycm_eur', 'merchant_id'=>'0bb040ee-1bd5-11e6-b805-0211eb00a4cc'],
        ['id'=>'jfd', 'merchant_id'=>'70fe90ec-3914-11e7-9335-065f4973d3e3'],
        ['id'=>'juno', 'merchant_id'=>'91de5842-24e2-11e7-9f54-065f4973d3e3'],
        ['id'=>'kab', 'merchant_id'=>'27f03926-3920-11e7-9361-065f4973d3e3'],
        ['id'=>'lyncpay', 'merchant_id'=>'db6e0dee-3826-11e6-b994-0211eb00a4cc'],
        ['id'=>'maxi', 'merchant_id'=>'d4d48518-de1f-11e6-b219-0211eb00a4cc'],
        ['id'=>'mnc', 'merchant_id'=>'cdd2e648-39b9-11e6-8add-0211eb00a4cc'],
        ['id'=>'offshore', 'merchant_id'=>'cb0ca074-26fa-11e6-b850-0211eb00a4cc'],
        ['id'=>'pico', 'merchant_id'=>'67f16a46-159a-11e5-9bff-0211eb00a4cc'],
        ['id'=>'rakuten', 'merchant_id'=>'e280913e-020f-11e7-b4e6-0211eb00a4cc'],
        ['id'=>'hpi', 'merchant_id'=>'accd390c-ec34-11e6-ba34-0211eb00a4cc'],
        ['id'=>'scmau', 'merchant_id'=>'b9a9d3ae-4b9c-11e5-bfe2-0211eb00a4cc'],
        ['id'=>'tfgm', 'merchant_id'=>'c9b1d3c6-2a72-11e7-8319-0211eb00a4cc'],
        ['id'=>'travelsky', 'merchant_id'=>'d592c3e4-d131-11e5-93fb-0211eb00a4cc'],
        ['id'=>'wci', 'merchant_id'=>'8788b4dc-1452-11e7-85f3-0211eb00a4cc'],
        ['id'=>'wingfung', 'merchant_id'=>'23a5d7a2-9440-11e6-a6c0-0211eb00a4cc'],
        ['id'=>'jindouyun', 'merchant_id'=>'5c8f742a-607c-11e7-a7a1-0242ac110002'],
        ['id'=>'fxnet', 'merchant_id'=>'dab33384-0dd7-11e7-80d0-0211eb00a4cc'],
        ['id'=>'hansard_eur', 'merchant_id'=>'86b38920-15b0-11e6-85d2-0211eb00a4cc'],
        ['id'=>'hansard_gbp', 'merchant_id'=>'ed6ca76c-168e-11e6-976e-0211eb00a4cc'],
    ];


    //foreach ($res as $r) {
    foreach ($lst as $r) {
        $oid = $r['id'];
        $mid = $r['merchant_id'];

        DB::update('merchants_group', ['id'=>$mid], "id=%s", $oid);
        DB::update('merchants_group_id', ['id'=>$mid], "id=%s", $oid);
        //break;
    }

}

exit;

$f = __DIR__."/banks.xlsx";
print("file:$f\n");

$data = fromExcelToArray($f);
/*
var_dump($data);

exit;
*/
foreach ($data as $row) {
    var_dump($row);

    try {
        DB::insert(DATABASE_TABLE, $row);
    } catch (Exception $e) {
        printf("Exception: %s\n", $e);
    }
    //break;
}
