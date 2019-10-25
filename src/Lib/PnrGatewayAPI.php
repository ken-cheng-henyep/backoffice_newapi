<?php 

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;

use \GuzzleHttp\Client;

use \Chumper\Zipper\Zipper;

class PnrGatewayAPI
{
	const SANDBOX_API_URL = 'https://mertest.chinapnr.com/pay/';
	const PRODUCTION_API_URL = 'https://global.chinapnr.com/pay/';

	protected $options =[
		'merchantKey'=>'',
		'processorKey'=>'',
		'merchantAccountId'=>'',
		'terminalIds'=>[],
		'isSandbox'=>false,
		'maxLogFilesize'=>5120,
		'logPath'=>null,
	];


	protected $httpClient;

	protected $logPath = null;
	public $logger;


	public function __construct($options = null)
	{
		$this->setOptions($options);

		$this->initLogger();
		$this->initHttpClient();
	}

	/**
	 * Gets the option.
	 *
	 * @param      string  $key           The key
	 * @param      mixed   $defaultValue  The default value
	 *
	 * @return     mixed   The option value. <code>null</code> returned if value is not exist
	 */
	public function getOption($key, $defaultValue = null)
	{
		if(isset($this->options[ $key ]))
			return $this->options [ $key ];
		return $defaultValue;
	}

	/**
	 * Determines if empty option.
	 *
	 * @param      string   $key    The key
	 *
	 * @return     boolean  Returning <code>true</code> if empty option, otherwise <code>false</code>
	 */
	public function isEmptyOption($key)
	{
		$val = $this->getOption($key);
		return empty($val);
	}

	/**
	 * Shortcut method for getting / setting key. 
	 * If passing 2nd argument, assume that is setting mode.
	 * Otherwise, it perform as getting mode.
	 *
	 * @param      string  $key    The key
	 * @param      mixed   $value  The value to be set in the option. 
	 *
	 * @return     mixed   The value if getting mode, or this object if setting mode.
	 */
	public function option($key)
	{
		$args = func_get_args();
		if(count($args) > 1){
			return $this->setOption($key, $args[1]);
		}
		return $this->getOption($key);
	}

	/**
	 * Sets the option.
	 *
	 * @param      string  $key    The key
	 * @param      mixed   $value  The value
	 */
	public function setOption($key, $value = null)
	{
		if($key == 'merchantKeyFile'){
			return $this->setOption('merchantKey', $this->loadTextFile($value) );
		}
		if($key == 'processorKeyFile'){
			return $this->setOption('processorKey', $this->loadTextFile($value) );
		}

		$this->options[ $key ] = $value;
		return $this;
	}

	/**
	 * Sets the options.
	 *
	 * @param      array  $options  The options
	 *
	 */
	public function setOptions($options)
	{
		if(is_array($options)){
			foreach($options as $key => $val){
				$this->setOption($key, $val);
			}

		}

		return $this;
	}

	/**
	 * Shortcut method for getting / setting a batch of options. 
	 * If passing 1nd argument, assume that is setting mode.
	 * Otherwise, it perform as getting mode.
	 *
	 * @param      mixed  $options    The new options
	 *
	 * @return     mixed   The value if getting mode, or this object if setting mode.
	 */
	public function options($key)
	{
		$args = func_get_args();
		if(count($args) > 0){
			return $this->setOptions($args[0]);
		}
		return $this->getOptions();
	}

	/**
	 * Gets the options.
	 *
	 * @return     array  The options.
	 */
	public function getOptions()
	{
		return $this->options;
	}

	protected function initHttpClient()
	{
		$options = ['base_uri'=>$this->getEndpointUrl()];
		$this->logger->debug(__METHOD__.' HttpClient.options:'.print_r($options, true));

		$this->httpClient = new Client($options);
	}

