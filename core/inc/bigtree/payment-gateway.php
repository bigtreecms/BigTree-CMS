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
				$this->PostURL = "https://pilot-payflowpro.verisign.com/transaction";
			} else {
				$this->PostURL = 'https://payflowpro.verisign.com/transaction';
			}
			
			$this->DefaultParameters = array(
				"USER" => $this->Username,
				"VENDOR" => $this->Vendor,
				"PARTNER" => $this->Partner,
				"PWD" => $this->Password
			);
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
				$fields[] = $key."=".urlencode($val);
			}
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,implode("&",$fields));
				echo $response;
				if ($response) {
					$r = explode("|",$response);
					$this->Message = $this->response[3];
					$this->AuthorizationCode = $this->response[4];
					$this->AVS = $this->response[5];
					$this->Transaction = $this->response[6];
					
					return array(
						"status" => $possibilities[$r[0]],
						"message" => $r[3],
						"authorization" => $r[4],
						"avs" => $r[5],
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

			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,array_merge($this->DefaultParameters,$params));
				
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
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,array_merge($this->DefaultParameters,$params),$extras);
				
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
		
		protected function chargeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			$params = array();
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["x_type"] = "AUTH_CAPTURE";
			
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
			$this->AVS = $response["avs"];

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
		
		/*
			Function: authorize
				Authorizes a credit card and returns the token for later capture.
			
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
				Capture token if successful, otherwise returns false.
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
			Function: authorizeAuthorize
				Authorize.net interface for <authorize>
		*/
		
		function authorizeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			$params = array();
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["x_type"] = "AUTH_ONLY";
			
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
			$this->AVS = $response["avs"];

			if ($response["status"] == "approved") {
				return $response["transaction"];
			} else {
				return false;
			}
		}
	}
?>