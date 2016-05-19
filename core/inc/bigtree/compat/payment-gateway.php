<?php
	/*
		Class: BigTreePaymentGateway
			Controls eCommerce payment systems.
			Wrapper overtop PayPal Payments Pro, Authorize.Net, PayPal Payflow Gateway, LinkPoint API
	*/

	use BigTree\Setting;
	
	class BigTreePaymentGateway {

		public $Provider;
		public $Service;
		public $Settings;

		/*
			Constructor:
				Sets up the currently configured service.

			Parameters:
				gateway_override - Optionally specify the gateway you want to use (defaults to the admin default)
		*/
		
		function __construct($gateway_override = false) {
			$setup = Setting::value("bigtree-internal-payment-gateway");

			// Setting doesn't exist? Create it.
			if ($setup === false) {
				$setting = Setting::create("bigtree-internal-payment-gateway","Payment Gateway","","",array(),"",true,true,true);
				$setting->Value = array("service" => "", "settings" => array());
				$setting->save();

				$this->Service = "";
				$this->Settings = array();
			} else {
				$this->Service = $setup["service"];
				$this->Settings = $setup["settings"];
			}

			$this->Service = isset($setup["service"]) ? $setup["service"] : "";
			$this->Settings = isset($setup["settings"]) ? $setup["settings"] : array();

			// If you specifically request a certain service, use it instead of the default
			if ($gateway_override) {
				$this->Service = $gateway_override;
			}

			if ($this->Service == "authorize.net") {
				$this->Provider = new BigTree\PaymentGateway\AuthorizeNet;
			} elseif ($this->Service == "paypal") {
				$this->Provider = new BigTree\PaymentGateway\PayPalPaymentsPro;
			} elseif ($this->Service == "paypal-rest") {
				$this->Provider = new BigTree\PaymentGateway\PayPalREST;
			} elseif ($this->Service == "payflow") {
				$this->Provider = new BigTree\PaymentGateway\Payflow;
			} elseif ($this->Service == "linkpoint") {
				$this->Provider = new BigTree\PaymentGateway\LinkPoint;
			}
		}

		// Magic method to return the provider's properties instead
		function __get($property) {
			if (isset($this->Provider->$property)) {
				return $this->Provider->$property;
			}

			trigger_error("Invalid property: $property on ".__class__, E_USER_WARNING);

			return null;
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
			return $this->Provider->authorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
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
			return $this->Provider->capture($transaction,$amount);
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
			return BigTree\PaymentGateway\Provider::cardType($card_number);
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
			return $this->Provider->charge($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
		}
		
		/*
			Function: createRecurringPayment
				Creates a recurring payment profile.
			
			Parameters:
				description - A description of the subscription.
				amount - The amount to charge (includes the tax).
				start_date - The date to begin charging the user (YYYY-MM-DD, blank for immediately).
				period - The unit of how often to charge the user (options: day, week, month, year)
				frequency - The number of units that make up each billing period. (i.e. for bi-weekly the frequency is "2" and period is "week", quarterly would be frequency "3" and period "month")
				card_name - Name as it appears on the credit card.
				card_number - Credit card number.
				card_expiration - 4 or 6 digit expiration date (MMYYYY or MMYY).
				cvv - Credit card security code.
				address - An address array with keys "street", "street2", "city", "state", "zip", "country"
				email - Email address of the purchaser.
				trial_amount - The amount to charge during the (optional) trial period.
				trial_period - The unit of how often to charge the user during the (optional) trial period (options: day, week, month, year)
				trial_frequency - The number of units that make up each billing segment of the (optional) trial period.
				trial_length - The number of billing cycles the (optional) trial period should last.
		
			Returns:
				Subscriber ID if successful, otherwise returns false.
				$this->Message will contain an error message if not successful.
		*/
		
		function createRecurringPayment($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount = false,$trial_period = false,$trial_frequency = false,$trial_length = false) {
			return $this->Provider->createRecurringPayment($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length);
		}

		/*
			Function: formatCurrency
				Formats a currency amount for the payment gateways (they're picky).

			Parameters:
				amount - Currency amount

			Returns:
				A string
		*/

		function formatCurrency($amount) {
			return BigTree\PaymentGateway\Provider::formatCurrency($amount);
		}

		/*
			Function: paypalExpressCheckoutDetails
				Returns checkout details for an Express Checkout transaciton.
				For: PayPal Payments Pro and Payflow Gateway ONLY.
				
			Parameters:
				token - The Express Checkout token returned by PayPal.
			
			Returns:
				An array of buyer information.
		*/
		
		function paypalExpressCheckoutDetails($token) {
			return $this->Provider->paypalExpressCheckoutDetails($token);
		}

		/*
			Function: paypalExpressCheckoutProcess
				Processes an Express Checkout transaction.
				For: PayPal REST API, PayPal Payments Pro and Payflow Gateway ONLY.

			Parameters:
				token - The Express Checkout token returned by PayPal.
				payer_id - The Payer ID returned by PayPal.
				amount - The amount to charge.
			
			Returns:
				An array of buyer information.
		*/
		
		function paypalExpressCheckoutProcess($token,$payer_id,$amount = false) {
			return $this->Provider->paypalExpressCheckoutProcess($token,$payer_id,$amount);
		}

		/*
			Function: paypalExpressCheckoutRedirect
				Sets up a PayPal Express Checkout session and redirects the user to PayPal.
				For: PayPal REST API, PayPal Payments Pro and Payflow Gateway ONLY.
			
			Parameters:
				amount - The amount to charge the user.
				success_url - The URL to return to on successful PayPal login.
				cancel_rul - The URL to return to if the user cancels payment.
			
			Returns:
				false in the event of a failure, otherwise redirects and dies.
		*/
		
		function paypalExpressCheckoutRedirect($amount,$success_url,$cancel_url) {
			return $this->Provider->paypalExpressCheckoutRedirect($amount,$success_url,$cancel_url);
		}

		/*
			Function: paypalRESTVaultAuthorize
				Authorizes a payment using a token from the PayPal REST API vault.
				Payment should be captured using standard "capture" method

			Parameters:
				id - The card ID returned when the card was stored.
				user_id - The user ID related to this card (pass in false if no user id was stored with this card).
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				description - Description of what is being charged.
				email - Email address of the purchaser.
		*/

		function paypalRESTVaultAuthorize($id,$user_id,$amount,$tax = 0,$description = "",$email = "") {
			return $this->paypalRESTVaultCharge($id,$user_id,$amount,$tax,$description,$email,"authorize");
		}

		/*
			Function: paypalRESTVaultCharge
				Charges a credit card using a token from the PayPal REST API vault.

			Parameters:
				id - The card ID returned when the card was stored.
				user_id - The user ID related to this card (pass in false if no user id was stored with this card).
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				description - Description of what is being charged.
				email - Email address of the purchaser.
				action - "sale" or "authorize" (defaults to sale)
		*/

		function paypalRESTVaultCharge($id,$user_id,$amount,$tax = 0,$description = "",$email = "",$action = "sale") {
			return $this->Provider->chargeByProfile($id,$user_id,$amount,$tax,$description,$email,$action);
		}

		/*
			Function: paypalRESTVaultDelete
				Deletes a credit card stored in the PayPal REST API vault.

			Parameters:
				id - The card ID returned when the card was stored.
		*/

		function paypalRESTVaultDelete($id) {
			return $this->Provider->deleteProfile($id);
		}

		/*
			Function: paypalRESTVaultLookup
				Looks up a credit card stored in the PayPal REST API vault.

			Parameters:
				id - The card ID returned when the card was stored.

			Returns:
				Credit card information (only the last 4 digits of the credit card number are visible)
		*/

		function paypalRESTVaultLookup($id) {
			return $this->Provider->getProfile($id);
		}

		/*
			Function: paypalRESTVaultStore
				Stores a credit card in the PayPal REST API vault.

			Parameters:
				name - Name on the credit card
				number - Credit card number
				expiration_date - Expiration date (MMYY or MMYYYY format)
				cvv - Credit card security code.
				address - An address array with keys "street", "street2", "city", "state", "zip", "country"
				user_id - A unique ID to associate with this storage (for example, the user ID of this person on your site)

			Returns:
				A card ID to be used for later recall.
		*/

		function paypalRESTVaultStore($name,$number,$expiration_date,$cvv,$address,$user_id) {
			return $this->Provider->createProfile($name,$number,$expiration_date,$cvv,$address,$user_id);
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
			return $this->Provider->refund($transaction,$card_number,$amount);
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
			return $this->Provider->void($authorization);
		}		
		
	}