<?php
	/*
		Class: BigTree\EmailService\Provider
			Provides a base interface for transactional email service providers.
	*/
	
	namespace BigTree\EmailService;
	
	use BigTree\Email;
	
	class Provider
	{
		
		protected $Settings;
		
		public $Error;
		
		/*
			Constructor:
				Sets up the current service settings.
		*/
		
		function __construct(?array $settings = [])
		{
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
		
		function parseAddress(?string $address = null, bool $use_default = true): array
		{
			$email = $name = "";
			
			if (empty($address) && $use_default) {
				if (isset($_SERVER["HTTP_HOST"])) {
					$domain = str_replace("www.", "", $_SERVER["HTTP_HOST"]);
				} else {
					$domain = str_replace(["http://www.", "https://www.", "http://", "https://"], "", DOMAIN);
				}
				
				$email = "no-reply@$domain";
				$name = "BigTree CMS";
			} else {
				// Parse out from and reply-to names
				$address = trim($address);
				
				if (strpos($address, "<") !== false && substr($address, -1, 1) == ">") {
					$address_pieces = explode("<", $address);
					$name = trim($address_pieces[0]);
					$email = substr($address_pieces[1], 0, -1);
				}
			}
			
			return [$email, $name];
		}
		
		/*
			Function: send
				Sends an HTML email.

			Parameters:
				email - BigTree\Email object

			Returns:
				true if successful
				Sets $this->Error with error response if not successful.
		*/
		
		function send(Email $email): ?bool
		{
			trigger_error(get_class($this)." does not implement ".__METHOD__, E_USER_ERROR);
			
			return null;
		}
		
	}