	protected function initLogger()
	{
		// Setup default log file path.
		$logPath = $this->getOption('logPath', ROOT.'/logs/pnr_api.log');

		$realLogPath = realpath($logPath);

		$logfilePathinfo = pathinfo($realLogPath);

		// If log file size is too large, mvoe to {$filename}-{$date}.{$ext}
		
		if(file_exists($logPath)){
			if(filesize($logPath) > $this->getOption('maxLogFilesize',5120) * 1024 ){
				@rename($logPath,$logfilePathinfo['dirname'].$logfilePathinfo['filename'] .'-'.date('YmdHis').'.'.$logfilePathinfo['extension']);
			}
		}
        $this->logger = new Logger('pnr_api');
        $this->logger->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
	}

	protected function loadTextFile($path)
	{
		if(!file_exists($path) ){
			throw new Exception('File is not exist. Path:'.$path);
		}
		if(!is_readable($path) ){
			throw new Exception('File is not readable. Path:'.$path);
		}
		return  file_get_contents($path);
	}

	/**
	 * Generate the request message signature.
	 *
	 * @param      array                                        $data    The data
	 * @param      array                                        $fields  The fields
	 *
	 * @throws     PnrGatewayConfigurationException             (description)
	 * @throws     PnrGatewayFieldNameDataTypeInvalidException  (description)
	 * @throws     PnrGatewayFieldRequiredException             (description)
	 *
	 * @return     string                                       The signature.
	 */
	public function getRequestSignature($data = [], $fields = [])
	{
		if($this->isEmptyOption('merchantKey')){
			throw new PnrGatewayConfigurationException('Options.merchantKey is required.');
		}
		if(empty($data) || !is_array($data)){
			throw new PnrGatewayFieldRequiredException('List of data are required.');
		}
		if(empty($fields) || !is_array($fields)){
			throw new PnrGatewayFieldRequiredException('List of fields are required.');
		}
		
		$msgItems = [];


		foreach($fields as $a => $b)
		{
			$fieldName = $a;
			$fieldRequired = false;
			if(!is_string($a)){
				$fieldName = $b;
			}else{
				$fieldRequired = $b == true;
			}
			if(!is_string($fieldName)){
				throw new PnrGatewayFieldNameDataTypeInvalidException('Field name is invalid for '.$fieldName);
			}
			if( empty($data[$fieldName])){
				if($fieldRequired){
					throw new PnrGatewayFieldRequiredException('Required data '.$fieldName .' is empty.');
				}
			}else{
				$msgItems[] = $fieldName.'='.$data[ $fieldName];
			}
		}

		$payload = implode('&', $msgItems);

		$this->logger->debug(__METHOD__.'@'.__LINE__." Signature Payload:".$payload);

		$signMsg = '';
		/////////////  RSA Signature Generation /////////
		$pkeyid = openssl_get_privatekey($this->getOption('merchantKey'));

		// compute signature
		openssl_sign($payload, $signMsg, $pkeyid, OPENSSL_ALGO_SHA1);

		// free the key from memory
		openssl_free_key($pkeyid);

		$signMsg = base64_encode($signMsg);
		
		/////////////  RSA Signature Generation /////////

		return $signMsg;
	}

	/**
	 * Determines if response signature valid.
	 *
	 * @param      array    $data    The data
	 * @param      array    $fields  The fields
	 *
	 * @return     boolean  True if response signature valid, False otherwise.
	 */
	public function isResponseSignatureValid($data = [], $fields = [])
	{
		if($this->isEmptyOption('processorKey')){
			throw new PnrGatewayConfigurationException('Options.processorKey is required.');
		}
		if(empty($data) || !is_array($data)){
			throw new PnrGatewayFieldRequiredException('List of data are required.');
		}
		if(empty($fields) || !is_array($fields)){
			throw new PnrGatewayFieldRequiredException('List of fields are required.');
		}
		
		$msgItems = [];


		foreach($fields as $a => $b)
		{
			$fieldName = $a;
			$fieldRequired = false;
			if(!is_string($a)){
				$fieldName = $b;
			}else{
				$fieldRequired = $b == true;
			}
			if(!is_string($fieldName)){
				throw new PnrGatewayFieldNameDataTypeInvalidException('Field name is invalid for '.$fieldName);
			}
			if( empty($data[$fieldName])){
				if($fieldRequired){
					throw new PnrGatewayFieldRequiredException('Required data '.$fieldName .' is empty.');
				}
			}else{
				$msgItems[] = $fieldName.'='.$data[ $fieldName];
			}
		}

		$payload = implode('&', $msgItems);

		$this->logger->debug(__METHOD__.'@'.__LINE__." Signature Payload:".$payload);


		$signMsgDe=	urldecode($data['signMsg']);
		$MAC=base64_decode($signMsgDe);

		$cert = $this->getOption('processorKey');
		
		/////////////  RSA Signature Generation /////////
		$pubkeyid = openssl_get_publickey($cert); 
		$ok = openssl_verify($payload, $MAC, $pubkeyid); 

		$this->logger->debug(__METHOD__.'@'.__LINE__." Result:".$ok);


		return $ok == '1';
	}

