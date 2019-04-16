<?php
	/*
		Class: BigTree\PaymentGateway\PayPalPaymentsPro
			Provides a PayPal Payments Pro implementation of the PaymentGateway Provider.
	*/
	
	namespace BigTree\PaymentGateway;
	
	use BigTree\cURL;
	use BigTree\Router;
	
	class PayPalPaymentsPro extends Provider
	{
		
		protected $DefaultParameters;
		protected $Environment;
		protected $Password;
		protected $PostURL;
		protected $Signature;
		protected $Username;
		
		public $Profile;
		
		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		public function __construct()
		{
			parent::__construct();
			
			$this->Username = $this->Settings["paypal-username"];
			$this->Password = $this->Settings["paypal-password"];
			$this->Signature = $this->Settings["paypal-signature"];
			$this->Environment = $this->Settings["paypal-environment"];
			
			if ($this->Environment == "test") {
				$this->PostURL = "https://api-3t.sandbox.paypal.com/nvp";
			} else {
				$this->PostURL = "https://api-3t.paypal.com/nvp";
			}
			
			$this->DefaultParameters = [
				"VERSION" => "54.0",
				"USER" => $this->Username,
				"PWD" => $this->Password,
				"SIGNATURE" => $this->Signature
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
				Sends an API call to PayPal Payments Pro.
		*/
		
		public function call(array $params): ?array
		{
			$count = 0;
			$this->Unresponsive = false;
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters, $params);
			
			// PayPal wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = [];
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&", "%26", $val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = cURL::request($this->PostURL, implode("&", $fields));
				
				if ($response) {
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
				"METHOD" => "DoCapture",
				"COMPLETETYPE" => "Complete",
				"AUTHORIZATIONID" => $transaction,
				"AMT" => $this->formatCurrency($amount)
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["TRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
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
			
			// Setup request params
			$params = [
				"METHOD" => "DoDirectPayment",
				"PAYMENTACTION" => $action,
				"AMT" => $this->formatCurrency($amount),
				"CREDITCARDTYPE" => $this->cardType($card_number),
				"ACCT" => $card_number,
				"EXPDATE" => $card_expiration,
				"CVV2" => $cvv,
				"IPADDRESS" => Router::getRemoteIP(),
				"FIRSTNAME" => $first_name,
				"LASTNAME" => $last_name,
				"STREET" => trim($address["street"]." ".$address["street2"]),
				"CITY" => $address["city"],
				"STATE" => $address["state"],
				"ZIP" => $address["zip"],
				"COUNTRYCODE" => $this->countryCode($address["country"]),
				"EMAIL" => $email,
				"PHONE" => $phone,
				"NOTE" => $description
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["TRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			$this->Last4CC = substr(trim($card_number), -4, 4);
			
			// Get a common AVS response.
			$avs_response = $response["AVSCODE"];
			
			if ($avs_response == "A" || $avs_response == "B") {
				$this->AVS = "Address";
			} elseif ($avs_response == "W" || $avs_response == "Z" || $avs_response == "P") {
				$this->AVS = "Zip";
			} elseif ($avs_response == "D" || $avs_response == "F" || $avs_response == "M" || $avs_response == "Y" || $avs_response == "X") {
				$this->AVS = "Both";
			} else {
				$this->AVS = false;
			}
			
			// Get a common CVV response, either it passed or it didn't.
			if ($response["CVV2MATCH"] == "M") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::createRecurringPayment
		public function createRecurringPayment(string $description, float $amount, ?string $start_date, string $period,
											   int $frequency, string $card_name, string $card_number,
											   int $card_expiration, int $cvv, array $address, string $email,
											   ?float $trial_amount = null, ?string $trial_period = null,
											   ?int $trial_frequency = null, ?int $trial_length = null): ?string
		{
			// Default to today for start
			$start_time = $start_date ? strtotime($start_date) : time();
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name, 0, strpos($card_name, " "));
			$last_name = trim(substr($card_name, strlen($first_name)));
			
			$params = [
				"METHOD" => "CreateRecurringPaymentsProfile",
				"PROFILESTARTDATE" => gmdate("Y-m-d", $start_time)."T".gmdate("H:i:s", $start_time)."ZL",
				"BILLINGPERIOD" => $this->PayPalPeriods[$period],
				"BILLINGFREQUENCY" => $frequency,
				"DESC" => $description,
				"AMT" => $this->formatCurrency($amount),
				"CREDITCARDTYPE" => $this->cardType($card_number),
				"ACCT" => $card_number,
				"EXPDATE" => $card_expiration,
				"CVV2" => $cvv,
				"FIRSTNAME" => $first_name,
				"LASTNAME" => $last_name,
				"STREET" => trim($address["street"]." ".$address["street2"]),
				"CITY" => $address["city"],
				"STATE" => $address["state"],
				"COUNTRYCODE" => $this->countryCode($address["country"]),
				"ZIP" => $address["zip"],
				"EMAIL" => $email
			
			];
			
			if ($trial_amount) {
				$params["TRIALAMT"] = $this->formatCurrency($trial_amount);
				$params["TRIALBILLINGPERIOD"] = $this->PayPalPeriods[$trial_period];
				$params["TRIALBILLINGFREQUENCY"] = $trial_frequency;
				$params["TRIALTOTALBILLINGCYCLES"] = $trial_length;
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Profile = $response["PROFILEID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["PROFILEID"];
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutDetails
		public function paypalExpressCheckoutDetails(string $token): ?array
		{
			$params = [
				"METHOD" => "GetExpressCheckoutDetails",
				"TOKEN" => $token
			];
			
			$response = $this->call($params);
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $this->urldecodeArray($response);
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutProcess
		public function paypalExpressCheckoutProcess(string $token, string $payer_id, ?float $amount = null): ?array
		{
			// Clean up the amount.
			$amount = $this->formatCurrency($amount);
			
			$params = [
				"METHOD" => "DoExpressCheckoutPayment",
				"PAYMENTACTION" => "Sale",
				"TOKEN" => $token,
				"PAYERID" => $payer_id,
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount
			];
			
			$response = $this->call($params);
			
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			$this->Transaction = $response["TRANSACTIONID"];
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
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
				"METHOD" => "SetExpressCheckout",
				"PAYMENTACTION" => "Sale"
			];
			
			$response = $this->call($params);
			
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				header("Location: https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$amount&CURRENCYCODE=USD&RETURNURL=$success_url&CANCELURL=$cancel_url");
				die();
			}
		}
		
		// Implements Provider::refund
		public function refund(string $transaction, ?string $card_number = null, ?float $amount = null): ?string
		{
			$params = [
				"METHOD" => "RefundTransaction",
				"TRANSACTIONID" => $transaction
			];
			
			if ($amount) {
				$params["REFUNDTYPE"] = "Partial";
				$params["AMT"] = $this->formatCurrency($amount);
			} else {
				$params["REFUNDTYPE"] = "Full";
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["REFUNDTRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["REFUNDTRANSACTIONID"];
			}
			
			return null;
		}
		
		// Implements Provider::void
		public function void(string $authorization): ?string
		{
			$params = [
				"METHOD" => "DoVoid",
				"AUTHORIZATIONID" => $authorization
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["AUTHORIZATIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["AUTHORIZATIONID"];
			} else {
				return null;
			}
		}
		
	}
