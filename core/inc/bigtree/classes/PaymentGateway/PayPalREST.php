<?php
	/*
		Class: BigTree\PaymentGateway\PayPalREST
			Provides a PayPal REST API implementation of the PaymentGateway Provider.
	*/
	
	namespace BigTree\PaymentGateway;
	
	use BigTree\cURL;
	use BigTree\Setting;
	use stdClass;
	
	class PayPalREST extends Provider
	{
		
		protected $Headers;
		protected $Environment;
		protected $PostURL;
		
		public $Errors;
		public $Profile;
		
		/*
			Constructor:
				Prepares an environment for Authorize.Net payments.
		*/
		
		public function __construct()
		{
			parent::__construct();
			
			// Check on the token expiration, get a new one if needed in the next minute
			if (strtotime($this->Settings["paypal-rest-expiration"]) < time() + 60) {
				$this->getToken();
			}
			
			// Setup default cURL headers
			$this->Headers = [
				"Content-type: application/json",
				"Authorization: Bearer ".$this->Settings["paypal-rest-token"]
			];
			
			if ($this->Settings["paypal-rest-environment"] == "test") {
				$this->PostURL = "https://api.sandbox.paypal.com/v1/";
			} else {
				$this->PostURL = "https://api.paypal.com/v1/";
			}
		}
		
		// Implements Provider::authorize
		public function authorize(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
						   int $cvv, array $address, ?string $description = "", ?string $email = "", ?string $phone = "",
						   ?string $customer = ""): ?string
		{
			return $this->charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description,
								 $email, $phone, $customer, "AUTH_ONLY");
		}
		
		// Implements Provider::authorizeByProfile
		public function authorizeByProfile(string $id, string $user_id, float $amount, ?float $tax = 0.0,
									?string $description = "", ?string $email = ""): ?string
		{
			return $this->chargeByProfile($id, $user_id, $amount, $tax, $description, $email, "authorize");
		}
		
		/*
			Function: call
				Sends an API call to PayPal Payments Pro.
		*/
		
		public function call(string $endpoint, $data = "", ?string $method = null): ?stdClass
		{
			$options = [CURLOPT_HTTPHEADER => $this->Headers];
			
			if ($method) {
				$options[CURLOPT_CUSTOMREQUEST] = $method;
			}
			
			return json_decode(cURL::request($this->PostURL.$endpoint, $data, $options));
		}
		
		// Implements Provider::capture
		public function capture(string $transaction, ?float $amount = null): ?string
		{
			$data = [
				"amount" => [
					"currency" => "USD",
					"total" => $amount
				]
			];
			
			$response = $this->call("payments/authorization/$transaction/capture", json_encode($data));
			
			if ($response->state == "completed") {
				return $response->id;
			} else {
				$this->Message = $response->message;
				
				return null;
			}
		}
		
		// Implements Provider::charge
		public function charge(float $amount, float $tax, string $card_name, string $card_number, int $card_expiration,
						int $cvv, array $address, ?string $description = "", ?string $email = "", ?string $phone = "",
						?string $customer = "", ?string $action = null): ?string
		{
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// Split the card name into first name and last name.
			$first_name = substr($card_name, 0, strpos($card_name, " "));
			$last_name = trim(substr($card_name, strlen($first_name)));
			
			// Separate card expiration out
			$card_expiration_month = substr($card_expiration, 0, 2);
			$card_expiration_year = substr($card_expiration, 2);
			
			if (strlen($card_expiration) == 4) {
				$card_expiration_year = "20".$card_expiration_year;
			}
			
			// Split out tax and subtotals if present
			if ($tax) {
				$transaction_details = [
					"subtotal" => $this->formatCurrency(floatval($amount) - floatval($tax)),
					"tax" => $tax,
					"shipping" => 0
				];
			} else {
				$transaction_details = false;
			}
			
			// Setup credit card payment array
			$cc_array = [
				"number" => $card_number,
				"type" => $this->cardType($card_number),
				"expire_month" => $card_expiration_month,
				"expire_year" => $card_expiration_year,
				"cvv2" => $cvv,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"billing_address" => [
					"line1" => $address["street"],
					"line2" => $address["street2"],
					"city" => $address["city"],
					"state" => $address["state"],
					"postal_code" => $address["zip"],
					"country_code" => $this->countryCode($address["country"]),
					"phone" => $phone
				]
			];
			
			// Remove blank values, REST API doesn't like them
			$cc_array["billing_address"] = array_filter($cc_array["billing_address"]);
			$cc_array = array_filter($cc_array);
			
			// Full transaction array
			$data = [
				"intent" => $action,
				"payer" => [
					"payment_method" => "credit_card",
					"funding_instruments" => [["credit_card" => $cc_array]]
				],
				"transactions" => [[
					"amount" => [
						"total" => $amount,
						"currency" => "USD"
					]
				]]
			];
			
			if ($transaction_details) {
				$data["transactions"][0]["amount"]["details"] = $transaction_details;
			}
			
			if ($email) {
				$data["payer"]["payer_info"]["email"] = $email;
			}
			
			if ($description) {
				$data["transactions"][0]["description"] = $description;
			}
			
			$response = $this->call("payments/payment", json_encode($data));
			
			if ($response->state == "approved") {
				$this->Last4CC = substr(trim($card_number), -4, 4);
				
				if ($action == "authorize") {
					$this->Transaction = $response->transactions[0]->related_resources[0]->authorization->id;
				} else {
					$this->Transaction = $response->transactions[0]->related_resources[0]->sale->id;
				}
				
				return $this->Transaction;
			} else {
				$this->Message = $response->message;
				$this->Errors = $response->details;
				
				return null;
			}
		}
		
		// Implements Provider::chargeByProfile
		public function chargeByProfile(string $id, string $user_id, float $amount, ?float $tax = 0.0,
								 ?string $description = "", ?string $email = "", ?string $action = "sale"): ?string
		{
			$amount = $this->formatCurrency($amount);
			$tax = $this->formatCurrency($tax);
			
			// Split out tax and subtotals if present
			if ($tax) {
				$transaction_details = [
					"subtotal" => $this->formatCurrency(floatval($amount) - floatval($tax)),
					"tax" => $tax,
					"shipping" => 0
				];
			} else {
				$transaction_details = false;
			}
			
			$data = [
				"intent" => $action,
				"payer" => [
					"payment_method" => "credit_card",
					"funding_instruments" => [["credit_card_token" => ["credit_card_id" => $id]]],
				],
				"transactions" => [[
					"amount" => [
						"total" => $amount,
						"currency" => "USD"
					]
				]]
			];
			
			if ($transaction_details) {
				$data["transactions"][0]["amount"]["details"] = $transaction_details;
			}
			
			if ($user_id) {
				$data["payer"]["funding_instruments"][0]["credit_card_token"]["payer_id"] = $user_id;
			}
			
			if ($email) {
				$data["payer"]["payer_info"]["email"] = $email;
			}
			
			if ($description) {
				$data["transactions"][0]["description"] = $description;
			}
			
			$response = $this->call("payments/payment", json_encode($data));
			
			if ($response->state == "approved") {
				if ($action == "authorize") {
					$this->Transaction = $response->transactions[0]->related_resources[0]->authorization->id;
				} else {
					$this->Transaction = $response->transactions[0]->related_resources[0]->sale->id;
				}
				
				return $this->Transaction;
			} else {
				$this->Message = $response->message;
				$this->Errors = $response->details;
				
				return null;
			}
		}
		
		// Implements Provider::createProfile
		public function createProfile(string $name, string $number, int $expiration_date, int $cvv, array $address,
							   string $user_id): ?string
		{
			// Split the card name into first name and last name.
			$first_name = substr($name, 0, strpos($name, " "));
			$last_name = trim(substr($name, strlen($first_name)));
			
			// Make card number only have numeric digits
			$number = preg_replace('/\D/', '', $number);
			
			// Separate card expiration out
			$card_expiration_month = substr($expiration_date, 0, 2);
			$card_expiration_year = substr($expiration_date, 2);
			
			if (strlen($expiration_date) == 4) {
				$card_expiration_year = "20".$card_expiration_year;
			}
			
			$data = [
				"payer_id" => $user_id,
				"number" => $number,
				"type" => $this->cardType($number),
				"expire_month" => $card_expiration_month,
				"expire_year" => $card_expiration_year,
				"cvv2" => $cvv,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"billing_address" => [
					"line1" => $address["street"],
					"line2" => $address["street2"],
					"city" => $address["city"],
					"state" => $address["state"],
					"postal_code" => $address["zip"],
					"country_code" => $this->countryCode($address["country"])
				]
			];
			
			// Remove blank values, REST API doesn't like them
			$data["billing_address"] = array_filter($data["billing_address"]);
			$data = array_filter($data);
			
			$response = $this->call("vault/credit-card", json_encode($data));
			
			if ($response->state == "ok") {
				return $response->id;
			}
			
			$this->Errors = $response->details;
			$this->Message = $response->message;
			
			return null;
		}
		
		// Implements Provider::createRecurringPayment
		public function createRecurringPayment(string $description, float $amount, ?string $start_date, string $period,
										int $frequency, string $card_name, string $card_number, int $card_expiration,
										int $cvv, array $address, string $email, ?float $trial_amount = null,
										?string $trial_period = null, ?int $trial_frequency = null,
										?int $trial_length = null): ?string
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
		
		// Implements Provider::deleteProfile
		public function deleteProfile(string $id): void
		{
			$this->call("vault/credit-card/$id", "", "DELETE");
		}
		
		// Implements Provider::getProfile
		public function getProfile(string $id): ?stdClass
		{
			$response = $this->call("vault/credit-card/$id");
			
			if ($response->state != "ok") {
				$this->Message = $response->message;
				
				return null;
			}
			
			$card = new stdClass;
			$card->Address = new stdClass;
			$card->Address->Street = $response->billing_address->line1;
			isset($response->billing_address->line2) ? $card->Address->Street2 = $response->billing_address->line2 : false;
			$card->Address->City = $response->billing_address->city;
			$card->Address->State = $response->billing_address->state;
			$card->Address->Zip = $response->billing_address->postal_code;
			$card->Address->Country = $response->billing_address->country_code;
			$card->ExpirationDate = $response->expire_month."/".$response->expire_year;
			$card->ID = $response->id;
			$card->Name = $response->first_name." ".$response->last_name;
			$card->Number = $response->number;
			$card->Type = $response->type == "amex" ? "American Express" : ucwords($response->type);
			$card->UserID = $response->payer_id;
			$card->ValidUntil = date("Y-m-d H:i:s", strtotime($response->valid_until));
			
			return $card;
		}
		
		/*
			Function: getToken
				Fetches a new authorization token from PayPal's OAuth servers.
		*/
		
		public function getToken(): bool
		{
			if ($this->Settings["paypal-rest-environment"] == "test") {
				$url = "api.sandbox.paypal.com";
			} else {
				$url = "api.paypal.com";
			}
			
			$response = json_decode(cURL::request("https://$url/v1/oauth2/token", "grant_type=client_credentials", [
				CURLOPT_POST => true,
				CURLOPT_USERPWD => $this->Settings["paypal-rest-client-id"].":".$this->Settings["paypal-rest-client-secret"]
			]));
			
			if ($response->error) {
				$this->Message[] = $response->error;
				
				return false;
			}
			
			$this->Settings["paypal-rest-expiration"] = date("Y-m-d H:i:s", strtotime("+".$response->expires_in." seconds"));
			$this->Settings["paypal-rest-token"] = $response->access_token;
			
			$setting = new Setting("bigtree-internal-payment-gateway");
			$setting->Value = $this->Settings;
			$setting->save();
			
			return true;
		}
		
		// Implements Provider::paypalExpressCheckoutDetails
		public function paypalExpressCheckoutDetails(string $token): ?array
		{
			$token = $token ?: $_SESSION["bigtree"]["paypal-rest-payment-id"];
			$response = $this->call("payments/payment/$token");
			
			if ($response->id) {
				return $response;
			} else {
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutProcess
		public function paypalExpressCheckoutProcess(string $token, string $payer_id, ?float $amount = null): ?array
		{
			$token = $token ?: $_SESSION["bigtree"]["paypal-rest-payment-id"];
			$data = ["payer_id" => $payer_id];
			
			if ($amount) {
				$data["transactions"] = [["amount" => ["total" => $amount, "currency" => "USD"]]];
			}
			
			$response = $this->call("payments/payment/$token/execute", json_encode($data));
			
			if ($response->state == "approved") {
				$this->Transaction = $response->transactions[0]->related_resources[0]->sale->id;
				
				return $this->Transaction;
			} else {
				$this->Errors = $response->details;
				$this->Message = $response->message;
				
				return null;
			}
		}
		
		// Implements Provider::paypalExpressCheckoutRedirect
		public function paypalExpressCheckoutRedirect(float $amount, string $success_url, string $cancel_url): void
		{
			$data = [
				"intent" => "sale",
				"redirect_urls" => [
					"return_url" => $success_url,
					"cancel_url" => $cancel_url
				],
				"payer" => ["payment_method" => "paypal"],
				"transactions" => [[
					"amount" => [
						"total" => $this->formatCurrency($amount),
						"currency" => "USD"
					]
				]]
			];
			
			$response = $this->call("payments/payment", json_encode($data));
			
			if ($response->state == "created") {
				$_SESSION["bigtree"]["paypal-rest-payment-id"] = $response->id;
				
				foreach ($response->links as $link) {
					if ($link->rel == "approval_url") {
						header("Location: ".$link->href);
						die();
					}
				}
			} else {
				$this->Errors = $response->details;
				$this->Message = $response->message;
			}
		}
		
		// Implements Provider::refund
		public function refund(string $transaction, ?string $card_number = null, ?float $amount = null): ?string
		{
			if ($amount) {
				$data = [
					"amount" => [
						"total" => $amount,
						"currency" => "USD"
					]
				];
			} else {
				$data = [];
			}
			
			// First try as if it was a sale.
			$response = $this->call("payments/sale/$transaction/refund", json_encode($data));
			
			if ($response->state == "completed") {
				return $response->id;
			} elseif ($amount) {
				// Try as if it were auth/capture
				$response = $this->call("payments/capture/$transaction/refund", $data);
				
				if ($response->state == "completed") {
					return $response->id;
				}
			}
			
			$this->Message = $response->message;
			
			return null;
		}
		
		// Implements Provider::void
		public function void(string $authorization): ?string
		{
			$response = $this->call("payments/authorization/$authorization/void", "{}");
			
			if ($response->state == "voided") {
				return $response->id;
			} else {
				$this->Message = $response->message;
				
				return null;
			}
		}
		
	}
