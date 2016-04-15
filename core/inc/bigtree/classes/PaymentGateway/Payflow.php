<?php
	/*
		Class: BigTree\PaymentGateway\Payflow
			Provides a PayPal Payflow Gateway implementation of the PaymentGateway Provider.
	*/

	namespace BigTree\PaymentGateway;

	use BigTree\cURL;
	
	class Payflow extends Provider {

		protected $DefaultParameters;
		protected $Environment;
		protected $Partner;
		protected $Password;
		protected $PostURL;
		protected $Signature;
		protected $Username;
		protected $Vendor;

		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		function __construct() {
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
			
			$this->DefaultParameters = array(
				"USER" => $this->Username,
				"VENDOR" => $this->Vendor,
				"PARTNER" => $this->Partner,
				"PWD" => $this->Password
			);
		}

		// Implements Provider::authorize
		function authorize($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description, $email, $phone, $customer) {
			return $this->charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description, $email, $phone, $customer, "AUTH_ONLY");
		}

		/*
			Function: call
				Sends an API call to PayPal Payflow Gateway.
		*/
		
		function call($params) {
			$count = 0;
			$this->Unresponsive = false;
			
			// We build a random hash to submit as the transaction ID so that Payflow knows we're trying a repeat transaction, and spoof Mozilla.
			$extras = array(
				CURLOPT_HTTPHEADER => array("X-VPS-Request-ID: ".uniqid("", true)),
				CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"
			);
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters, $params);
			
			// Authorize wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = array();
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&", "%26", $val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = cURL::request($this->PostURL, implode("&", $fields), $extras);
				
				if ($response) {
					$response = strstr($response, 'RESULT');
					$response_array = array();
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
			
			return false;
		}

		// Implements Provider::capture
		function capture($transaction, $amount) {
			$params = array(
				"TRXTYPE" => "D",
				"ORIGID" => $transaction
			);
			
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
				return false;
			}
		}

		// Implements Provider::charge
		function charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description = "", $email = "", $phone = "", $customer = "", $action = "S") {
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			// Split the card name into first name and last name.
			$first_name = substr($card_name, 0, strpos($card_name, " "));
			$last_name = trim(substr($card_name, strlen($first_name)));

			$params = array(
				"TRXTYPE" => $action,
				"TENDER" => "C",
				"AMT" => $amount,
				"CREDITCARDTYPE" => $this->cardType($card_number),
				"ACCT" => $card_number,
				"EXPDATE" => substr($card_expiration, 0, 4),
				"CVV2" => $cvv,
				"IPADDRESS" => $_SERVER["REMOTE_ADDR"],
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
			);

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
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutDetails
		function paypalExpressCheckoutDetails($token) {
			$params = array(
				"TOKEN" => $token,
				"TRXTYPE" => "S",
				"ACTION" => "G",
				"TENDER" => "P"
			);

			$response = $this->call($params);
			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				return $this->urldecodeArray($response);
			} else {
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutProcess
		function paypalExpressCheckoutProcess($token, $payer_id, $amount = false) {
			$amount = $this->formatCurrency($amount);
			
			$params = array(
				"TOKEN" => $token,
				"PAYERID" => $payer_id,
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount,
				"TRXTYPE" => "S",
				"ACTION" => "D",
				"TENDER" => "P"
			);

			$response = $this->call($params);
			
			$this->Transaction = $response["PNREF"];
			$this->PayPalTransaction = $response["PPREF"];
			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutRedirect
		function paypalExpressCheckoutRedirect($amount, $success_url, $cancel_url) {
			// Clean up the amount.
			$amount = $this->formatCurrency($amount);
			
			$params = array(
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount,
				"RETURNURL" => $success_url,
				"CANCELURL" => $cancel_url,
				"TRXTYPE" => "S",
				"ACTION" => "S",
				"TENDER" => "P"
			);

			$response = $this->call($params);

			$this->Message = $response["RESPMSG"];
			
			if ($response["RESULT"] == "0") {
				header("Location: https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$amount&CURRENCYCODE=USD&RETURNURL=$success_url&CANCELURL=$cancel_url");
				die();
			} else {
				return false;
			}
		}

		// Implements Provider::refund
		function refund($transaction, $card_number, $amount) {
			$params = array(
				"TRXTYPE" => "C",
				"ORIGID" => $transaction
			);

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
				return false;
			}
		}

		// Implements Provider::void
		function void($authorization) {
			$params = array(
				"TRXTYPE" => "V",
				"ORIGID" => $authorization
			);

			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return false;
			}
		}

	}
