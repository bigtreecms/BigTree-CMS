<?php
	/*
		Class: BigTree\PaymentGateway\LinkPoint
			Provides a LinkPoint implementation of the PaymentGateway Provider.
	*/
	
	namespace BigTree\PaymentGateway;

	use BigTree\Router;
	
	class LinkPoint extends Provider
	{
		
		protected $Certificate;
		protected $DefaultParameters;
		protected $Environment;
		protected $PostURL;
		protected $Store;
		
		/*
			Constructor:
				Prepares an environment for LinkPoint payments.
		*/
		
		public function __construct()
		{
			parent::__construct();
			
			$this->Store = $this->Settings["linkpoint-store"];
			$this->Environment = $this->Settings["linkpoint-environment"];
			$this->Certificate = SERVER_ROOT."custom/certificates/".$this->Settings["linkpoint-certificate"];
			
			if ($this->Environment == "test") {
				$this->PostURL = "https://staging.linkpt.net:1129";
			} else {
				$this->PostURL = "https://secure.linkpt.net:1129";
				$this->DefaultParameters["orderoptions"] = ["result" => "live"];
			}
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
				Sends an API call to LinkPoint.
		*/
		
		public function call($params)
		{
			$count = 0;
			$this->Unresponsive = false;
			
			$params["merchantinfo"]["configfile"] = $this->Store;
			$xml = "<order>";
			
			foreach ($params as $container => $data) {
				$xml .= "<$container>";
				
				foreach ($data as $key => $val) {
					if (is_array($val)) {
						$xml .= "<$key>";
						
						foreach ($val as $k => $v) {
							$xml .= "<$k>".htmlspecialchars($v)."</$k>";
						}
						
						$xml .= "</$key>";
					} else {
						$xml .= "<$key>".htmlspecialchars($val)."</$key>";
					}
				}
				
				$xml .= "</$container>";
			}
			
			$xml .= "</order>";
			
			// Send it off to the server, try 3 times.
			while ($count < 3) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $this->PostURL);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
				curl_setopt($ch, CURLOPT_SSLCERT, $this->Certificate);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
				
				$response = curl_exec($ch);
				
				if ($response) {
					return simplexml_load_string("<lpresonsecontainer>".$response."</lpresonsecontainer>");
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
				"orderoptions" => [
					"ordertype" => "POSTAUTH"
				],
				"transactiondetails" => [
					"ip" => Router::getRemoteIP(),
					"oid" => $transaction
				]
			];
			
			if ($amount) {
				$params["payment"]["chargetotal"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);
			
			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
			} else {
				return null;
			}
		}
		
		// Implements Provider::charge
		public function charge(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
							   int $cvv, array $address, ?string $description = "", ?string $email = "",
							   ?string $phone = "", ?string $customer = "", ?string $action = null): ?string
		{
			// Clean up the amount and tax.
			$amount = $this->formatCurrency($amount);
			$tax = $this->formatCurrency($tax);
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// Split out expiration
			$card_month = substr($card_expiration, 0, 2);
			$card_year = substr($card_expiration, -2, 2);
			
			$params = [
				"orderoptions" => [
					"ordertype" => $action
				],
				"creditcard" => [
					"cardnumber" => $card_number,
					"cardexpmonth" => $card_month,
					"cardexpyear" => $card_year,
					"cvmvalue" => $cvv,
					"cvmindicator" => "provided"
				],
				"transactiondetails" => [
					"ip" => Router::getRemoteIP()
				],
				"billing" => [
					"name" => $card_name,
					"address1" => $address["street"],
					"address2" => $address["street2"],
					"city" => $address["city"],
					"state" => $address["state"],
					"zip" => $address["zip"],
					"phone" => $phone,
					"email" => $email,
					"userid" => $customer
				],
				"payment" => [
					"tax" => $tax,
					"chargetotal" => $amount
				],
				"notes" => [
					"comments" => $description
				]
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);
			$this->Last4CC = substr(trim($card_number), -4, 4);
			
			// Get a common AVS response.
			$avs_response = substr(strval($response->r_avs), 0, 2);
			
			if ($avs_response == "YN") {
				$this->AVS = "Address";
			} elseif ($avs_response == "NY") {
				$this->AVS = "Zip";
			} elseif ($avs_response == "YY") {
				$this->AVS = "Both";
			} else {
				$this->AVS = false;
			}
			
			// CVV match.
			if (substr(strval($response->r_avs), -1, 1) == "M") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}
			
			if (strval($response->r_message) == "APPROVED") {
				return $this->Transaction;
			} else {
				return null;
			}
		}
		
		// Implements Provider::refund
		public function refund(string $transaction, ?string $card_number = null, ?float $amount = null): ?string
		{
			$params = [
				"orderoptions" => [
					"ordertype" => "CREDIT"
				],
				"creditcard" => [
					"cardnumber" => $card_number
				],
				"transactiondetails" => [
					"ip" => Router::getRemoteIP(),
					"oid" => $transaction
				]
			];
			
			if ($amount) {
				$params["payment"]["chargetotal"] = $this->formatCurrency($amount);
			}
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);
			
			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
			} else {
				return null;
			}
		}
		
		// Implements Provider::void
		public function void(string $authorization): ?string
		{
			$params = [
				"orderoptions" => [
					"ordertype" => "VOID"
				],
				"transactiondetails" => [
					"ip" => Router::getRemoteIP(),
					"oid" => $authorization
				]
			];
			
			$response = $this->call($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);
			
			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
			} else {
				return null;
			}
		}
		
	}
