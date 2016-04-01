<?php
	/*
		Class: BigTree\Facebook\API
			Facebook API class that implements some API calls.
	*/
	
	namespace BigTree\Facebook;
	
	use BigTree\OAuth;
	
	class API extends OAuth {
		
		public $AuthorizeURL = "https://www.facebook.com/dialog/oauth";
		public $EndpointURL = "https://graph.facebook.com/v2.4/";
		public $OAuthVersion = "2.0";
		public $RequestType = "header";
		public $Scope = "";
		public $TokenURL = "https://graph.facebook.com/v2.4/oauth/access_token";
		
		/*
			Constructor:
				Sets up the Facebook API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-facebook-api","Facebook API","org.bigtreecms.api.facebook",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/facebook/return/";

			// Set access scope
			$this->Scope = $this->Settings["scope"];
		}

		/*
			Function: getUser
				Returns a user for the given user ID.
				Returns the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return.

			Returns:
				A BigTree\Facebook\Person object.
		*/

		function getUser($user = "me") {
			$response = $this->call($user);
			
			if (!$response->id) {
				return false;
			}
			
			return new User($response,$this);
		}
		
	}
