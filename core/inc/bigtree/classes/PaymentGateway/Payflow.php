<?php
	/*
		Class: BigTree\PaymentGateway\Payflow
			Provides a PayPal Payflow Gateway implementation of the PaymentGateway Provider.
	*/
	
	namespace BigTree\PaymentGateway;
	
	use BigTree\cURL;
	use BigTree\Router;
	
	class Payflow extends Provider
	{
		
		protected $DefaultParameters;
		protected $Environment;
		protected $Partner;
		protected $Password;
		protected $PostURL;
		protected $Signature;
		protected $Username;
		protected $Vendor;
		
		public $PayPalTransaction;
		
		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		public function __construct()
		{
			parent::__construct();
			
			$this->Username = $this->Settings["payflow-username"];
			$this->Password = $this->Settings["payflow-password"];
			$this->Vendor = $this->Settings["payflow-vendor"];
			$this->Environment = $this->Settings["payflow-environment"];
			$this->Partner = $this->Settings["payflow-partner"];
			
			if ($this->Environment == "test") {
				$this->PostURL = "https://pilot-payflowpro.paypal.com";
			} else {
				$this->PostURL = 'https://payflowpro.paypal.com';
			}
			
			$this->DefaultParameters = [
				"USER" => $this->Username,
				"VENDOR" => $this->Vendor,
				"PARTNER" => $this->Partner,
				"PWD" => $this->Password
			];
		}
		
		// Implements Provider::authorize
		public function authorize(float $amount, float $tax, string $card_name, string $card_number,
								  int $card_expiration, int $cvv, array $address, ?string $description = "",
								  ?string $email = "", ?string $phone = "", ?string $customer = ""): ?string
		{
			return $this->charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description,
								 $email, $phone, $customer, "AUTH_ONLY");
		}
		
		/*
			Function: call
				Sends an API call to PayPal Payflow Gateway.
		*/
		
		public function call(array $params): ?array
		{
			$count = 0;
			$this->Unresponsive = false;
			
			// We build a random hash to submit as the transaction ID so that Payflow knows we're trying a repeat transaction, and spoof Mozilla.
			$extras = [
				CURLOPT_HTTPHEADER => ["X-VPS-Request-ID: ".uniqid("", true)],
				CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"
			];
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters, $params);
			
			// Authorize wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = [];
			
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&", "%26", $val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = cURL::request($this->PostURL, implode("&", $fields), $extras);
				
				if ($response) {
					$response = strstr($response, 'RESULT');
					$response_array = [];
					$response_parts = explode("&", $response);
					
					foreach ($response_parts as $part) {
						list($key, $val) = explode("=", $part);
						$response_array[$key] = $val;
					}
					
					return $response_array;
				}
				
				$count++;
			}
			
			$this->Unresponsive = true;
			
			return null;
		}
		
		// Implements Provider::capture
		public function capture(string $transaction, ?float $amount = null): ?string
		{
			$params = [
				"TRXTYPE" => "D",
				"ORIGID" => $transaction
			];
			
			if ($amount) {
				$params["AMT"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::charge
		public function charge(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
							   int $cvv, array $address, ?string $description = "", ?string $email = "",
							   ?string $phone = "", ?string $customer = "", ?string $action = null): ?string
		{
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name, 0, strpos($card_name, " "));
			$last_name = trim(substr($card_name, strlen($first_name)));
			
			$params = [
				"TRXTYPE" => $action,
				"TENDER" => "C",
				"AMT" => $amount,
				"CREDITCARDTYPE" => $this->cardType($card_number),
				"ACCT" => $card_number,
				"EXPDATE" => substr($card_expiration, 0, 4),
				"CVV2" => $cvv,
				"IPADDRESS" => Router::getRemoteIP(),
				"FIRSTNAME" => $first_name,
				"LASTNAME" => $last_name,
				"STREET" => trim($address["street"]." ".$address["street2"]),
				"CITY" => $address["city"],
				"STATE" => $address["state"],
				"ZIP" => $address["zip"],
				"BILLTOCOUNTRY" => $address["country"],
				"EMAIL" => $email,
				"PHONE" => $phone,
				"COMMENT1" => $description
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			$this->Last4CC = substr(trim($card_number), -4, 4);
			
			// Get a common AVS response.
			if ($response["AVSADDR"] == "Y" && $response["AVSZIP"] == "Y") {
				$this->AVS = "Both";
			} elseif ($response["AVSADDR"] == "Y") {
				$this->AVS = "Address";
			} elseif ($response["AVSZIP"] == "Y") {
				$this->AVS = "Zip";
			} else {
				$this->AVS = false;
			}
			
			// Get a common CVV response, either it passed or it didn't.
			if ($response["CVV2MATCH"] == "Y") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutDetails
		public function paypalExpressCheckoutDetails(string $token): ?array
		{
			$params = [
				"TOKEN" => $token,
				"TRXTYPE" => "S",
				"ACTION" => "G",
				"TENDER" => "P"
			];
			
			$response = $this->call($params);
			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				return $this->urldecodeArray($response);
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutProcess
		public function paypalExpressCheckoutProcess(string $token, string $payer_id, ?float $amount = null): ?array
		{
			$amount = $this->formatCurrency($amount);
			
			$params = [
				"TOKEN" => $token,
				"PAYERID" => $payer_id,
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount,
				"TRXTYPE" => "S",
				"ACTION" => "D",
				"TENDER" => "P"
			];
			
			$response = $this->call($params);
			
			$this->Transaction = $response["PNREF"];
			$this->PayPalTransaction = $response["PPREF"];
			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutRedirect
		public function paypalExpressCheckoutRedirect(float $amount, string $success_url, string $cancel_url): void
		{
			// Clean up the amount.
			$amount = $this->formatCurrency($amount);
			
			$params = [
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount,
				"RETURNURL" => $success_url,
				"CANCELURL" => $cancel_url,
				"TRXTYPE" => "S",
				"ACTION" => "S",
				"TENDER" => "P"
			];
			
			$response = $this->call($params);
			
			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				header("Location: https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$amount&CURRENCYCODE=USD&RETURNURL=$success_url&CANCELURL=$cancel_url");
				die();
			}
		}
		
		// Implements Provider::refund
		public function refund(string $transaction, ?string $card_number = null, ?float $amount = null): ?string
		{
			$params = [
				"TRXTYPE" => "C",
				"ORIGID" => $transaction
			];
			
			if ($amount) {
				$params["AMT"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::void
		public function void(string $authorization): ?string
		{
			$params = [
				"TRXTYPE" => "V",
				"ORIGID" => $authorization
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return null;
			}
		}
		
	}
