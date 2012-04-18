<?
	/*
		Class: BigTreePaymentGateway
			Controls eCommerce payment systems.
			Wrapper overtop PayPal Payments Pro, Authorize.Net, PayPal Payflow Gateway
	*/
	
	class BigTreePaymentGateway {
		
		var $Service = "";
		
		/*
			Constructor:
				Sets up the currently configured service.
		*/
		
		function __construct() {
			global $cms;
			$pgs = $cms->getSetting("bigtree-internal-payment-gateway");
			// If for some reason the setting doesn't exist, make one.
			$this->Service = $pgs["service"];
			if ($this->Service == "authorize.net") {
				$this->setupAuthorize($pgs["settings"]);
			} elseif ($this->Service == "paypal") {
				$this->setupPayPal($pgs["settings"]);
			} elseif ($this->Service == "payflow") {
				$this->setupPayflow($pgs["settings"]);
			}
		}
		
		/*
			Function: authorize
				Authorizes a credit card and returns the transaction ID for later capture.
			
			Parameters:
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				card_name - Name as it appears on the credit card.
				card_number - Credit card number.
				card_expiration - 4 or 6 digit expiration date (MMYYYY or MMYY).
				cvv - Credit card security code.
				address - An address array with keys "street", "street2", "city", "state", "zip", "country"
				description - Description of what is being charged.
				email - Email address of the purchaser.
				phone - Phone number of the purchaser.
				customer - Customer ID of the purchaser.
		
			Returns:
				Transaction ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function authorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description = "",$email = "",$phone = "",$customer = "") {
			// Clean up the amount and tax.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$tax = round(floatval(str_replace(array('$',','),"",$tax)),2);

			if ($this->Service == "authorize.net") {
				return $this->authorizeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal") {
				return $this->authorizePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "payflow") {
				return $this->authorizePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			}
		}
		
		/*
			Function: authorizeAuthorize
				Authorize.net interface for <authorize>
		*/
		
		protected function authorizeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"AUTH_ONLY");
		}
		
		/*
			Function: authorizePayPal
				PayPal Payments Pro interface for <authorize>
		*/
		
		protected function authorizePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"Authorization");
		}
		
		/*
			Function: authorizePayflow
				PayPal Payflow Pro interface for <authorize>
		*/
		
		protected function authorizePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"A");
		}
		
		/*
			Function: cardType
				Returns the type of credit card based on the number.
			
			Parameters:
				card_number - The credit card number.
			
			Returns:
				The name of the card issuer.
		*/
		
		function cardType($card_number) {
			$cards = array(
				"visa" => "(4\d{12}(?:\d{3})?)",
				"amex" => "(3[47]\d{13})",
				"jcb" => "(35[2-8][89]\d\d\d{10})",
				"maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
				"solo" => "((?:6334|6767)\d{12}(?:\d\d)?\d?)",
				"mastercard" => "(5[1-5]\d{14})",
				"switch" => "(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)",
			);
			$names = array("Visa", "American Express", "JCB", "Maestro", "Solo", "Mastercard", "Switch");
			$matches = array();
			$pattern = "#^(?:".implode("|", $cards).")$#";
			$result = preg_match($pattern, str_replace(" ", "", $card_number), $matches);
			if ($result > 0) {
				return $names[count($matches) - 2];
			}
			return false;
		}
		
		/*
			Function: capture
				Captures a previously authorized transaction.
			
			Parameters:
				transaction - The transaction ID to capture funds for.
				amount - The amount to charge (must be equal to or lower than authorization amount).
		
			Returns:
				Transaction ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function capture($transaction,$amount = 0) {
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);

			if ($this->Service == "authorize.net") {
				return $this->captureAuthorize($transaction,$amount);
			} elseif ($this->Service == "paypal") {
				return $this->capturePayPal($transaction,$amount);
			} elseif ($this->Service == "payflow") {
				return $this->capturePayflow($transaction,$amount);
			}
		}
		
		/*
			Function: captureAuthorize
				Authorize.Net interface for <capture>
		*/
		
		protected function captureAuthorize($transaction,$amount) {
			$params = array();
			
			$params["x_type"] = "PRIOR_AUTH_CAPTURE";
			$params["x_trans_id"] = $transaction;
			if ($amount) {
				$params["x_amount"] = $amount;
			}
			
			$response = $this->sendAuthorize($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
		
		/*
			Function: capturePayPal
				PayPal Payments Pro interface for <capture>
		*/
		
		protected function capturePayPal($transaction,$amount) {
			$params = array();
			
			$params["METHOD"] = "DoCapture";
			$params["COMPLETETYPE"] = "Complete";
			$params["AUTHORIZATIONID"] = $transaction;
			$params["AMT"] = $amount;
			
			$response = $this->sendPayPal($params);
			
			// Setup response messages.
			$this->Transaction = $response["TRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["TRANSACTIONID"];
			} else {
				return false;
			}
		}
		
		/*
			Function: capturePayflow
				PayPal Payflow Pro interface for <capture>
		*/
		
		protected function capturePayflow($transaction,$amount) {
			$params = array();
			
			$params["TRXTYPE"] = "D";
			$params["ORIGID"] = $transaction;
			if ($amount) {
				$params["AMT"] = $amount;
			}
			
			$response = $this->sendPayflow($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return false;
			}
		}
		
		/*
			Function: charge
				Charges a credit card (Authorization & Capture).
			
			Parameters:
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				card_name - Name as it appears on the credit card.
				card_number - Credit card number.
				card_expiration - 4 or 6 digit expiration date (MMYYYY or MMYY).
				cvv - Credit card security code.
				address - An address array with keys "street", "street2", "city", "state", "zip", "country"
				description - Description of what is being charged.
				email - Email address of the purchaser.
				phone - Phone number of the purchaser.
				customer - Customer ID of the purchaser.
		
			Returns:
				Transaction ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function charge($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description = "",$email = "",$phone = "",$customer = "") {
			// Clean up the amount and tax.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$tax = round(floatval(str_replace(array('$',','),"",$tax)),2);

			if ($this->Service == "authorize.net") {
				return $this->chargeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal") {
				return $this->chargePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "payflow") {
				return $this->chargePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			}
		}
		
		/*
			Function: chargeAuthorize
				Authorize.net interface for <charge>
		*/
		
		protected function chargeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,$action = "AUTH_CAPTURE") {
			$params = array();
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["x_type"] = $action;
			
			$params["x_first_name"] = $first_name;
			$params["x_last_name"] = $last_name;
			$params["x_address"] = trim($address["street"]." ".$address["street2"]);
			$params["x_city"] = $address["city"];
			$params["x_state"] = $address["state"];
			$params["x_zip"] = $address["zip"];
			$params["x_country"] = $address["country"];
			
			$params["x_phone"] = $phone;
			$params["x_email"] = $email;
			$params["x_cust_id"] = $customer;
			$params["x_customer_ip"] = $_SERVER["REMOTE_ADDR"];

			$params["x_card_num"] = $card_number;
			$params["x_exp_date"] = $card_expiration;
			$params["x_card_code"] = $cvv;

			$params["x_amount"] = $amount;
			$params["x_tax"] = $tax;
			
			$params["x_description"] = $description;
			
			$response = $this->sendAuthorize($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];
			$this->Last4CC = $response["cc_last_4"];
			// Get a common AVS response.
			if ($response["avs"] == "A") {
				$this->AVS = "Address";
			} elseif ($response["avs"] == "W" || $response["avs"] == "Z") {
				$this->AVS = "Zip";
			} elseif ($response["avs"] == "X" || $response["avs"] == "Y") {
				$this->AVS = "Both";
			} else {
				$this->AVS = false;
			}
			// Get a common CVV response, either it passed or it didn't.
			if ($response["cvv"] == "2" || $response["cvv"] == "8" || $response["cvv"] == "A" || $response["cvv"] == "B") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
		
		/*
			Function: chargePayPal
				PayPal Payments Pro interface for <charge>
		*/
		
		protected function chargePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,$action = "Sale") {
			$params = array();
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["METHOD"] = "DoDirectPayment";
			$params["PAYMENTACTION"] = $action;
			
			$params["AMT"] = $amount;
			$params["CREDITCARDTYPE"] = $this->cardType($card_number);
			$params["ACCT"] = $card_number;
			$params["EXPDATE"] = $card_expiration;
			$params["CVV2"] = $cvv;
			
			$params["IPADDRESS"] = $_SERVER["REMOTE_ADDR"];
			
			$params["FIRSTNAME"] = $first_name;
			$params["LASTNAME"] = $last_name;
			$params["STREET"] = trim($address["street"]." ".$address["street2"]);
			$params["CITY"] = $address["city"];
			$params["STATE"] = $address["state"];
			$params["ZIP"] = $address["zip"];
			$params["COUNTRYCODE"] = $address["country"];
			
			$params["EMAIL"] = $email;
			$params["PHONE"] = $phone;
			$params["NOTE"] = $description;
			
			$response = $this->sendPayPal($params);
			
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
		
		/*
			Function: chargePayflow
				PayPal Payflow Pro interface for <charge>
		*/
		
		protected function chargePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,$action = "S") {
			$params = array();
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["TRXTYPE"] = $action;
			$params["TENDER"] = "C"; // Credit card
			
			$params["AMT"] = $amount;
			$params["CREDITCARDTYPE"] = $this->cardType($card_number);
			$params["ACCT"] = $card_number;
			$params["EXPDATE"] = substr($card_expiration,0,4);
			$params["CVV2"] = $cvv;
			
			$params["IPADDRESS"] = $_SERVER["REMOTE_ADDR"];
			
			$params["FIRSTNAME"] = $first_name;
			$params["LASTNAME"] = $last_name;
			$params["STREET"] = trim($address["street"]." ".$address["street2"]);
			$params["CITY"] = $address["city"];
			$params["STATE"] = $address["state"];
			$params["ZIP"] = $address["zip"];
			$params["BILLTOCOUNTRY"] = $address["country"];
			
			$params["EMAIL"] = $email;
			$params["PHONE"] = $phone;
			$params["COMMENT1"] = $description;
			
			$response = $this->sendPayflow($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			$this->Last4CC = substr(trim($card_number),-4,4);
			
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
		
		/*
			Function: doExpressCheckoutPayment
				Processes an Express Checkout transaction.
				For: PayPal Payments Pro and Payflow Pro ONLY.
				
			Parameters:
				token - The Express Checkout token returned by PayPal.
				payer_id - The Payer ID returned by PayPal.
				amount - The amount to charge.
			
			Returns:
				An array of buyer information.
		*/
		
		function doExpressCheckoutPayment($token,$payer_id,$amount) {
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			
			$params = array();
			$params["TOKEN"] = $token;
			$params["PAYERID"] = $payer_id;
			$params["AMT"] = $amount;
			
			// Payflow
			if ($this->Service == "payflow") {
				$params["TRXTYPE"] = "S";
				$params["ACTION"] = "D";
				$params["TENDER"] = "P";
				
				$response = $this->sendPayflow($params);
				$this->Transaction = $response["PNREF"];
				$this->PayPalTransaction = $response["PPREF"];
				$this->Message = $response["RESPMSG"];
				
				if ($response["RESULT"] == "0") {
					return $response["PNREF"];
				} else {
					return false;
				}
			// PayPal Payments Pro
			} elseif ($this->Service == "paypal") {
				$params["METHOD"] = "DoExpressCheckoutPayment";
				$params["PAYMENTACTION"] = "Sale";
				
				$response = $this->sendPayPal($params);
				$this->Message = urldecode($response["L_LONGMESSAGE0"]);
				$this->Transaction = $response["TRANSACTIONID"];
				
				if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
					return $response["TRANSACTIONID"];
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: getExpressCheckoutDetails
				Returns checkout details for an Express Checkout transaciton.
				For: PayPal Payments Pro and Payflow Pro ONLY.
				
			Parameters:
				token - The Express Checkout token returned by PayPal.
			
			Returns:
				An array of buyer information.
		*/
		
		function getExpressCheckoutDetails($token) {
			$params = array();
			$params["TOKEN"] = $token;
			
			// Payflow
			if ($this->Service == "payflow") {
				$params["TRXTYPE"] = "S";
				$params["ACTION"] = "G";
				$params["TENDER"] = "P";
				
				$response = $this->sendPayflow($params);
				$this->Message = $response["RESPMSG"];				
				
				if ($response["RESULT"] == "0") {
					return $this->urldecode($response);
				} else {
					return false;
				}
			// PayPal Payments Pro
			} elseif ($this->Service == "paypal") {
				$params["METHOD"] = "GetExpressCheckoutDetails";
				
				$response = $this->sendPayPal($params);
				$this->Message = urldecode($response["L_LONGMESSAGE0"]);
				
				if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
					return $this->urldecode($response);
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: refund
				Refunds a settled transaction.
			
			Parameters:
				transaction - The transaction ID to capture funds for.
				card_number - The last four digits of the credit card number used for the transaction (required for some processors).
				amount - The amount to refund (required for some processors).
		
			Returns:
				Transaction ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function refund($transaction,$card_number = "",$amount = 0) {
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);

			if ($this->Service == "authorize.net") {
				return $this->refundAuthorize($transaction,$card_number,$amount);
			} elseif ($this->Service == "paypal") {
				return $this->refundPayPal($transaction,$card_number,$amount);
			} elseif ($this->Service == "payflow") {
				return $this->refundPayflow($transaction,$card_number,$amount);
			}
		}
		
		/*
			Function: refundAuthorize
				Authorize.Net interface for <refund>
		*/
		
		protected function refundAuthorize($transaction,$card_number,$amount) {
			$params = array();
			
			$params["x_type"] = "CREDIT";
			$params["x_trans_id"] = $transaction;
			$params["x_card_num"] = $card_number;
			if ($amount) {
				$params["x_amount"] = $amount;
			}
			
			$response = $this->sendAuthorize($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
		
		/*
			Function: refundPayPal
				PayPal Payments Pro interface for <refund>
		*/
		
		protected function refundPayPal($transaction,$card_number,$amount) {
			$params = array();
			
			$params["METHOD"] = "RefundTransaction";
			$params["TRANSACTIONID"] = $transaction;

			if ($amount) {
				$params["REFUNDTYPE"] = "Partial";
				$params["AMT"] = $amount;
			} else {
				$params["REFUNDTYPE"] = "Full";
			}
			
			$response = $this->sendPayPal($params);
			
			// Setup response messages.
			$this->Transaction = $response["REFUNDTRANSACTIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["REFUNDTRANSACTIONID"];
			} else {
				return false;
			}
		}
		
		/*
			Function: refundPayflow
				PayPal Payflow Pro interface for <refund>
		*/
		
		protected function refundPayflow($transaction,$card_number,$amount) {
			$params = array();
			
			$params["TRXTYPE"] = "C";
			$params["ORIGID"] = $transaction;

			if ($amount) {
				$params["AMT"] = $amount;
			}
			
			$response = $this->sendPayflow($params);
			
			// Setup response messages.
			$this->Transaction = $response["PNREF"];
			$this->Message = urldecode($response["RESPMSG"]);
			
			if ($response["RESULT"] == "0") {
				return $response["PNREF"];
			} else {
				return false;
			}
		}
		
		/*
			Function: sendAuthorize
				Sends a command to Authorize.Net.
		*/
		
		protected function sendAuthorize($params) {
			$count = 0;
			$possibilities = array("","approved","declined","error");
			$this->Unresponsive = false;

			// Get the default parameters
			$params = array_merge($this->DefaultParameters,$params);
			
			// Authorize wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = array();
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&","%26",$val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,implode("&",$fields));
				if ($response) {
					$r = explode("|",$response);
					return array(
						"status" => $possibilities[$r[0]],
						"message" => $r[3],
						"authorization" => $r[4],
						"avs" => $r[5],
						"cvv" => $r[39],
						"transaction" => $r[6],
						"cc_last_4" => substr($r[50],-4,4)
					);
				}
				
				$count++;
			}
			
			$this->Unresponsive = true;
			return false;
		}
		
		/*
			Function: sendPayPal
				Sends a command to PayPal Payments Pro.
		*/
		
		protected function sendPayPal($params) {
			$count = 0;
			$this->Unresponsive = false;
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters,$params);
			
			// Authorize wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = array();
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&","%26",$val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,implode("&",$fields));
				
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
		
		/*
			Function: sendPayflow
				Sends a command to PayPal Payflow Pro.
		*/
		
		protected function sendPayflow($params) {
			$count = 0;
			$this->Unresponsive = false;
			
			// We build a random hash to submit as the transaction ID so that Payflow knows we're trying a repeat transaction, and spoof Mozilla.
			$extras = array(
				CURLOPT_HTTPHEADER => array("X-VPS-Request-ID: ".uniqid("",true)),
				CURLOPT_USERAGENT => "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"
			);
			
			// Get the default parameters
			$params = array_merge($this->DefaultParameters,$params);
			
			// Authorize wants a GET instead of a POST, so we have to convert it away from an array.
			$fields = array();
			foreach ($params as $key => $val) {
				$fields[] = $key."=".str_replace("&","%26",$val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,implode("&",$fields),$extras);
				
				if ($response) {
					$response = strstr($response, 'RESULT');
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
		
		/*
			Function: setupAuthorize
				Prepares an environment for Authorize.Net payments.
		*/
		
		protected function setupAuthorize($settings) {
			$this->APILogin = $settings["authorize-api-login"];
			$this->TransactionKey = $settings["authorize-transaction-key"];
			$this->Environment = $settings["authorize-environment"];
			
			if ($this->Environment == "test") {
				$this->PostURL = "https://test.authorize.net/gateway/transact.dll";
			} else {
				$this->PostURL = "https://secure.authorize.net/gateway/transact.dll";
			}
				
			$this->DefaultParameters = array(
				"x_delim_data" => "TRUE",
				"x_delim_char" => "|",
				"x_relay_response" => "FALSE",
				"x_url" => "FALSE",
				"x_version" => "3.1",
				"x_method" => "CC",
				"x_login" => $this->APILogin,
				"x_tran_key" => $this->TransactionKey
			);
		}
		
		/*
			Function: setupExpressCheckout
				Sets up a PayPal Express Checkout session.
				For: PayPal Payments Pro and Payflow Pro ONLY.
			
			Parameters:
				amount - The amount to charge the user.
				success_url - The URL to return to on successful PayPal login.
				cancel_rul - The URL to return to if the user cancels payment.
			
			Returns:
				Authorization token for redirect.
		*/
		
		function setupExpressCheckout($amount,$success_url,$cancel_url) {
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$params = array();
			
			$params["AMT"] = $amount;
			$params["RETURNURL"] = $success_url;
			$params["CANCELURL"] = $cancel_url;
			
			// Payflow
			if ($this->Service == "payflow") {
				$params["TRXTYPE"] = "S";
				$params["ACTION"] = "S";
				$params["TENDER"] = "P";
				
				$response = $this->sendPayflow($params);
				$this->Message = $response["RESPMSG"];				
				
				if ($response["RESULT"] == "0") {
					return $response["TOKEN"];
				} else {
					return false;
				}
			// PayPal Payments Pro
			} elseif ($this->Service == "paypal") {
				$params["METHOD"] = "SetExpressCheckout";
				$params["PAYMENTACTION"] = "Sale";
				
				$response = $this->sendPayPal($params);
				$this->Message = urldecode($response["L_LONGMESSAGE0"]);
				
				if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
					return urldecode($response["TOKEN"]);
				} else {
					return false;
				}
			} else {
				return false;
			}
		}
		
		/*
			Function: setupPayPal
				Prepares an environment for PayPal Payments Pro payments.
		*/
		
		protected function setupPayPal($settings) {
			$this->Username = $settings["paypal-username"];
			$this->Password = $settings["paypal-password"];
			$this->Signature = $settings["paypal-signature"];
			$this->Environment = $settings["paypal-environment"];
			
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
		
		/*
			Function: setupPayflow
				Prepares an environment for PayPal Payflow Pro payments.
		*/
		
		protected function setupPayflow($settings) {
			$this->Username = $settings["payflow-username"];
			$this->Password = $settings["payflow-password"];
			$this->Vendor = $settings["payflow-vendor"];
			$this->Environment = $settings["payflow-environment"];
			$this->Partner = $settings["payflow-partner"];
			
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
		
		/*
			Function: urldecode
				urldecodes a whole array.
		*/
		
		protected function urldecode($array) {
			foreach ($array as &$item) {
				$item = urldecode($item);
			}
			return $array;
		}
		
		/*
			Function: void
				Voids an authorization.
			
			Parameters:
				authorization - The transaction/authorization ID to void.
		
			Returns:
				Transaction ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function void($authorization) {
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);

			if ($this->Service == "authorize.net") {
				return $this->voidAuthorize($authorization);
			} elseif ($this->Service == "paypal") {
				return $this->voidPayPal($authorization);
			} elseif ($this->Service == "payflow") {
				return $this->voidPayflow($authorization);
			}
		}
		
		/*
			Function: voidAuthorize
				Authorize.Net interface for <void>
		*/
		
		protected function voidAuthorize($authorization) {
			$params = array();
			
			$params["x_type"] = "VOID";
			$params["x_trans_id"] = $authorization;
			
			$response = $this->sendAuthorize($params);
			
			// Setup response messages.
			$this->Transaction = $response["transaction"];
			$this->Message = $response["message"];

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
		
		/*
			Function: voidPayPal
				PayPal Payments Pro interface for <void>
		*/
		
		protected function voidPayPal($authorization) {
			$params = array();
			
			$params["METHOD"] = "DoVoid";
			$params["AUTHORIZATIONID"] = $authorization;

			$response = $this->sendPayPal($params);
			
			// Setup response messages.
			$this->Transaction = $response["AUTHORIZATIONID"];
			$this->Message = urldecode($response["L_LONGMESSAGE0"]);
			
			if ($response["ACK"] == "Success" || $response["ACK"] == "SuccessWithWarning") {
				return $response["AUTHORIZATIONID"];
			} else {
				return false;
			}
		}
		
		/*
			Function: voidPayflow
				PayPal Payflow Pro interface for <void>
		*/
		
		protected function voidPayflow($authorization) {
			$params = array();
			
			$params["TRXTYPE"] = "V";
			$params["ORIGID"] = $authorization;

			$response = $this->sendPayflow($params);
			
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
?>