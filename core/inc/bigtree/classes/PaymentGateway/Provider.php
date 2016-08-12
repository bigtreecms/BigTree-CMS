<?php
	/*
		Class: BigTree\PaymentGateway\Provider
			Provides a base interface for payment gateway providers.
	*/

	namespace BigTree\PaymentGateway;

	use BigTree\Setting;
	
	class Provider {

		public $AVS;
		public $CountryCodes = array("ALAND ISLANDS" => "AX", "ALBANIA" => "AL", "ALGERIA" => "DZ", "AMERICAN SAMOA" => "AS", "ANDORRA" => "AD", "ANGUILLA" => "AI", "ANTARCTICA" => "AQ", "ANTIGUA AND BARBUDA" => "AG", "ARGENTINA" => "AR", "ARMENIA" => "AM", "ARUBA" => "AW", "AUSTRALIA" => "AU", "AUSTRIA" => "AT", "AZERBAIJAN" => "AZ", "BAHAMAS" => "BS", "BAHRAIN" => "BH", "BANGLADESH" => "BD", "BARBADOS" => "BB", "BELGIUM" => "BE", "BELIZE" => "BZ", "BENIN" => "BJ", "BERMUDA" => "BM", "BHUTAN" => "BT", "BOSNIA-HERZEGOVINA" => "BA", "BOTSWANA" => "BW", "BOUVET ISLAND" => "BV", "BRAZIL" => "BR", "BRITISH INDIAN OCEAN TERRITORY" => "IO", "BRUNEI DARUSSALAM" => "BN", "BULGARIA" => "BG", "BURKINA FASO" => "BF", "CANADA" => "CA", "CAPE VERDE" => "CV", "CAYMAN ISLANDS" => "KY", "CENTRAL AFRICAN REPUBLIC" => "CF", "CHILE" => "CL", "CHINA" => "CN", "CHRISTMAS ISLAND" => "CX", "COCOS (KEELING) ISLANDS" => "CC", "COLOMBIA" => "CO", "COOK ISLANDS" => "CK", "COSTA RICA" => "CR", "CYPRUS" => "CY", "CZECH REPUBLIC" => "CZ", "DENMARK" => "DK", "DJIBOUTI" => "DJ", "DOMINICA" => "DM", "DOMINICAN REPUBLIC" => "DO", "ECUADOR" => "EC", "EGYPT" => "EG", "EL SALVADOR" => "SV", "ESTONIA" => "EE", "FALKLAND ISLANDS (MALVINAS)" => "FK", "FAROE ISLANDS" => "FO", "FIJI" => "FJ", "FINLAND" => "FI", "FRANCE" => "FR", "FRENCH GUIANA" => "GF", "FRENCH POLYNESIA" => "PF", "FRENCH SOUTHERN TERRITORIES" => "TF", "GABON" => "GA", "GAMBIA" => "GM", "GEORGIA" => "GE", "GERMANY" => "DE", "GHANA" => "GH", "GIBRALTAR" => "GI", "GREECE" => "GR", "GREENLAND" => "GL", "GRENADA" => "GD", "GUADELOUPE" => "GP", "GUAM" => "GU", "GUERNSEY" => "CG", "GUYANA" => "GY", "HEARD ISLAND AND MCDONALD ISLANDS" => "HM", "HOLY SEE (VATICAN CITY STATE)" => "VA", "HONDURAS" => "HN", "HONG KONG" => "HK", "HUNGARY" => "HU", "ICELAND" => "IS", "INDIA" => "IN", "INDONESIA" => "ID", "IRELAND" => "IE", "ISLE OF MAN" => "IM", "ISRAEL" => "IL", "ITALY" => "IT", "JAMAICA" => "JM", "JAPAN" => "JP", "JERSEY" => "JE", "JORDAN" => "JO", "KAZAKHSTAN" => "KZ", "KIRIBATI" => "KI", "KOREA, REPUBLIC OF" => "KR", "KUWAIT" => "KW", "KYRGYZSTAN" => "KG", "LATVIA" => "LV", "LESOTHO" => "LS", "LIECHTENSTEIN" => "LI", "LITHUANIA" => "LT", "LUXEMBOURG" => "LU", "MACAO" => "MO", "MACEDONIA" => "MK", "MADAGASCAR" => "MG", "MALAWI" => "MW", "MALAYSIA" => "MY", "MALTA" => "MT", "MARSHALL ISLANDS" => "MH", "MARTINIQUE" => "MQ", "MAURITANIA" => "MR", "MAURITIUS" => "MU", "MAYOTTE" => "YT", "MEXICO" => "MX", "MICRONESIA, FEDERATED STATES OF" => "FM", "MOLDOVA, REPUBLIC OF" => "MD", "MONACO" => "MC", "MONGOLIA" => "MN", "MONTENEGRO" => "ME", "MONTSERRAT" => "MS", "MOROCCO" => "MA", "MOZAMBIQUE" => "MZ", "NAMIBIA" => "NA", "NAURU" => "NR", "NEPAL" => "NP", "NETHERLANDS" => "NL", "NETHERLANDS ANTILLES" => "AN", "NEW CALEDONIA" => "NC", "NEW ZEALAND" => "NZ", "NICARAGUA" => "NI", "NIGER" => "NE", "NIUE" => "NU", "NORFOLK ISLAND" => "NF", "NORTHERN MARIANA ISLANDS" => "MP", "NORWAY" => "NO", "OMAN" => "OM", "PALAU" => "PW", "PALESTINE" => "PS", "PANAMA" => "PA", "PARAGUAY" => "PY", "PERU" => "PE", "PHILIPPINES" => "PH", "PITCAIRN" => "PN", "POLAND" => "PL", "PORTUGAL" => "PT", "PUERTO RICO" => "PR", "QATAR" => "QA", "REUNION" => "RE", "ROMANIA" => "RO", "RUSSIAN FEDERATION" => "RU", "RWANDA" => "RW", "SAINT HELENA" => "SH", "SAINT KITTS AND NEVIS" => "KN", "SAINT LUCIA" => "LC", "SAINT PIERRE AND MIQUELON" => "PM", "SAINT VINCENT AND THE GRENADINES" => "VC", "SAMOA" => "WS", "SAN MARINO" => "SM", "SAO TOME AND PRINCIPE" => "ST", "SAUDI ARABIA" => "SA", "SENEGAL" => "SN", "SERBIA" => "RS", "SEYCHELLES" => "SC", "SINGAPORE" => "SG", "SLOVAKIA" => "SK", "SLOVENIA" => "SI", "SOLOMON ISLANDS" => "SB", "SOUTH AFRICA" => "ZA", "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS" => "GS", "SPAIN" => "ES", "SURINAME" => "SR", "SVALBARD AND JAN MAYEN" => "SJ", "SWAZILAND" => "SZ", "SWEDEN" => "SE", "SWITZERLAND" => "CH", "TAIWAN, PROVINCE OF CHINA" => "TW", "TANZANIA, UNITED REPUBLIC OF" => "TZ", "THAILAND" => "TH", "TIMOR-LESTE" => "TL", "TOGO" => "TG", "TOKELAU" => "TK", "TONGA" => "TO", "TRINIDAD AND TOBAGO" => "TT", "TUNISIA" => "TN", "TURKEY" => "TR", "TURKMENISTAN" => "™", "TURKS AND CAICOS ISLANDS " => "TC", "TUVALU" => "TV", "UGANDA" => "UG", "UKRAINE" => "UA", "UNITED ARAB EMIRATES" => "AE", "UNITED KINGDOM" => "GB", "UNITED STATES" => "US", "UNITED STATES MINOR OUTLYING ISLANDS" => "UM", "URUGUAY" => "UY", "UZBEKISTAN" => "UZ", "VANUATU" => "VU", "VENEZUELA" => "VE", "VIET NAM" => "VN", "VIRGIN ISLANDS, BRITISH" => "VG", "VIRGIN ISLANDS, U.S." => "VI", "WALLIS AND FUTUNA" => "WF", "WESTERN SAHARA" => "EH", "ZAMBIA" => "ZM");
		public $CVV;
		public $Last4CC;
		public $Message;
		public $PayPalPeriods = array("day" => "Day", "week" => "Week", "month" => "Month", "year" => "Year");
		public $Service;
		public $Setting;
		public $Settings;
		public $Transaction;
		public $Unresponsive;

		/*
			Constructor:
				Sets up the currently configured service.

			Parameters:
				gateway_override - Optionally specify the gateway you want to use (defaults to the admin default)
		*/
		
		function __construct($gateway_override = false) {
			$this->Setting = new Setting("bigtree-internal-payment-gateway");

			// Setting doesn't exist? Create it.
			if (empty($this->Setting->ID)) {
				$this->Setting = Setting::create("bigtree-internal-payment-gateway", "Payment Gateway", "", "", array(), "", true, true, true);
				$this->Setting->Value = array("service" => "", "settings" => array());
				$this->Setting->save();
			}
			
			$this->Service = &$this->Setting["service"];
			$this->Settings = &$this->Setting["settings"];
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
		
		function authorize($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description = "", $email = "", $phone = "", $customer = "") {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: authorizeByProfile
				Authorizes a payment using a card / profile ID stored by the provider.
				Payment should be captured using standard "capture" method

			Parameters:
				id - The card /profile ID returned when the card was stored.
				user_id - The user ID related to this card (pass in false if no user id was stored with this card).
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				description - Description of what is being charged.
				email - Email address of the purchaser.
		*/

		function authorizeByProfile($id, $user_id, $amount, $tax = 0, $description = "", $email = "") {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
		
		function capture($transaction, $amount = 0) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: cardType
				Returns the type of credit card based on the number.
			
			Parameters:
				card_number - The credit card number.
			
			Returns:
				The name of the card issuer.
		*/
		
		static function cardType($card_number) {
			$cards = array(
				"visa" => "(4\d{12}(?:\d{3})?)",
				"amex" => "(3[47]\d{13})",
				"jcb" => "(35[2-8][89]\d\d\d{10})",
				"maestro" => "((?:5020|5038|6304|6579|6761)\d{12}(?:\d\d)?)",
				"solo" => "((?:6334|6767)\d{12}(?:\d\d)?\d?)",
				"mastercard" => "(5[1-5]\d{14})",
				"switch" => "(?:(?:(?:4903|4905|4911|4936|6333|6759)\d{12})|(?:(?:564182|633110)\d{10})(\d\d)?\d?)",
				"discover" => '(^6(?:011|5[0-9]{2})[0-9]{12}$)'
			);
			$names = array("visa", "amex", "jcb", "maestro", "solo", "mastercard", "switch", "discover");
			$matches = array();
			$pattern = "#^(?:".implode("|", $cards).")$#";
			$result = preg_match($pattern, str_replace(" ", "", $card_number), $matches);

			if ($result > 0) {
				return $names[count($matches) - 2];
			}

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
		
		function charge($amount, $tax, $card_name, $card_number, $card_expiration, $cvv, $address, $description = "", $email = "", $phone = "", $customer = "") {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: chargeByProfile
				Charges a credit card using a card / profile ID stored by the provider.

			Parameters:
				id - The card / profile ID returned when the card was stored.
				user_id - The user ID related to this card (pass in false if no user id was stored with this card).
				amount - The amount to charge (includes the tax).
				tax - The amount of tax to charge (for accounting purposes, must also be included in total amount).
				description - Description of what is being charged.
				email - Email address of the purchaser.
				action - "sale" or "authorize" (defaults to sale)
		*/

		function chargeByProfile($id, $user_id, $amount, $tax = 0, $description = "", $email = "", $action = "sale") {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: countryCode
				Returns the two letter country code if a full name was entered.

			Parameters:
				country - Either a two letter country code or a full country name

			Returns:
				Two letter country code (if found)
		*/

		function countryCode($country) {
			if (strlen($country) == 2) {
				return $country;
			}

			$code = $this->CountryCodes[strtoupper($country)];
			
			return $code ?: $country;
		}

		/*
			Function: createProfile
				Stores a credit card in the provider's profile/vault storage.

			Parameters:
				name - Name on the credit card
				number - Credit card number
				expiration_date - Expiration date (MMYY or MMYYYY format)
				cvv - Credit card security code.
				address - An address array with keys "street", "street2", "city", "state", "zip", "country"
				user_id - A unique ID to associate with this storage (for example, the user ID of this person on your site)

			Returns:
				A card/profile ID to be used for later recall.
		*/

		function createProfile($name, $number, $expiration_date, $cvv, $address, $user_id) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
		
		function createRecurringPayment($description, $amount, $start_date, $period, $frequency, $card_name, $card_number, $card_expiration, $cvv, $address, $email, $trial_amount = false, $trial_period = false, $trial_frequency = false, $trial_length = false) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: deleteProfile
				Deletes a credit card / profile stored by the provider.

			Parameters:
				id - The card / profile ID returned when the card / profile was stored.
		*/

		function deleteProfile($id) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: formatCurrency
				Formats a currency amount for the payment gateways (they're picky).

			Parameters:
				amount - Currency amount

			Returns:
				A string
		*/

		static function formatCurrency($amount) {
			return number_format(round(floatval(str_replace(array('$', ','), "", $amount)), 2), 2, ".", "");
		}

		/*
			Function: getProfile
				Looks up a credit card / profile stored by the provider.

			Parameters:
				id - The card ID returned when the card was stored.

			Returns:
				Credit card / Profile information (only the last 4 digits of the credit card number are visible)
		*/

		function getProfile($id) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
		
		function paypalExpressCheckoutProcess($token, $payer_id, $amount = false) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
		
		function paypalExpressCheckoutRedirect($amount, $success_url, $cancel_url) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
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
		
		function refund($transaction, $card_number = "", $amount = 0) {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

		/*
			Function: urldecodeArray
				urldecodes a whole array.
		*/
		
		protected function urldecodeArray($array) {
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
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

	}