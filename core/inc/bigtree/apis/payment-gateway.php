<?
	/*
		Class: BigTreePaymentGateway
			Controls eCommerce payment systems.
			Wrapper overtop PayPal Payments Pro, Authorize.Net, PayPal Payflow Gateway, LinkPoint API
	*/
	
	class BigTreePaymentGateway {
		
		var $Service = "";
		var $PayPalPeriods = array("day" => "Day", "week" => "Week", "month" => "Month", "year" => "Year");
		var $CountryCodes = array("ALAND ISLANDS" => "AX", "ALBANIA" => "AL", "ALGERIA" => "DZ", "AMERICAN SAMOA" => "AS", "ANDORRA" => "AD", "ANGUILLA" => "AI", "ANTARCTICA" => "AQ","ANTIGUA AND BARBUDA" => "AG", "ARGENTINA" => "AR", "ARMENIA" => "AM", "ARUBA" => "AW", "AUSTRALIA" => "AU", "AUSTRIA" => "AT", "AZERBAIJAN" => "AZ", "BAHAMAS" => "BS", "BAHRAIN" => "BH", "BANGLADESH" => "BD", "BARBADOS" => "BB", "BELGIUM" => "BE", "BELIZE" => "BZ", "BENIN" => "BJ", "BERMUDA" => "BM", "BHUTAN" => "BT", "BOSNIA-HERZEGOVINA" => "BA", "BOTSWANA" => "BW", "BOUVET ISLAND" => "BV", "BRAZIL" => "BR", "BRITISH INDIAN OCEAN TERRITORY" => "IO", "BRUNEI DARUSSALAM" => "BN", "BULGARIA" => "BG", "BURKINA FASO" => "BF", "CANADA" => "CA", "CAPE VERDE" => "CV", "CAYMAN ISLANDS" => "KY", "CENTRAL AFRICAN REPUBLIC" => "CF", "CHILE" => "CL", "CHINA" => "CN", "CHRISTMAS ISLAND" => "CX", "COCOS (KEELING) ISLANDS" => "CC", "COLOMBIA" => "CO", "COOK ISLANDS" => "CK", "COSTA RICA" => "CR", "CYPRUS" => "CY", "CZECH REPUBLIC" => "CZ", "DENMARK" => "DK", "DJIBOUTI" => "DJ", "DOMINICA" => "DM", "DOMINICAN REPUBLIC" => "DO", "ECUADOR" => "EC", "EGYPT" => "EG", "EL SALVADOR" => "SV", "ESTONIA" => "EE", "FALKLAND ISLANDS (MALVINAS)" => "FK", "FAROE ISLANDS" => "FO", "FIJI" => "FJ", "FINLAND" => "FI", "FRANCE" => "FR", "FRENCH GUIANA" => "GF", "FRENCH POLYNESIA" => "PF", "FRENCH SOUTHERN TERRITORIES" => "TF", "GABON" => "GA", "GAMBIA" => "GM", "GEORGIA" => "GE", "GERMANY" => "DE", "GHANA" => "GH", "GIBRALTAR" => "GI", "GREECE" => "GR", "GREENLAND" => "GL", "GRENADA" => "GD", "GUADELOUPE" => "GP", "GUAM" => "GU", "GUERNSEY" => "CG", "GUYANA" => "GY", "HEARD ISLAND AND MCDONALD ISLANDS" => "HM", "HOLY SEE (VATICAN CITY STATE)" => "VA", "HONDURAS" => "HN", "HONG KONG" => "HK", "HUNGARY" => "HU", "ICELAND" => "IS", "INDIA" => "IN", "INDONESIA" => "ID", "IRELAND" => "IE", "ISLE OF MAN" => "IM", "ISRAEL" => "IL", "ITALY" => "IT", "JAMAICA" => "JM", "JAPAN" => "JP", "JERSEY" => "JE", "JORDAN" => "JO", "KAZAKHSTAN" => "KZ", "KIRIBATI" => "KI", "KOREA, REPUBLIC OF" => "KR", "KUWAIT" => "KW", "KYRGYZSTAN" => "KG", "LATVIA" => "LV", "LESOTHO" => "LS", "LIECHTENSTEIN" => "LI", "LITHUANIA" => "LT", "LUXEMBOURG" => "LU", "MACAO" => "MO", "MACEDONIA" => "MK", "MADAGASCAR" => "MG", "MALAWI" => "MW", "MALAYSIA" => "MY", "MALTA" => "MT", "MARSHALL ISLANDS" => "MH", "MARTINIQUE" => "MQ", "MAURITANIA" => "MR", "MAURITIUS" => "MU", "MAYOTTE" => "YT", "MEXICO" => "MX", "MICRONESIA, FEDERATED STATES OF" => "FM", "MOLDOVA, REPUBLIC OF" => "MD", "MONACO" => "MC", "MONGOLIA" => "MN", "MONTENEGRO" => "ME", "MONTSERRAT" => "MS", "MOROCCO" => "MA", "MOZAMBIQUE" => "MZ", "NAMIBIA" => "NA", "NAURU" => "NR", "NEPAL" => "NP", "NETHERLANDS" => "NL", "NETHERLANDS ANTILLES" => "AN", "NEW CALEDONIA" => "NC", "NEW ZEALAND" => "NZ", "NICARAGUA" => "NI", "NIGER" => "NE", "NIUE" => "NU", "NORFOLK ISLAND" => "NF", "NORTHERN MARIANA ISLANDS" => "MP", "NORWAY" => "NO", "OMAN" => "OM", "PALAU" => "PW", "PALESTINE" => "PS", "PANAMA" => "PA", "PARAGUAY" => "PY", "PERU" => "PE", "PHILIPPINES" => "PH", "PITCAIRN" => "PN", "POLAND" => "PL", "PORTUGAL" => "PT", "PUERTO RICO" => "PR", "QATAR" => "QA", "REUNION" => "RE", "ROMANIA" => "RO", "RUSSIAN FEDERATION" => "RU", "RWANDA" => "RW", "SAINT HELENA" => "SH", "SAINT KITTS AND NEVIS" => "KN", "SAINT LUCIA" => "LC", "SAINT PIERRE AND MIQUELON" => "PM", "SAINT VINCENT AND THE GRENADINES" => "VC", "SAMOA" => "WS", "SAN MARINO" => "SM", "SAO TOME AND PRINCIPE" => "ST", "SAUDI ARABIA" => "SA", "SENEGAL" => "SN", "SERBIA" => "RS", "SEYCHELLES" => "SC", "SINGAPORE" => "SG", "SLOVAKIA" => "SK", "SLOVENIA" => "SI", "SOLOMON ISLANDS" => "SB", "SOUTH AFRICA" => "ZA", "SOUTH GEORGIA AND THE SOUTH SANDWICH ISLANDS" => "GS", "SPAIN" => "ES", "SURINAME" => "SR", "SVALBARD AND JAN MAYEN" => "SJ", "SWAZILAND" => "SZ", "SWEDEN" => "SE", "SWITZERLAND" => "CH", "TAIWAN, PROVINCE OF CHINA" => "TW", "TANZANIA, UNITED REPUBLIC OF" => "TZ", "THAILAND" => "TH", "TIMOR-LESTE" => "TL", "TOGO" => "TG", "TOKELAU" => "TK", "TONGA" => "TO", "TRINIDAD AND TOBAGO" => "TT", "TUNISIA" => "TN", "TURKEY" => "TR", "TURKMENISTAN" => "™", "TURKS AND CAICOS ISLANDS " => "TC", "TUVALU" => "TV","UGANDA" => "UG", "UKRAINE" => "UA", "UNITED ARAB EMIRATES" => "AE", "UNITED KINGDOM" => "GB", "UNITED STATES" => "US", "UNITED STATES MINOR OUTLYING ISLANDS" => "UM", "URUGUAY" => "UY", "UZBEKISTAN" => "UZ", "VANUATU" => "VU", "VENEZUELA" => "VE", "VIET NAM" => "VN", "VIRGIN ISLANDS, BRITISH" => "VG", "VIRGIN ISLANDS, U.S." => "VI", "WALLIS AND FUTUNA" => "WF", "WESTERN SAHARA" => "EH", "ZAMBIA" => "ZM");
		
		/*
			Constructor:
				Sets up the currently configured service.
		*/
		
		function __construct() {
			$admin = new BigTreeAdmin;
			$s = $admin->getSetting("bigtree-internal-payment-gateway");
			if ($s === false) {
				$admin->createSetting(array(
					"id" => "bigtree-internal-payment-gateway",
					"system" => "on",
					"encrypted" => "on"
				));
				$s = array("service" => "", "settings" => array());
				$admin->updateSettingValue("bigtree-internal-payment-gateway",$s);
			}

			// If for some reason the setting doesn't exist, make one.
			$this->Service = isset($s["value"]["service"]) ? $s["value"]["service"] : "";
			$this->Settings = isset($s["value"]["settings"]) ? $s["value"]["settings"] : array();

			if ($this->Service == "authorize.net") {
				$this->setupAuthorize();
			} elseif ($this->Service == "paypal") {
				$this->setupPayPal();
			} elseif ($this->Service == "paypal-rest") {
				$this->setupPayPalREST();
			} elseif ($this->Service == "payflow") {
				$this->setupPayflow();
			} elseif ($this->Service == "linkpoint") {
				$this->setupLinkPoint();
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
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			if ($this->Service == "authorize.net") {
				return $this->authorizeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal") {
				return $this->authorizePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal-rest") {
				return $this->authorizePayPalREST($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "payflow") {
				return $this->authorizePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "linkpoint") {
				return $this->authorizeLinkPoint($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);	
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
			Function: authorizeLinkPoint
				First Data / LinkPoint interface for <authorize>
		*/
		
		protected function authorizeLinkPoint($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargeLinkPoint($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"PREAUTH");
		}
		
		/*
			Function: authorizePayPal
				PayPal Payments Pro interface for <authorize>
		*/
		
		protected function authorizePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"Authorization");
		}

		/*
			Function: authorizePayPalREST
				PayPal REST API interface for <authorize>
		*/
		
		protected function authorizePayPalREST($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargePayPalREST($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"authorize");
		}
		
		/*
			Function: authorizePayflow
				PayPal Payflow Gateway interface for <authorize>
		*/
		
		protected function authorizePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer) {
			return $this->chargePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,"A");
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
			} elseif ($this->Service == "paypal-rest") {
				return $this->capturePayPalREST($transaction,$amount);
			} elseif ($this->Service == "payflow") {
				return $this->capturePayflow($transaction,$amount);
			} elseif ($this->Service == "linkpoint") {
				return $this->captureLinkPoint($transaction,$amount);
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
			Function: captureLinkPoint
				First Data / LinkPoint interface for <capture>
		*/
		
		protected function captureLinkPoint($transaction,$amount) {
			$params = array(
				"orderoptions" => array(
					"ordertype" => "POSTAUTH"
				),
				"transactiondetails" => array(
					"ip" => $_SERVER["REMOTE_ADDR"],
					"oid" => $transaction
				)
			);
			
			if ($amount) {
				$params["payment"]["chargetotal"] = $amount;
			}
			
			$response = $this->sendLinkPoint($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);

			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
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
			Function: capturePayPalREST
				PayPal REST API interface for <capture>
		*/
		
		protected function capturePayPalREST($transaction,$amount) {
			$data = json_encode(array(
				"amount" => array(
					"currency" => "USD",
					"total" => $amount
				)
			));

			$response = $this->sendPayPalREST("payments/authorization/$transaction/capture",$data);
			if ($response->state == "completed") {
				return $response->id;
			} else {
				$this->Message = $response->message;
				return false;
			}
		}
		
		/*
			Function: capturePayflow
				PayPal Payflow Gateway interface for <capture>
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
				"discover" => '(^6(?:011|5[0-9]{2})[0-9]{12}$)'
			);
			$names = array("visa","amex","jcb","maestro","solo","mastercrad","switch","discover");
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
		
		function charge($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description = "",$email = "",$phone = "",$customer = "") {
			// Clean up the amount and tax.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$tax = round(floatval(str_replace(array('$',','),"",$tax)),2);
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			if ($this->Service == "authorize.net") {
				return $this->chargeAuthorize($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal") {
				return $this->chargePayPal($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "paypal-rest") {
				return $this->chargePayPalREST($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "payflow") {
				return $this->chargePayflow($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
			} elseif ($this->Service == "linkpoint") {
				return $this->chargeLinkPoint($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer);
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
			Function: chargeLinkPoint
				First Data / LinkPoint interface for <charge>
		*/
		
		protected function chargeLinkPoint($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,$action = "SALE") {
			$card_month = substr($card_expiration,0,2);
			$card_year = substr($card_expiration,-2,2);
			
			$params = array(
				"orderoptions" => array(
					"ordertype" => $action
				),
				"creditcard" => array(
					"cardnumber" => $card_number,
					"cardexpmonth" => $card_month,
					"cardexpyear" => $card_year,
					"cvmvalue" => $cvv,
					"cvmindicator" => "provided"
				),
				"transactiondetails" => array(
					"ip" => $_SERVER["REMOTE_ADDR"]
				),
				"billing" => array(
					"name" => $card_name,
					"address1" => $address["street"],
					"address2" => $address["street2"],
					"city" => $address["city"],
					"state" => $address["state"],
					"zip" => $address["zip"],
					"phone" => $phone,
					"email" => $email,
					"userid" => $customer
				),
				"payment" => array(
					"tax" => $tax,
					"chargetotal" => $amount
				),
				"notes" => array(
					"comments" => $description
				)
			);
			
			$response = $this->sendLinkPoint($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);
			$this->Last4CC = substr(trim($card_number),-4,4);
			
			// Get a common AVS response.
			$a = substr(strval($response->r_avs),0,2);
			if ($a == "YN") {
				$this->AVS = "Address";
			} elseif ($a == "NY") {
				$this->AVS = "Zip";
			} elseif ($a == "YY") {
				$this->AVS = "Both";
			} else {
				$this->AVS = false;
			}
			
			// CVV match.
			if (substr(strval($response->r_avs),-1,1) == "M") {
				$this->CVV = true;
			} else {
				$this->CVV = false;
			}
			
			if (strval($response->r_message) == "APPROVED") {
				return $this->Transaction;
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
			Function: chargePayPalREST
				PayPal REST API interface for <charge>
		*/
		
		protected function chargePayPalREST($amount,$tax,$card_name,$card_number,$card_expiration,$cvv,$address,$description,$email,$phone,$customer,$action = "sale") {
			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));

			// Separate card expiration out
			$card_expiration_month = substr($card_expiration,0,2);
			$card_expiration_year = substr($card_expiration,2);				
			if (strlen($card_expiration) == 4) {
				$card_expiration_year = "20".$card_expiration_year;
			}

			// See if we can get a country code
			$country = isset($this->CountryCodes[strtoupper($address["country"])]) ? $this->CountryCodes[strtoupper($address["country"])] : $address["country"];

			// Split out tax and subtotals if present
			if ($tax) {
				$transaction_details = array(
					"subtotal" => $amount - $tax,
					"tax" => $tax,
					"shipping" => 0
				);
			} else {
				$transaction_details = false;
			}

			$data = array(
				"intent" => $action,
				"payer" => array(
					"payment_method" => "credit_card",
					"funding_instruments" => array(
						array(
							"credit_card" => array(
								"number" => $card_number,
								"type" => $this->cardType($card_number),
								"expire_month" => $card_expiration_month,
								"expire_year" => $card_expiration_year,
								"cvv2" => $cvv,
								"first_name" => $first_name,
								"last_name" => $last_name,
								"billing_address" => array(
									"line1" => $address["street"],
									"line2" => $address["street2"],
									"city" => $address["city"],
									"state" => $address["state"],
									"postal_code" => $address["zip"],
									"country_code" => $country
								)
							)
						)
					)
				),
				"transactions" => array(array(
					"amount" => array(
						"total" => $amount,
						"currency" => "USD"
					)
				))
			);

			if ($transaction_details) {
				$data["transactions"][0]["amount"]["details"] = $transaction_details;
			}
			if ($email) {
				$data["payer"]["payer_info"]["email"] = $email;
			}
			if ($description) {
				$data["transactions"][0]["description"] = $description;
			}

			$response = $this->sendPayPalREST("payments/payment",json_encode($data));

			if ($response->state == "approved") {
				$this->Last4CC = substr(trim($card_number),-4,4);

				if ($action == "authorize") {
					$this->Transaction = $response->transactions[0]->related_resources[0]->authorization->id;
				} else {
					$this->Transaction = $response->transactions[0]->related_resources[0]->sale->id;
				}

				return $this->Transaction;
			} else {
				$this->Message = $response->message;
				$this->Errors = $response->details;

				return false;
			}
		}
		
		/*
			Function: chargePayflow
				PayPal Payflow Gateway interface for <charge>
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
			// Clean up the amount and trial amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$trial_amount = round(floatval(str_replace(array('$',','),"",$trial_amount)),2);
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);
			
			// If a start date wasn't given, do it now.
			if (!$start_date) {
				$start_date = date("Y-m-d H:i:s");
			}

			if ($this->Service == "authorize.net") {
				return $this->createRecurringPaymentAuthorize($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length,$customer);
			} elseif ($this->Service == "paypal") {
				return $this->createRecurringPaymentPayPal($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length,$customer);
			} elseif ($this->Service == "paypal-rest") {
				return $this->createRecurringPaymentPayPalREST($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length,$customer);
			} elseif ($this->Service == "payflow") {
				return $this->createRecurringPaymentPayflow($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length,$customer);
			} elseif ($this->Service == "linkpoint") {
				return $this->createRecurringPaymentLinkPoint($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length,$customer);
			}
			return false;
		}
		
		/*
			Function: createRecurringPaymentAuthorize
				Authorize.Net interface for <createRecurringPayment>
		*/
		
		protected function createRecurringPaymentAuthorize($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length) {
		
		}
		
		/*
			Function: createRecurringPaymentLinkPoint
				First Data / LinkPoint interface for <createRecurringPayment>
		*/
		
		protected function createRecurringPaymentLinkPoint($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length) {
		
		}
		
		/*
			Function: createRecurringPaymentPayflow
				Payflow Gateway interface for <createRecurringPayment>
		*/
		
		protected function createRecurringPaymentPayflow($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length) {
		
		}
		
		/*
			Function: createRecurringPaymentPayPal
				PayPal Payments Pro interface for <createRecurringPayment>
		*/
		
		protected function createRecurringPaymentPayPal($description,$amount,$start_date,$period,$frequency,$card_name,$card_number,$card_expiration,$cvv,$address,$email,$trial_amount,$trial_period,$trial_frequency,$trial_length) {
			$params = array();
			
			$sd = strtotime($start_date);
			// Parameters specific to creating a recurring profile
			$params["METHOD"] = "CreateRecurringPaymentsProfile";
			$params["PROFILESTARTDATE"] = gmdate("Y-m-d",$sd)."T".gmdate("H:i:s",$sd)."ZL";
			$params["BILLINGPERIOD"] = $this->PayPalPeriods[$period];
			$params["BILLINGFREQUENCY"] = $frequency;
			if ($trial_amount) {
				$params["TRIALAMT"] = $trial_amount;
				$params["TRIALBILLINGPERIOD"] = $this->PayPalPeriods[$trial_period];
				$params["TRIALBILLINGFREQUENCY"] = $trial_frequency;
				$params["TRIALTOTALBILLINGCYCLES"] = $trial_length;
			}
			$params["DESC"] = $description;

			// Split the card name into first name and last name.
			$first_name = substr($card_name,0,strpos($card_name," "));
			$last_name = trim(substr($card_name,strlen($first_name)));
			
			$params["AMT"] = $amount;
			$params["CREDITCARDTYPE"] = $this->cardType($card_number);
			$params["ACCT"] = $card_number;
			$params["EXPDATE"] = $card_expiration;
			$params["CVV2"] = $cvv;
			
			
			$params["FIRSTNAME"] = $first_name;
			$params["LASTNAME"] = $last_name;
			$params["STREET"] = trim($address["street"]." ".$address["street2"]);
			$params["CITY"] = $address["city"];
			$params["STATE"] = $address["state"];
			$params["ZIP"] = $address["zip"];
			if (strlen($address["country"]) == 2) {
				$params["COUNTRYCODE"] = $address["country"];
			} else {
				$params["COUNTRYCODE"] = $this->CountryCodes[strtoupper($address["country"])];
			}
			
			$params["EMAIL"] = $email;
			
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
			// PayPal REST API
			} elseif ($this->Service == "paypal-rest") {
				$token = $_SESSION["bigtree"]["paypal-rest-payment-id"];
				$response = $this->sendPayPalREST("payments/payment/$token");
				if ($response->id) {
					return $response;
				} else {
					return false;
				}
			} else {
				return false;
			}
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
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			
			$params = array();
			$params["TOKEN"] = $token;
			$params["PAYERID"] = $payer_id;
			$params["PAYMENTREQUEST_0_AMT"] = $amount;
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
			} elseif ($this->Service == "paypal-rest") {
				$token = $_SESSION["bigtree"]["paypal-rest-payment-id"];
				$data = array("payer_id" => $payer_id);
				if ($amount) {
					$data["transactions"] = array(array("total" => $amount,"currency" => "USD"));
				}
				$response = $this->sendPayPalREST("payments/payment/$token/execute",json_encode($data));
				if ($response->state == "approved") {
					$this->Transaction = $response->transactions[0]->related_resources[0]->sale->id;
					return $this->Transaction;
				} else {
					$this->Errors = $response->details;
					$this->Message = $response->message;
					return false;
				}
			} else {
				return false;
			}
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
			// Clean up the amount.
			$amount = round(floatval(str_replace(array('$',','),"",$amount)),2);
			$params = array();
			
			$params["PAYMENTREQUEST_0_AMT"] = $amount;
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
					BigTree::redirect("https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$total_cost&CURRENCYCODE=USD&RETURNURL=$return_urly&CANCELURL=$cancel_url");
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
					BigTree::redirect("https://www".($this->Environment == "test" ? ".sandbox" : "").".paypal.com/webscr?cmd=_express-checkout&token=".urldecode($response["TOKEN"])."&AMT=$total_cost&CURRENCYCODE=USD&RETURNURL=$return_urly&CANCELURL=$cancel_url");
				} else {
					return false;
				}
			// PayPal REST API
			} elseif ($this->Service == "paypal-rest") {
				$data = json_encode(array(
					"intent" => "sale",
					"redirect_urls" => array(
						"return_url" => $success_url,
						"cancel_url" => $cancel_url
					),
					"payer" => array("payment_method" => "paypal"),
					"transactions" => array(array(
						"amount" => array(
							"total" => $amount,
							"currency" => "USD"
						)
					))
				));
				$response = $this->sendPayPalREST("payments/payment",$data);
				if ($response->state == "created") {
					$_SESSION["bigtree"]["paypal-rest-payment-id"] = $response->id;
					foreach ($response->links as $link) {
						if ($link->rel == "approval_url") {
							BigTree::redirect($link->href);
						}
					}
				} else {
					$this->Errors = $response->details;
					$this->Message = $response->message;
					return false;
				}
			} else {
				return false;
			}
		}

		/*
			Function: paypalRESTTokenRequest
				Fetches a new authorization token from PayPal's OAuth servers.
		*/

		protected function paypalRESTTokenRequest() {
			if ($this->Settings["paypal-rest-environment"] == "test") {
				$url = "api.sandbox.paypal.com";
			} else {
				$url = "api.paypal.com";
			}
			
			$r = json_decode(BigTree::cURL("https://$url/v1/oauth2/token","grant_type=client_credentials",array(CURLOPT_POST => true, CURLOPT_USERPWD => $this->Settings["paypal-rest-client-id"].":".$this->Settings["paypal-rest-client-secret"])));
			if ($r->error) {
				$this->Error = $r->error;
				return false;
			}

			$this->Settings["paypal-rest-expiration"] = date("Y-m-d H:i:s",strtotime("+".$r->expires_in." seconds"));
			$this->Settings["paypal-rest-token"] = $r->access_token;
			$this->saveSettings();
			return true;
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
			// Split out tax and subtotals if present
			if ($tax) {
				$transaction_details = array(
					"subtotal" => $amount - $tax,
					"tax" => $tax,
					"shipping" => 0
				);
			} else {
				$transaction_details = false;
			}

			$data = array(
				"intent" => $action,
				"payer" => array(
					"payment_method" => "credit_card",
					"funding_instruments" => array(array("credit_card_token" => array("credit_card_id" => $id))),	
				),
				"transactions" => array(array(
					"amount" => array(
						"total" => $amount,
						"currency" => "USD"
					)
				))
			);

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

			$response = $this->sendPayPalREST("payments/payment",json_encode($data));

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

				return false;
			}
		}

		/*
			Function: paypalRESTVaultDelete
				Deletes a credit card stored in the PayPal REST API vault.

			Parameters:
				id - The card ID returned when the card was stored.
		*/

		function paypalRESTVaultDelete($id) {
			$this->sendPayPalREST("vault/credit-card/$id","","DELETE");
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
			$response = $this->sendPayPalREST("vault/credit-card/$id");
			if ($response->state != "ok") {
				$this->Message = $response->message;
				return false;
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
			$card->ValidUntil = date("Y-m-d H:i:s",strtotime($response->valid_until));

			return $card;
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
			// Split the card name into first name and last name.
			$first_name = substr($name,0,strpos($name," "));
			$last_name = trim(substr($name,strlen($first_name)));

			// Make card number only have numeric digits
			$number = preg_replace('/\D/', '', $number);

			// Separate card expiration out
			$card_expiration_month = substr($expiration_date,0,2);
			$card_expiration_year = substr($expiration_date,2);				
			if (strlen($expiration_date) == 4) {
				$card_expiration_year = "20".$card_expiration_year;
			}

			// See if we can get a country code
			$country = isset($this->CountryCodes[strtoupper($address["country"])]) ? $this->CountryCodes[strtoupper($address["country"])] : $address["country"];

			$data = json_encode(array(
				"payer_id" => $user_id,
				"number" => $number,
				"type" => $this->cardType($number),
				"expire_month" => $card_expiration_month,
				"expire_year" => $card_expiration_year,
				"cvv2" => $cvv,
				"first_name" => $first_name,
				"last_name" => $last_name,
				"billing_address" => array(
					"line1" => $address["street"],
					"line2" => $address["street2"],
					"city" => $address["city"],
					"state" => $address["state"],
					"postal_code" => $address["zip"],
					"country_code" => $country
				)
			));

			$response = $this->sendPayPalREST("vault/credit-card",$data);
			if ($response->state == "ok") {
				return $response->id;
			}

			$this->Errors = $response->details;
			$this->Message = $response->message;
			return false;
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
			
			// Make card number only have numeric digits
			$card_number = preg_replace('/\D/', '', $card_number);

			if ($this->Service == "authorize.net") {
				return $this->refundAuthorize($transaction,$card_number,$amount);
			} elseif ($this->Service == "paypal") {
				return $this->refundPayPal($transaction,$card_number,$amount);
			} elseif ($this->Service == "paypal-rest") {
				return $this->refundPayPalREST($transaction,$card_number,$amount);
			} elseif ($this->Service == "payflow") {
				return $this->refundPayflow($transaction,$card_number,$amount);
			} elseif ($this->Service == "linkpoint") {
				return $this->refundLinkPoint($transaction,$card_number,$amount);
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
			Function: refundLinkPoint
				First Data / LinkPoint interface for <refund>
		*/
		
		protected function refundLinkPoint($transaction,$card_number,$amount) {
			$params = array(
				"orderoptions" => array(
					"ordertype" => "CREDIT"
				),
				"creditcard" => array(
					"cardnumber" => $card_number
				),
				"transactiondetails" => array(
					"ip" => $_SERVER["REMOTE_ADDR"],
					"oid" => $authorization
				)
			);
			
			if ($amount) {
				$params["payment"]["chargetotal"] = $amount;
			}
			
			$response = $this->sendLinkPoint($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);

			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
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
			Function: refundPayPalREST
				PayPal REST API interface for <refund>

				Note: Currently the PayPal REST API requires an amount to refund if the transaction was first authorized and later captured.
				This does not apply to transactions that were simply charged through the charge() method.
		*/
		
		protected function refundPayPalREST($transaction,$card_number,$amount) {
			if ($amount) {
				$data = json_encode(array(
					"amount" => array(
						"total" => $amount,
						"currency" => "USD"
					)
				));
			} else {
				$data = "{}";
			}
			// First try as if it was a sale.
			$response = $this->sendPayPalREST("payments/sale/$transaction/refund",$data);
			if ($response->state == "completed") {
				return $response->id;
			// Try as if it were auth/capture
			} elseif ($amount) {
				$response = $this->sendPayPalREST("payments/capture/$transaction/refund",$data);
				if ($response->state == "completed") {
					return $response->id;
				}
			}

			$this->Message = $response->message;
			return false;
		}
		
		/*
			Function: refundPayflow
				PayPal Payflow Gateway interface for <refund>
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
			Function: saveSettings
				Saves changed service and settings.
		*/

		function saveSettings() {
			$admin = new BigTreeAdmin;
			$admin->updateSettingValue("bigtree-internal-payment-gateway",array("service" => $this->Service,"settings" => $this->Settings));
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
			Function: sendLinkPoint
				Sends a command to First Data / LinkPoint.
		*/
		
		protected function sendLinkPoint($params) {
			$count = 0;
			$this->Unresponsive = false;
			
			$params["merchantinfo"]["configfile"] = $this->Store;
			$xml = "<order>";
			foreach ($params as $container => $data) {
				$xml .= "<$container>";
				foreach ($data as $key => $val) {
					if (is_array($val)) {
						$xml .= "<$key>";
						foreach ($val as $k => $vl) {
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
				curl_setopt($ch,CURLOPT_URL,$this->PostURL);
				curl_setopt($ch,CURLOPT_POST, 1); 
				curl_setopt($ch,CURLOPT_POSTFIELDS, $xml);
				curl_setopt($ch,CURLOPT_SSLCERT, $this->Certificate);
				curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, 0);
				curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, 0);
				$response = curl_exec($ch);
				if ($response) {
					return simplexml_load_string("<lpresonsecontainer>".$response."</lpresonsecontainer>");
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
			Function: sendPayPalREST
				Sends a command to PayPal REST API.
		*/
		
		protected function sendPayPalREST($endpoint,$data = "",$method = false) {
			if ($method) {
				return json_decode(BigTree::cURL($this->PostURL.$endpoint,$data,array(CURLOPT_HTTPHEADER => $this->Headers,CURLOPT_CUSTOMREQUEST => $method)));
			} else {
				return json_decode(BigTree::cURL($this->PostURL.$endpoint,$data,array(CURLOPT_HTTPHEADER => $this->Headers)));
			}
		}
		
		/*
			Function: sendPayflow
				Sends a command to PayPal Payflow Gateway.
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
		
		protected function setupAuthorize() {
			$this->APILogin = $this->Settings["authorize-api-login"];
			$this->TransactionKey = $this->Settings["authorize-transaction-key"];
			$this->Environment = $this->Settings["authorize-environment"];
			
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
			Function: setupLinkPoint
				Prepares an environment for First Data / LinkPoint.
		*/
		
		protected function setupLinkPoint() {
			$this->Store = $this->Settings["linkpoint-store"];
			$this->Environment = $this->Settings["linkpoint-environment"];
			$this->Certificate = SERVER_ROOT."custom/certificates/".$this->Settings["linkpoint-certificate"];

			if ($this->Environment == "test") {
				$this->PostURL = "https://staging.linkpt.net:1129";
			} else {
				$this->PostURL = "https://secure.linkpt.net:1129";
				$this->DefaultParameters["orderoptions"] = array(
					"result" => "live"
				);
			}
		}
		
		/*
			Function: setupPayPal
				Prepares an environment for PayPal Payments Pro payments.
		*/
		
		protected function setupPayPal() {
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

		/*
			Function: setupPayPalREST
				Prepares an environment for the PayPal REST API.
		*/
		
		protected function setupPayPalREST() {
			// Check on the token expiration, get a new one if needed in the next minute
			if (strtotime($this->Settings["paypal-rest-expiration"]) < time() + 60) {
				$this->paypalRESTTokenRequest();
			}
			// Setup default cURL headers
			$this->Headers = array(
				"Content-type: application/json",
				"Authorization: Bearer ".$this->Settings["paypal-rest-token"]
			);
			
			if ($this->Settings["paypal-rest-environment"] == "test") {
				$this->PostURL = "https://api.sandbox.paypal.com/v1/";
			} else {
				$this->PostURL = "https://api.paypal.com/v1/";
			}
		}
		
		/*
			Function: setupPayflow
				Prepares an environment for PayPal Payflow Gateway payments.
		*/
		
		protected function setupPayflow() {
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
			} elseif ($this->Service == "paypal-rest") {
				return $this->voidPayPalREST($authorization);
			} elseif ($this->Service == "payflow") {
				return $this->voidPayflow($authorization);
			} elseif ($this->Service == "linkpoint") {
				return $this->voidLinkPoint($authorization);
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
			Function: voidLinkPoint
				First Data / LinkPoint interface for <void>
		*/
		
		protected function voidLinkPoint($authorization) {
			$params = array(
				"orderoptions" => array(
					"ordertype" => "VOID"
				),
				"transactiondetails" => array(
					"ip" => $_SERVER["REMOTE_ADDR"],
					"oid" => $authorization
				)
			);
			
			$response = $this->sendLinkPoint($params);
			
			// Setup response messages.
			$this->Transaction = strval($response->r_ordernum);
			$this->Message = strval($response->r_error);

			if (strval($response->r_message) == "ACCEPTED") {
				return $this->Transaction;
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
			Function: voidPayPalREST
				PayPal REST API interface for <void>
		*/
		
		protected function voidPayPalREST($authorization) {
			$response = $this->sendPayPalREST("payments/authorization/$authorization/void","{}");
			if ($response->state == "voided") {
				return $response->id;
			} else {
				$this->Message = $response->message;
				return false;
			}
		}
		
		/*
			Function: voidPayflow
				PayPal Payflow Gateway interface for <void>
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