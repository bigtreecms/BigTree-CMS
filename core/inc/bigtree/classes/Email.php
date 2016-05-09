<?php
	/*
		Class: BigTree\Email
			Provides an interface for creating an email and sending it.
			You should create an object, set its properties, then call the send method.
	*/

	namespace BigTree;

	use PHPMailer;

	class Email extends BaseObject {

		protected $Service;
		protected $Settings;

		public $BCC = false;
		public $CC = false;
		public $From = "";
		public $Headers = array();
		public $HTML = "";
		public $ReplyTo = "";
		public $Subject = "";
		public $Text = "";
		public $To = "";

		function __construct() {
			$setup = Setting::value("bigtree-internal-email-service");

			// Setting doesn't exist? Create it.
			if ($setup === false) {
				$setting = Setting::create("bigtree-internal-email-service","Email Service","","",array(),"",true,true,true);
				$setting->Value = array("service" => "Local", "settings" => array());
				$setting->save();
			} else {
				$this->Service = $setup["service"];
				$this->Settings = $setup["settings"];
			}

			if (!$this->Service) {
				$this->Service = "Local";
			}
		}

		/*
			Function: send
				Sends the email with the preferred selected email service provider.
		*/

		function send() {
			$provider_string = "BigTree\\EmailService\\".$this->Service;
			$provider = new $provider_string($this->Settings);

			return $provider->send($this);
		}

	}

