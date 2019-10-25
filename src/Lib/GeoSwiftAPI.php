<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
class GeoSwiftAPI {
	const SANDBOX_DOMAIN = 'https://sandboxp2p.georemittance.com';
	const PRODUCTION_DOMAIN = 'https://p2p.georemittance.com';
	const API_URL = self::SANDBOX_DOMAIN . "/api/v1/remit";
	const CHECKSUM_ALGORITHM = 'sha256';
	const WECOLLECT_NAME = "WeCollect";
	const SENDER_BANKSWIFT = "HSBCHKHHHKH";
	const SENDER_DOB = "1990-01-01";
	const ID_TYPE_DRIVER_LICENSE = 1;
	const ID_TYPE_ID_CARD = 2;
	const RECEIVER_TYPE = "INDIVIDUAL";
	const CURRENCY = "USD";
	const WALLET = "USD-USD Wallet";
	const GATEWAY = "P-IN-1";
	const COUNTRY_CODE_HK = "HK";
	const STATUS_REQUEST_RECEIVED = "REQUEST RECEIVED";
	const STATUS_PENDING = "PENDING";
	const STATUS_FAILED = "FAILED";
	const STATUS_SUCCEED = "SUCCEED";
	const TIMENOW_STR = 'Y-m-d H:i:s';
	const LOG_TABLE = 'remittance_api_log';

	// Sandbox
	private static $username = "wecollect_api";
	private static $pwd = "E1RH6g6OTJ6TYHEJ";
	private static $merchant_key = "W7RUOjOR+SCs5RuC88ZYs06sM8oyc5uUf+c3f9mfTGY=";
	private static $logFilePath = '/logs/GeoSwiftAPI.log';
	private $logger, $debug;
	public $orderId;

	function __construct($debug = false) {
		$this->debug = $debug;
		$this->logger = new Logger('wc_logger');
		if (! defined('ROOT'))
			define('ROOT', dirname(__DIR__));
		$this->logger->pushHandler(new StreamHandler(ROOT . self::$logFilePath, Logger::DEBUG));
	}

	public static function formatResultTime($resultTime, $gmt = 8) {
		$output = "";
		$date = substr($resultTime, 0, 10);
		$hour = intval(substr($resultTime, 11, 2));
		$minsec = substr($resultTime, 13, 6);
		$op = substr($resultTime, 19, 1);
		$processTime = intval(substr($resultTime, 20, 2));
		$processTime = $op === "+" ? ($gmt - $processTime) : ($gmt + $processTime);
		$hour = $hour + $processTime;
		$output = $date . " " . $hour . $minsec . " GMT" . ($gmt >= 0 ? "+" : "-") . sprintf("%02d:00", $gmt);
		return $output;
	}

	public function getPinYin($input) {
		$pinyin = new \Overtrue\Pinyin\Pinyin();
		$result = $pinyin->sentence($input, PINYIN_NO_TONE);
		return $result;
	}

	public function getValidName($name) {
		return preg_replace("/ +/", " ", trim(preg_replace("/\d/", "", $name)));
	}

	function setOrderId($id) {
		$this->orderId = str_pad(preg_replace('/[^A-Za-z0-9]/', '', $id), 32, '0', STR_PAD_LEFT);
	}

	function getChecksum(Array $params) {
		$valueStr = "";
		$valueStr .= $params["merchantReference"] . $params["receiver"]["name"] . $params["receiver"]["bankCardNumber"] . $params["receiver"]["idNumber"] . $params["transaction"]["currency"] . $params["transaction"]["amount"] . $params["transaction"]["wallet"] . $params["transaction"]["gateway"] . self::$merchant_key;
		return hash(self::CHECKSUM_ALGORITHM, $valueStr);
	}

	function isSuccess($response) {
		$response_arr = json_decode($response, true);
		if ($response_arr != null) {
			if ($response_arr["status"] === self::STATUS_SUCCEED) {
				return true;
			} elseif ($response_arr["status"] === self::STATUS_FAILED) {
				return false;
			} elseif ($response_arr["status"] === self::STATUS_REQUEST_RECEIVED || $response_arr["status"] === self::STATUS_PENDING) {
				return 0;
			}
		} else {
			return false;
		}
	}