	/**
	 * Find a list of transaction by date
	 *
	 * @param      string                       $terminalId       The terminal identifier
	 * @param      string                       $transactionDate  The transaction date
	 *
	 * @throws     Exception                    (description)
	 * @throws     PnrGatewayResponseException  (description)
	 *
	 * @return     <type>                       ( description_of_the_return_value )
	 */
	public function findDailyTransactions( $terminalId, $transactionDate)
	{
		if($this->isEmptyOption('merchantAccountId')){
			throw new PnrGatewayConfigurationException('Options.merchantAccountId is required.');
		}
		if($this->isEmptyOption('merchantKey')){
			throw new PnrGatewayConfigurationException('Options.merchantKey is required.');
		}

		if(empty($terminalId)){
			throw new PnrGatewayFieldRequiredException('Terminal Id is required.');
		}
		if(empty($transactionDate)){
			throw new PnrGatewayFieldRequiredException('Transaction Date is required.');
		}
		// If the passed date is integer (Timestamp)
		if(is_int($transactionDate)){
			$transactionDate = date('Ymd', $transactionDate);
		}
		// If the passed date is YYYY-mm-dd
		if(is_string($transactionDate) && preg_match('#^\d{4}\-\d{2}-\d{2}#',$transactionDate)){
			$transactionDate = date('Ymd', strtotime(substr($transactionDate, 0, 10)));
		}
		// If the passed date is DateTimeObject
		if(!is_object($transactionDate) && is_subclass_of($transactionDate, 'DateTime')){
			$transactionDate = $transactionDate->format('Ymd');
		}

		// Part 1 - Request a token
		$data = [
			'inputCharset'=>'1', // 1 = UTF-8
			'signType'=>'4', // Hard-code
			'merchantAcctId'=> $this->getOption('merchantAccountId'),
			'terminalId'=>$terminalId,
			'trxDate'=>$transactionDate,
		];

		// Sign the payload
		$data['signMsg'] = $this->getRequestSignature($data,  ['inputCharset','signType','merchantAcctId','terminalId','trxDate']);

		$this->logger->debug(__METHOD__.'@'.__LINE__." RequestMessage:".print_r($data, true));



		// Response format is HTTP Query
		$response  = $this->httpClient->request('POST', 'dailyTxnConfirm.htm',  [
			'form_params' => $data,

			// SSL Verification need to be turn off since their api server certificate is self-signed only.
			'curl' => [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
		    ]
		]);

		// Getting response body string.
		$responseString = $response->getBody();

		$responseData = [];
		parse_str($responseString, $responseData);

		$this->logger->debug(__METHOD__.'@'.__LINE__." Response:".print_r($responseData, true));

		if(!empty( $responseData['errCode'] )){
			$this->logger->err(__METHOD__.'@'.__LINE__.', ResponseError: '.$responseData['errCode']);
			throw new PnrGatewayResponseException($responseData['errCode'], isset($responseData['errMsg']) ? $responseData['errMsg'] :  'Error: '.$responseData['errCode']);
		}

		// Token is required for downloading data file.
		if( empty( $responseData['token'] )){
			throw new PnrGatewayResponseException(-1, 'Token not exist in the response.');
		}

		// Part 2 - Download Data File
		$data = [
			'merchantAcctId'=>$this->getOption('merchantAccountId'),
			'terminalId'=>$terminalId,
			'token'=>$responseData['token'],
		];

		// Sign the payload
		$data['signMsg'] = $this->getRequestSignature($data,  ['inputCharset','signType','merchantAcctId','terminalId','trxDate']);

		$this->logger->debug(__METHOD__.'@'.__LINE__." RequestMessage:".print_r($data, true));

		// Generate tmp file path.
		//$tmpFilePath  = tempnam(sys_get_temp_dir(), uniqid(strftime('%G-%m-%d')));
		$tmpFilePath = ROOT.DIRECTORY_SEPARATOR.'tmp/pnr_dq_'.uniqid(strftime('%G-%m-%d')).'.zip';
		$this->logger->debug(__METHOD__.'@'.__LINE__." Temporary path:".$tmpFilePath);

		// Response format is HTTP Query
		$response  = $this->httpClient->request('POST', 'dailyFileDownload.htm',  [
			'form_params' => $data,

			'sink'=> $tmpFilePath ,

			// SSL Verification need to be turn off since their api server certificate is self-signed only.
			'curl' => [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
		    ]
		]);
		$this->logger->debug(__METHOD__.'@'.__LINE__." Response:".print_r($response, true));

		if(!file_exists($tmpFilePath)){
			throw new PnrGatewayResponseException('Data file does not exist.');
		}

		// Download content is a zip file
		$zip = new Zipper();
		$zip->make($tmpFilePath);

		// Scan for text file
		$fileList = $zip->listFiles('/\.txt$/i'); 
		if(empty($fileList[0])){
			throw new PnrGatewayResponseException('Data file not found.');
		}

		// Get the file content of first text file
		$responseString = $zip->getFileContent($fileList[0]);

		$zip->close();

		$this->logger->debug(__METHOD__.'@'.__LINE__." DataFile:".PHP_EOL.$responseString);

		$result = $this->parseDailyTransactionData($responseString);

		return $result;
	}

