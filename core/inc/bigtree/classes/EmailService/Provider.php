<?php
	namespace BigTree\EmailService;

	/*
		Class: BigTree\EmailService\Provider
			Provides a base interface for transactional email service providers.
	*/
	
	class Provider {

		public $Error;

		/*
			Constructor:
				Sets up the currently configured service.
		*/

		function __construct() {
			$setup = Setting::value("bigtree-internal-email-service");

			// Setting doesn't exist? Create it.
			if ($setup === false) {
				$setting = Setting::create("bigtree-internal-email-service","Email Service","","",array(),"",true,true,true);
				$setting->Value = array("service" => "", "settings" => array());
				$setting->save();

				$this->Settings = array();
			} else {
				$this->Settings = $setup["settings"];
			}
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