	public function log2DB($params, $request, $response, $status, $date) {
		if (isset($params['batch_id'])) {
			$logs = [
				'batch_id' => $params['batch_id'],
				'log_id' => $params['id']
			];
		} else {
			$logs = [
				'req_id' => $params['id'] // ,'bank_code'=> $params['gpay_code']
			];
		}

		$logs['request'] = $request;
		$logs['response'] = $response;
		$logs['status'] = $status;
		$logs['create_time'] = $date;
		$logs['complete_time'] = date(self::TIMENOW_STR);

		$response_arr = json_decode($response, true);
		$logs['return_code'] = (! empty($response_arr["errorCode"]) ? $response_arr["errorCode"] : null);
		$logs['return_msg'] = (! empty($response_arr["errorMessage"]) ? $response_arr["errorMessage"] : null);

		$logs['id'] = $this->orderId;
		$logs['processor'] = 'geoswift';
		$logs['url'] = self::API_URL;

		$this->logger->debug("log: " . print_r($logs, true));
		try {
			DB::insert(self::LOG_TABLE, $logs);
		} catch ( Exception $e ) {
			$this->logger->err($e);
		}
	}

	function sendQuery($url, $request) {
		$this->logger->debug("sendQuery($url)", [
			$this->orderId
		]);
		$header = array(
			'Content-Type:application/json',
			'Authorization:Basic ' . base64_encode(self::$username . ":" . self::$pwd),
			"x-geoswift-request-id:" . $this->orderId
		);

		$this->logger->debug("header: " . json_encode($header, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		$this->logger->debug("data: " . json_encode(json_decode($request), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

		$ch = curl_init($url);

		// curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
		// curl_setopt($ch, CURLOPT_TIMEOUT, 60); // server max_execution = 120

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 180);
		curl_setopt($ch, CURLOPT_TIMEOUT, 180); // server max_execution = 360
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);

		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (strpos($url, 'https://') !== FALSE) {
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		$content = curl_exec($ch);
		$this->logger->debug(sprintf("Order ID: %s, return: %s", $this->orderId, $content));
		if (($errno = curl_errno($ch))) {
			$this->logger->debug("Curl error: ($errno) " . curl_error($ch));
			$this->logger->debug("Curl info:", curl_getinfo($ch));
		}
		curl_close($ch);

		// $arr = json_decode($content, true);
		// if (! empty($arr["createdTime"]))
		// $arr["createdTime"] = self::formatResultTime($arr["createdTime"]);
		// if (! empty($arr["completedTime"]))
		// $arr["completedTime"] = self::formatResultTime($arr["completedTime"]);

		return $content;
	}

	function sendRemittance($params) {
		$date = date(self::TIMENOW_STR);
		$this->setOrderId($params['id']);
		$amount = ($params['currency'] == 'CNY' ? $params['amount'] : $params['convert_amount']);

		// if (isset($params['gross_amount_cny']))
		// $amount = $params['gross_amount_cny'];
		$data = array(
			"merchantReference" => $this->orderId,
			"sender" => array(
				"bankSwift" => self::SENDER_BANKSWIFT,
				"bankName" => self::WECOLLECT_NAME,
				"bankAddress" => self::WECOLLECT_NAME,
				"bankCity" => self::WECOLLECT_NAME,
				"bankPostalCode" => self::WECOLLECT_NAME,
				"bankCountry" => self::COUNTRY_CODE_HK,
				"name" => self::WECOLLECT_NAME,
				"cardNumber" => self::WECOLLECT_NAME,
				"idNumber" => self::WECOLLECT_NAME,
				"idType" => self::ID_TYPE_DRIVER_LICENSE,
				"dateOfBirthday" => self::SENDER_DOB,
				"nationality" => self::COUNTRY_CODE_HK,
				"messageToRecipient" => self::WECOLLECT_NAME
			),
			"receiver" => array(
				"type" => self::RECEIVER_TYPE,
				"name" => $this->getValidName($this->getPinYin($params["name"])),
				"bankName" => $this->getPinYin($params["bank_name"]),
				"bankCardNumber" => $params["account"],
				"idType" => (! empty($params["id_type"]) ? ($params["id_type"] != "2" ? $params["id_type"] : "3") : "1"),
				"idNumber" => $params["id_number"]
			),
			"transaction" => array(
				"currency" => self::CURRENCY,
				"amount" => $amount,
				"wallet" => self::WALLET,
				"gateway" => self::GATEWAY
			)
		);
		$data["requestChecksum"] = $this->getChecksum($data);
		$request = json_encode($data);

		$url = self::API_URL;
		$response = $this->sendQuery($url, $request);
		$status = $this->isSuccess($response);
		$this->logger->debug("Order ID: " . $this->orderId . ", status: " . ($status ? "success" : ($status === 0 ? "processing" : "failed")));
		$this->log2DB($params, $request, $response, $status, $date);

		return [
			'result' => $status,
			'return' => $response,
			'order_id' => $this->orderId,
			'processing' => ($status === 0)
		];
	}
}