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
				$this->setupPayFlow($pgs["settings"]);
			}
		}
		
		/*
			Function: setupAuthorize
				Prepares an environment for Authorize.Net payments.
			
			Parameters:
				settings - An array of settings.
		*/
		
		function setupAuthorize($settings) {
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
			
			Parameters:
				settings - An array of settings.
		*/
		
		function setupPayPal($settings) {
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
			Function: setupPayFlow
				Prepares an environment for PayPal Payflow Pro payments.
			
			Parameters:
				settings - An array of settings.
		*/
		
		function setupPayFlow($settings) {
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
		}
		
		/*
			Function: sendAuthorize
				Sends a command to Authorize.Net
				Sets $this->Message to the response message.
				Sets $this->Transaction to the Transaction ID.
				If the server was not responding, sets $this->Unresponsive to true.
			
			Parameters:
				params - Parameters to send.
			
			Returns:
				Returns true if transaction was successful, false otherwise.
		*/
		
		function sendAuthorize($params) {
			$count = 0;
			$possibilities = array("","Approved","Declined","Error");
			$this->Unresponsive = false;

			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,array_merge($this->DefaultParameters,$params));
				
				if ($response) {
					$r = explode("|",$response);
					$result = $possibilities[$r[0]];
					$this->Message = $this->response[3];
					$this->AuthorizationCode = $this->response[4];
					$this->AVS = $this->response[5];
					$this->Transaction = $this->response[6];
					
					if ($result == "Approved") {
						return true;
					} else if ($result == "Declined") {
						return false;
					}
				}
				
				$count++;
			}
			
			$this->Unresponsive = true;
			return false;
		}
		
		/*
			Function: sendPayPal
				Sends a command to PayPal Payments Pro
				Sets $this->Message to the response message.
				Sets $this->Transaction to the Transaction ID.
				If the server was not responding, sets $this->Unresponsive to true.
			
			Parameters:
				params - Parameters to send.
			
			Returns:
				Returns true if transaction was successful, false otherwise.
		*/
		
		function sendPayPal($params) {
			$count = 0;
			$this->Unresponsive = false;

			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$response = BigTree::cURL($this->PostURL,array_merge($this->DefaultParameters,$params));
				
				if ($response) {
				
				}
				
				$count++;
			}
			
			$this->Unresponsive = true;
			return false;
		}
		
	}
?>