	/**
	 * Parse transaction data by using an archive file
	 *
	 * @param      string                       $archiveFilePath  The archive file path
	 *
	 * @throws     PnrGatewayResponseException  (description)
	 *
	 * @return     <type>                       ( description_of_the_return_value )
	 */
	public function handleDailyTransactionArchive($archiveFilePath)
	{
		$zip = new Zipper();
		$zip->make($archiveFilePath);

		// Scan for text file
		$fileList = $zip->listFiles('/\.txt$/i'); 
		if(empty($fileList[0])){
			throw new PnrGatewayResponseException('Data file not found.');
		}

		// Get the file content of first text file
		$responseString = $zip->getFileContent($fileList[0]);

		$zip->close();


		$this->logger->debug(__METHOD__.'@'.__LINE__." DataFile:".PHP_EOL.$responseString);



		$result = $this->parseDailyTransactionData($responseString);

		return $result;
	}
	
	/**
	 * Parsing daily transaction records
	 *
	 * @param      string                                  $text   The text
	 *
	 * @throws     PnrGatewayTransactionDataFileException  (description)
	 *
	 * @return     array                                   ( description_of_the_return_value )
	 */
    public function parseDailyTransactionData($text)
    {
    	$lines = explode("\n", $text);

    	print_r($lines);

    	$merchant_info = [];
    	$transactions = [];

    	$line = $lines[0];

    	$d = explode('|', $line);
    	if(count($d) < 9){
    		throw new PnrGatewayTransactionDataFileException('Format of query info is invalid.');
    	}

    	// First line is query information.
    	// 商户号|终端|结算币别|对账日期|总消费笔数|总消费金额|总消费手续费金额|总退款笔数|总退款金额|总退款手续费|预留字段
    	$info = [
    		'merchant_account_no'=>$d[0],
    		'terminal_id'=>$d[1],
    		'currency' => $d[2], // CNY
    		'date'=> $d[3], // YYYY-mm-dd
    		'total_amount'=> floatval($d[4]), // xx,xxx.xx
    		'total_fee'=> floatval($d[5]), // xx,xxx.xx
    		'num_refund_request'=> intval($d[6]),
    		'total_refund_amount'=> floatval($d[7]),
    		'total_refund_fee'=> floatval($d[8]),
    		'extra'=>$d[9],
    	];

    	// Second + lines are transaction details.
    	// 交易类型|交易号|商户订单号|商户订单时间|交易确认时间|订单币别|订单金额|结算币别|结算金额|手续费
    	
    	$data = [];
    	for($i = 1; $i < count($lines); $i++){
    		$line = $lines[ $i ];

    		// Skip if line is empty
    		if(empty($line)) continue;

    		$d = explode("|", $line);
    		if(count($d) < 9){
	    		throw new PnrGatewayTransactionDataFileException('Format of line '.($i+1).' is invalid.');
	    	}

	    	// Only handle these state
	    	if($d[0] == 'SALES' || $d[0] == 'REFUND'){
		    	$tx = [
		    		'transactionType'=>$d[0],
		    		'merchantAcctId'=>$info['merchant_account_no'],
		    		'terminalId'=>$info['terminal_id'],
		    		'dealId'=> $d[1],
		    		'orderId'=>$d[2],
		    		'orderTime'=> $d[3],
		    		'dealTime'=> $d[4],
		    		'orderCurrency'=>$d[5],
		    		'orderAmount'=> $d[6]*100,
		    		'dealCurrency'=>$d[7],
		    		'dealAmount'=> $d[8]*100,
		    		'fee'=> $d[9],
		    	];

		    	if(isset($d[10]))
		    		$tx['payTypeName'] = $d[10];

		    	if(isset($d[11]))
		    		$tx['bankName'] = $d[11];

		    	$data[] = $this->rowMappingFromGateway($tx);
		    }
    	}	

    	$result = compact('info','data');

    	$this->logger->debug(__METHOD__.'@'.__LINE__.', result='.print_r($result ,true));

    	return $result;
    }

