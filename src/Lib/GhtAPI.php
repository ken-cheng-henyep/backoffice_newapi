<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Flintstone\Flintstone;

class GhtAPI  {
    const DATABASE_ADDRESS = 'localhost';
    const DATABASE_NAME = 'srd_dev';    //'payment_dev';
    const DATABASE_USER = 'mysqlu';
    const DATABASE_PASSWORD = '362gQtSA_QA7QroNS';
    const GW_BANK_CODE_TABLE = 'gateway_bank_code';

    const USERAGENT_STRING = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:14.0) Gecko/20100101 Firefox/14.0.1';
    const WECOLLECT_URL = 'https://secure.wecollect.com/postback/chinagpay';
    const SIGN_ALGORITHM = 'sha256';
    //testing details
    const BACKEND_URL = 'http://120.31.132.119/entry.do?';
    const MERCHANT_ID = '102100000125';
    const TERMINAL_NUM = '20000147';
    const MERCHANT_KEY = '857e6g8y51b5k365f7v954s50u24h14w';
    /*
     * https://epay.gaohuitong.com:8443/entry.do
     */
    //PRD
    /*
    const BACKEND_URL = 'https://epay.gaohuitong.com:8443/entry.do?';
    const MERCHANT_ID = '549440153990303';
    const TERMINAL_NUM = '20000260';
    const MERCHANT_KEY = '303f58f71bdba102a3737f703d10de0b';
*/
    function __construct($debug=false) {
        $this->debug=$debug;
        $this->logger = new Logger('wc_logger');
        $this->logger->pushHandler(new StreamHandler(ROOT.'/logs/GhtAPI.log', Logger::DEBUG));

        \DB::$user = self::DATABASE_USER;
        \DB::$password = self::DATABASE_PASSWORD;
        \DB::$dbName = self::DATABASE_NAME;
        //DB::$host = '123.111.10.23'; //defaults to localhost if omitted
        //DB::$port = '12345'; // defaults to 3306 if omitted
        \DB::$encoding = 'utf8'; // defaults to latin1 if omitted
        if ($this->debug)
            \DB::debugMode();
    }

    function getSignature(Array $params, $key)
    {
        unset($params['sign_type']);
        unset($params['sign']);

        ksort($params);
        $str = http_build_query($params);
        /*
        $str = '';
        foreach ($params as $k => $v) {
            $str .= "{$k}={$v}&";
        }
        $str = substr($str, 0, -1);
        $str .= $key; // "88888888";r
        */
        $str .= "&key=$key";
        $this->logger->debug("getSignature(): $str");

        $signature = hash(self::SIGN_ALGORITHM, $str, false);
        return $signature;
    }

    function sendQuery($url, $data, $postdata=true)
    {
        $this->logger->debug("sendQuery($url, %s, $postdata)", $data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, trim($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // server max_execution = 120
        if ($postdata) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            self::USERAGENT_STRING
        );
        $content = curl_exec($ch);
        return $content;
    }

    //Order Inquiry Interface
    function query($merOrderId)
    {
        $v = [
            'busi_code' => 'SEARCH',
            'merchant_no' => self::MERCHANT_ID,
            'terminal_no' => self::TERMINAL_NUM,
            'order_no' => $merOrderId,
            //'sign_type' => strtoupper(self::SIGN_ALGORITHM),
        ];
/*
        $v = [
            'busi_code' => 'SEARCH_RATE',
            'merchant_no' => self::MERCHANT_ID,
            'terminal_no' => self::TERMINAL_NUM,
            //'order_no' => $merOrderId,
            //'sign_type' => strtoupper(self::SIGN_ALGORITHM),
        ];
*/
        $v['sign'] = $this->getSignature($v, self::MERCHANT_KEY);

        ksort($v);

        $url = self::BACKEND_URL . http_build_query($v);
        $response = $this->sendQuery($url, array(), false);
        /*
        $url = self::BACKEND_URL;
        $response = $this->sendQuery($url, http_build_query($v), true);
*/
        $this->logger->debug("result:\n$response");
        /*
$response=<<<XMLSTR
<?xml version="1.0" encoding="UTF-8" ?>
<root>
<resp_code>00</resp_code>
<resp_desc>Success</resp_desc>
<busi_code>QUERY</busi_code>
<merchant_no>102100000125</merchant_no>
<terminal_no>20000120</terminal_no>
<order_no>1440743671397</order_no><currency_type>CNY</currency_type>
<sett_currency_type>CNY</ sett_currency_type>
<exchg_rate></exchg_rate>
<amount>0.01</amount>
<pay_no>259900</pay_no>
<pay_result>1</pay_result>
<pay_time>20150828150757</pay_time>
<sett_date>20150828</sett_date>
<sett_time>150757</sett_time>
<sign_type>SHA256</sign_type>
<sign>63a8af68deb840baf999663b47760e24a17f36322587f20b1caa1a3bb9d673b2</sign>
</root>
XMLSTR;
*/
        if ($response) {
            $xml = simplexml_load_string($response);
            if ($xml===FALSE)
                return false;

            var_dump($xml);
            $this->logger->debug('xml:'.$xml->resp_code) ;

        } else {
            //echo "\n error...:: " . $merOrderId;
            $this->logger->debug('No response');
        }

        return false;
    }


    public function isGatewayBankExists($code) {
        if (empty($code))
            return false;
        $checks = \DB::queryFirstRow("SELECT  id FROM %b WHERE upper(code)=%s ;", self::GW_BANK_CODE_TABLE, strtoupper($code));
        if (isset($checks['id'])) {
            return $checks['id'];
        }
        return false;
    }

    public function update_gateway_bank_code($code, $name, $gateway='GHT') {
        if (empty($code))
            return false;
        /*
        if ($this->isGatewayBankExists($code))
            return false;
        */
        print(" update_gateway_bank_code($code, $name, $gateway)\n");
        //return false;
        $scode = $code;
        if (preg_match('/(\w{3,})B2B$/', $scode, $matches)) {
            //var_dump($matches);
            $scode = $matches[1];
        } elseif(preg_match('/(\w{3,})-NET-QBY$/', $scode, $matches)) {
            //var_dump($matches);
            $scode = $matches[1];
        }

        $pcapi = new PayConnectorAPI();
        $bcode = $pcapi->getBankCode($scode);

        $dbs = ['gateway'=>$gateway, 'code'=>$code, 'name'=>$name, 'bank_code'=>$bcode];
        if (!$this->isGatewayBankExists($code)) {
            \DB::insert(self::GW_BANK_CODE_TABLE, $dbs);
        } elseif ($bcode) {
            \DB::update(self::GW_BANK_CODE_TABLE, $dbs, "gateway=%s AND code=%s", $gateway, $code);
        }

    }

    function insertAllGhtBankCode() {
$codetxt = <<<BANKCODE
ICBC,Industrial and Commercial Bank of China
BOC,Bank of China
COMM,Bank of Communications
ABC,Agricultural Bank of China
CCB,China Construction Bank
CITIC,China CITIC Bank
SDB,Shenzhen Development Bank(Now subsumed by Ping An Bank)
CMBC,China Minsheng Bank
GDB,Guangdong Development Bank
CIB,China Industrial Bank
SPDB,Shanghai Pudong Development Bank
SPABANK,Ping An Bank
CMB,China Merchants Bank
CEBBANK,China Everbright Bank
HXBANK,Huaxia Bank
POSTGC,Post Savings Bank of China
SHB,Shanghai Bank
BCCB,Bank of Beijing
BJRCB,Beijing Rural Commercial Bank
ICBCB2B,Industrial and Commercial Bank of China
BOCB2B,Bank of China
ABCB2B,Agricultural Bank of China
CCBB2B,China Construction Bank
CMBB2B,China Merchants Bank
CEBB2B,China Everbright Bank
SPABANKB2B,Ping An Bank
SPDBB2B,Shanghai Pudong Development Bank
COMMB2B,Bank of Communications
CEB-NET-QBY,China Everbright Bank’s quick payment
CMB-NET-QBY,China Merchants Bank’s quick payment
SHB-NET-QBY,Shanghai Bank’s quick payment
PSBC-NET-QBY,Post Savings Bank of China’s quick payment
GDB-NET-QBY,Guangdong Development Bank’s quick payment
ECITIC-NET-QBY,China CITIC Bank’s quick payment
CMBC-NET-QBY,China Minsheng Bank’s quick payment
BOB-NET-QBY,Bank of Beijing’s quick payment
HXB-NET-QBY,Huaxia Bank’s quick payment
SPDB-NET-QBY,Shanghai Pudong Development Bank’s quick payment
CIB-NET-QBY,China Industrial Bank’s quick payment
PAB-NET-QBY,Ping An Bank’s quick payment
CCB-NET-QBY,China Construction Bank’s quick payment
BOC-NET-QBY,Bank of China’s quick payment
ABC-NET-QBY,Agricultural Bank of China’s quick payment
ICBC-NET-QBY,Industrial and Commercial Bank of China’s quick payment
BANKCODE;
        foreach (explode("\n", $codetxt) as $line) {
            $line = trim($line);
            list($code, $name) = explode(',', $line,2);
            $code = trim($code);
            $name = trim($name);
            $this->update_gateway_bank_code($code, $name);
        }
    }
}