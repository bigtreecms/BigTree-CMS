<?php
	/*
		Class: BigTree\PaymentGateway\PayPalPaymentsPro
			Provides a PayPal Payments Pro implementation of the PaymentGateway Provider.
	*/

	namespace BigTree\PaymentGateway;
	
	class PayPalPaymentsPro extends Provider {

		protected $DefaultParameters;
		protected $Environment;
		protected $Password;
		protected $PostURL;
		protected $Signature;
		protected $Username;

		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		function __construct() {
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
			
			$this->DefaultParameters = array(
				"VERSION" => "54.0",
				"USER" => $this->Username,
				"PWD" => $this->Password,
				"SIGNATURE" => $this->Signature
			);
		}

		// Implements Provider::authorize
		function authorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->charge($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"AUTH_ONLY");
		}

		/*
			Function: call
				Sends an API call to PayPal Payments Pro.
		*/
		
		function call($params) {
			$count = 0;
			$this->Unresponsive = false;
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters,$params);
			
			// PayPal wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = array();
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&","%26",$val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = cURL::request($this->PostURL,implode("&",$fields));
				
				if ($response) {
					$response_array = array();
					$response_parts = explode("&",$response);
					
					foreach ($response_parts as $part) {
						list($key,$val) = explode("=",$part);
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
		function capture($transaction,$amount) {
			$params = array(
				"METHOD" => "DoCapture",
				"COMPLETETYPE" => "Complete",
				"AUTHORIZATIONID" => $transaction,
				"AMT" => $this->formatCurrency($amount)
			);
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["TRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
			} else {
				return false;
			}
		}

		// Implements Provider::charge
		function charge($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description = "",$email = "",$phone = "",$customer = "") {
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));

			// Setup request params
			$params = array(
				"METHOD" => "DoDirectPayment",
				"PAYMENTACTION" => $action,
				"AMT" => $this->formatCurrency($amount),
				"CREDITCARDTYPE" => $this->cardType($card_number),
				"ACCT" => $card_number,
				"EXPDATE" => $card_expiration,
				"CVV2" => $cvv,
				"IPADDRESS" => $_SERVER["REMOTE_ADDR"],
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
			);
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["TRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			$this->Last4CC = substr(trim($card_number),-4,4);
			
			// Get a common AVS response.
			$a = $response["AVSCODE"];
			if ($a == "A" || $a == "B") {
				$this->AVS = "Address";
			} elseif ($a == "W" || $a == "Z" || $a == "P") {
				$this->AVS = "Zip";
			} elseif ($a == "D" || $a == "F" || $a == "M" || $a == "Y" || $a == "X") {
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
				return false;
			}
		}

		// Implements Provider::createRecurringPayment
		function createRecurringPayment($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount = false,$trial_period = false,$trial_frequency = false,$trial_length = false) {
			// Default to today for start
			$start_time = $start_date ? strtotime($start_date) : time();

			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
		
			$params = array(
				"METHOD" => "CreateRecurringPaymentsProfile",
				"PROFILESTARTDATE" => gmdate("Y-m-d",$start_time)."T".gmdate("H:i:s",$start_time)."ZL",
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
				"EMAIL" => $email,
				"PHONE" => $phone

			);
			
			if ($trial_amount) {
				$params["TRIALAMT"] = $this->formatCurrency($trial_amount);
				$params["TRIALBILLINGPERIOD"] = $this->PayPalPeriods[$trial_period];
				$params["TRIALBILLINGFREQUENCY"] = $trial_frequency;
				$params["TRIALTOTALBILLINGCYCLES"] = $trial_length;
			}
			
			$response = $this->sendPayPal($params);
			
			// Setup response messages.
			$this->Profile = $response["PROFILEID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["PROFILEID"];
			} else {
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutDetails
		function paypalExpressCheckoutDetails($token) {
			$params = array(
				"METHOD" => "GetExpressCheckoutDetails",
				"TOKEN" => $token
			);
				
			$response = $this->call($params);
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $this->urldecodeArray($response);
			} else {
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutProcess
		function paypalExpressCheckoutProcess($token,$payer_id,$amount = false) {
			// Clean up the amount.
			$amount = $this->formatCurrency($amount);
			
			$params = array(
				"METHOD" => "DoExpressCheckoutPayment",
				"PAYMENTACTION" => "Sale",
				"TOKEN" => $token,
				"PAYERID" => $payer_id,
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount
			);
				
			$response = $this->call($params);

			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			$this->Transaction = $response["TRANSACTIONID"];
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
			} else {
				return false;
			}
		}

		// Implements Provider::paypalExpressCheckoutRedirect
		function paypalExpressCheckoutRedirect($amount,$success_url,$cancel_url) {
			// Clean up the amount.
			$amount = $this->formatCurrency($amount);
			
			$params = array(
				"PAYMENTREQUEST_0_AMT" => $amount,
				"AMT" => $amount,
				"RETURNURL" => $success_url,
				"CANCELURL" => $cancel_url,
				"METHOD" => "SetExpressCheckout",
				"PAYMENTACTION" => "Sale"
			);
			
			$response = $this->sendPayPal($params);

			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				header("Location: https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$amount&CURRENCYCODE=USD&RETURNURL=$success_url&CANCELURL=$cancel_url");
				die();
			} else {
				return false;
			}
		}

		// Implements Provider::refund
		function refund($transaction,$card_number,$amount) {
			$params = array(
				"METHOD" => "RefundTransaction",
				"TRANSACTIONID" => $transaction
			);
			
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
			} else {
				return false;
			}
		}

		// Implements Provider::void
		function void($authorization) {
			$params = array(
				"METHOD" => "DoVoid",
				"AUTHORIZATIONID" => $transaction
			);
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = $response["AUTHORIZATIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["AUTHORIZATIONID"];
			} else {
				return false;
			}
		}

	}