    /**
     * Lookup a transaction from list of possible terminals
     *
     * @param      string  $orderId  The order identifier (PayConnector.TRANSACTION_ID without minus character)
     *
     * @return     array  ( description_of_the_return_value )
     */
    public function queryAllTerminals( $orderId ='' )
    {
    	if($this->isEmptyOption('terminalIds')){
			print "PNR: Configuration Problem!".PHP_EOL;
			print "Please check the options before querying.".PHP_EOL;
			return null;
    	}

		foreach($this->getOption('terminalIds') as $terminalId ){
			try{
				$this->logger->debug(__METHOD__.': '.print_r(compact('orderId','terminalId'), true));
				$data = $this->getTransaction( $terminalId, $orderId);
				$this->logger->debug(__METHOD__.': '.print_r($data, true));
				if(!empty($data['transaction'])){
					return $data['transaction'];
				}
			}catch(PnrGatewayResponseException $exp){
				$this->logger->err(__METHOD__.': Exception - '.$exp->getMessage());
			}catch(PnrGatewayConfigurationException $exp){
				// print "PNR: Configuration Problem!".PHP_EOL;
				// print $exp->getMessage().PHP_EOL;
				// print "Please check the options before querying.".PHP_EOL;
				$this->logger->err(__METHOD__.': Exception - '.$exp->getMessage());
				return null;
			}catch(Exception $exp){
				$this->logger->err(__METHOD__.': Exception - '.$exp->getMessage());
			}
    	}
    	return null;
    }

