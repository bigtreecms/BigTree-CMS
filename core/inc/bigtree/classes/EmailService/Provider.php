<?php
	/*
		Class: BigTree\EmailService\Provider
			Provides a base interface for transactional email service providers.
	*/
	
	namespace BigTree\EmailService;
	
	class Provider {

		protected $Settings;

		public $Error;

		/*
			Constructor:
				Sets up the current service settings.
		*/

		function __construct($settings) {
			$this->Settings = $settings;
		}

		/*
			Function: parseAddress
				Returns a proper address and name if the user doesn't provide one or provides a combined name/email.

			Parameters:
				address - User submitted email/name combo
				use_default - Use the default no-reply (defaults to true)

			Returns:
				Properly formatted name & email as an array
		*/

		function parseAddress($address, $use_default = true) {
			$email = $name = "";

			if (!$address && $use_default) {
				$email = "no-reply@".(isset($_SERVER["HTTP_HOST"]) ? str_replace("www.","",$_SERVER["HTTP_HOST"]) : str_replace(array("http://www.","https://www.","http://","https://"),"",DOMAIN));
				$name = "BigTree CMS";
			} else {
				// Parse out from and reply-to names
				$name = false;
				$address = trim($address);

				if (strpos($address,"<") !== false && substr($address,-1,1) == ">") {
					$address_pieces = explode("<",$address);
					$name = trim($address_pieces[0]);
					$email = substr($address_pieces[1],0,-1);
				}
			}

			return array($email,$name);
		}

		/*
			Function: send
				Sends an HTML email.

			Parameters:
				subject - Email subject
				body - HTML email body
				to - Email address to send to (single address as a string or an array of email addresses)
				from_email - From email address (optional, defaults to no-reply@domain.com where domain.com is the domain of the server/site)
				from_name - From name (optional, defaults to BigTree CMS if from_email isn't set)
				reply_to - Reply-to email address (optional)
				text - Regular text body (optional)

			Returns:
				true if successful
				Sets $this->Error with error response if not successful.
		*/

		function send($subject,$body,$to,$from_email = false,$from_name = false,$reply_to = false,$text = "") {
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
		}

	}