	/**
	 * Get a single transaction by orderId
	 *
	 * @param      string                       $terminalId      The terminal identifier
	 * @param      string                       $orderId         The order identifier (PayConnector.TRANSACTION_ID without minus character)
	 *
	 * @throws     PnrGatewayResponseException  (description)
	 *
	 * @return     array                        Data row of the transaction
	 */
	public function getTransaction( $terminalId, $orderId )
	{
    	if($this->isEmptyOption('merchantAccountId')){
			throw new PnrGatewayConfigurationException('Options.merchantAccountId is required.');
		}
    	if($this->isEmptyOption('merchantKey')){
			throw new PnrGatewayConfigurationException('Options.merchantKey does not configured.');
		}
		if(empty($terminalId)){
			throw new PnrGatewayFieldRequiredException('Terminal Id is required.');
		}
		if(empty($orderId)){
			throw new PnrGatewayFieldRequiredException('Order Id is required.');
		}

		// Remove minus character from the $orderId
		$orderId = str_replace('-','', $orderId);

		$data = [
			'inputCharset'=>'1', // 1 = UTF-8
			'signType'=>'4', // Hard-code
			'merchantAcctId'=>$this->getOption('merchantAccountId'),
			'terminalId'=>$terminalId,
			'orderId'=>$orderId,
		];

		// Sign the payload
		$data['signMsg'] = $this->getRequestSignature($data, ['inputCharset','signType','merchantAcctId','terminalId','orderId','dealId']);

		$this->logger->debug(__METHOD__." RequestMessage:".print_r($data, true));



		// Response format is HTTP Query
		$response  = $this->httpClient->request('POST', 'singleTrxQuery.htm',  [
			'form_params' => $data,

			// SSL Verification need to be false since their api server is self-signed only.
			'curl' => [
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
		    ]
		]);

		$this->logger->debug(__METHOD__." Response:".print_r($response, true));

		// Getting response body string.
		$responseString = $response->getBody();

		// if($response->getStatusCode()!= 200){
		// 	throw new Exception('Unknown result');
		// }


		$responseData = [];
		parse_str($responseString, $responseData);

		//$this->logger->debug(__METHOD__." Response:".print_r($responseData, true));

		if($responseData['queryRespCode'] =='F'){
			throw new PnrGatewayResponseException(-1, isset($responseData['queryRespMsg']) ? $responseData['queryRespMsg'] :  'Failure of the request.');
		}

		// if($responseData['errCode'] != '000000' && $responseData['errCode'] != ''){
		// 	throw new PnrGatewayResponseException($responseData['errCode'], isset($responseData['errMsg']) ? $responseData['errMsg'] :  'Unknown');
		// }

		$output = [];
		$output['version'] = ($responseData['version']);
		$output['error_code'] = isset($responseData['errCode']) ? ($responseData['errCode']) : '';
		$output['transaction'] = $this->rowMappingFromGateway($responseData);

		// Verify response message
		$isValid = $this->isResponseSignatureValid($responseData, ['merchantAcctId','terminalId','version','payType','bankId','orderId','orderTime','orderCurrency','orderAmount','dealId','dealTime','payResult','bankDealId','errcode','ext1','ext2','queryRespCode','queryRespMsg'] );


		$this->logger->debug(__METHOD__." ConvertedResponse:".print_r(compact('output','isValid'), true));

		return $output;
	}

	/**
	 * Normalize data for database transaction log
	 *
	 * @param      array   $responseData  The response data
	 *
	 * @return     array   Normlized data.
	 */
	public function rowMappingFromGateway($responseData){
		$output = [];
		if(isset($responseData['merchantAcctId']))
			$output['merchant_acct_id'] = ($responseData['merchantAcctId']);
		if(isset($responseData['transactionType']))
			$output['transaction_type'] = ($responseData['transactionType']);
		if(isset($responseData['terminalId']))
			$output['terminal_id'] = ($responseData['terminalId']);
		if(isset($responseData['orderId']))
			$output['order_id'] = $this->toGuidString($responseData['orderId']);
		if(isset($responseData['dealId']))
			$output['deal_id'] = ($responseData['dealId']);
		if(isset($responseData['dealTime']))
			$output['deal_time'] = $this->dateStringToObject($responseData['dealTime']);
		if(isset($responseData['orderTime']))
			$output['order_time'] = $this->dateStringToObject($responseData['orderTime']);
		if(isset($responseData['payType']))
			$output['pay_type'] = $responseData['payType'];
		if(isset($responseData['payTypeName']))
			$output['pay_type_name'] = $responseData['payTypeName'];
		if(isset($responseData['payResult']))
			$output['pay_result'] = $responseData['payResult'];
		if(isset($responseData['currency']))
			$output['order_currency'] = $responseData['currency'];
		if(isset($responseData['orderCurrency']))
			$output['order_currency'] = $responseData['orderCurrency'];
		if(isset($responseData['orderAmount']))
			$output['order_amount'] = intval($responseData['orderAmount']) / 100;
		if(isset($responseData['bankId']))
			$output['bank_id'] = strtoupper($responseData['bankId']);
		if(isset($responseData['bankName']))
			$output['bank_name'] = $responseData['bankName'];
		if(isset($responseData['bankDealId']))
			$output['bank_deal_id'] = $responseData['bankDealId'];
		if(isset($responseData['ext1']))
			$output['ext1'] = $responseData['ext1'];
		if(isset($responseData['ext2']))
			$output['ext2'] = $responseData['ext2'];
		if(isset($responseData['dealCurrency']))
			$output['deal_currency'] = $responseData['dealCurrency'];
		if(isset($responseData['dealAmount']))
			$output['deal_amount'] = intval($responseData['dealAmount'])/100;
		if(isset($responseData['fee']))
			$output['fee'] = $responseData['fee'];

		$this->logger->debug(__METHOD__.' Mapping data. From: '.print_r($responseData, true).', to '.print_r($output, true));

		return $output;
	}

	/**
	 * Getting endpoint url. 
	 *
	 * @param      string  $endpoint  The endpoint path
	 *
	 * @return     string  The endpoint url.
	 */
	public function getEndpointUrl($endpoint = '')
	{
		if($this->getOption('isSandbox', false)){
			return self::SANDBOX_API_URL .$endpoint;
		}else{
			return self::PRODUCTION_API_URL.$endpoint;
		}
	}

	/**
	 * Convert string into Guid format.
	 *
	 * @param      string  $str    The string to be converted. Only 34-length alphabet, numeric will be converted. Return the given text if format invalid.
	 *
	 * @return     string  Re-formatted string (If valid)
	 */
	public function toGuidString($str)
	{
		if(strlen($str) == '32' && preg_match('#^[a-zA-Z0-9]+$#', $str))
			return substr($str,0,8).'-'.substr($str,8,4).'-'.substr($str,12,4).'-'.substr($str,16,4).'-'.substr($str,20,12);
		return $str;
	}

	/**
	 * Convert date string into php date object. Returned DateTime object will be using local timezone.
	 *
	 * @param      string     $datestring  The date string
	 *
	 * @throws     Exception  Invalid date format of given string.
	 *
	 * @return     \DateTime  Returning DataTime object from given string content.
	 */
	public function dateStringToObject($datestring)
	{
		if(strlen($datestring) != 8 && strlen($datestring) != 14)
			throw new Exception('Invalid date format of given string.');

		$dto = new \DateTime();

		$dto->setDate( intval(substr($datestring, 0,4)) ,intval(substr($datestring, 4,2)) ,intval(substr($datestring, 6,2)) );
		$dto->setTime(0,0,0);

		if(strlen($datestring) > 10 ){
			$dto->setTime( intval(substr($datestring, 8,2)) ,intval(substr($datestring, 10,2)) ,intval(substr($datestring,12,2)) );
		}
		return $dto;
	}
}

class PnrGatewayFieldNameDataTypeInvalidException extends Exception
{}

class PnrGatewayResponseException extends Exception
{
	public $errorCode; 
	public function __construct($errorCode, $message = '')
	{
		parent::__construct($message);
		$this->errorCode = $errorCode;
	}

	public function getErrorCode()
	{
		return $this->errorCode;
	}
}

class PnrGatewayConfigurationException extends Exception
{}

class PnrGatewayFieldRequiredException extends Exception
{}

class PnrGatewayTransactionDataFileException extends Exception
{}