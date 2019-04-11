<?php
	/*
		Class: BigTreeSalesforceAPI
			Salesforce API class that implements a BigTree-esque module API over Salesforce.
	*/
	
	namespace BigTree\Salesforce;
	
	use BigTree\cURL;
	use BigTree\OAuth;
	
	class API extends OAuth
	{
		
		public $AuthorizeURL = "https://login.salesforce.com/services/oauth2/authorize";
		public $EndpointURL = "";
		public $InstanceURL = "";
		public $OAuthVersion = "2.0";
		public $RequestType = "header";
		public $TokenURL = "https://login.salesforce.com/services/oauth2/token";
		
		/*
			Constructor:
				Sets up the Salesforce API connectionpublic
			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		public function __construct(bool $cache = true)
		{
			parent::__construct("bigtree-internal-salesforce-api", "Salesforce API", "org.bigtreecms.api.salesforce", $cache);
			
			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/salesforce/return/";
			
			// Change things if we're in the test environment.
			if ($this->Settings["test_environment"]) {
				$this->AuthorizeURL = str_ireplace("login.", "test.", $this->AuthorizeURL);
				$this->TokenURL = str_replace("login.", "test.", $this->TokenURL);
			}
			
			// Get a new access token for this session.
			$this->Connected = false;
			
			if ($this->Settings["refresh_token"]) {
				$response = json_decode(cURL::request($this->TokenURL, [
					"grant_type" => "refresh_token",
					"client_id" => $this->Settings["key"],
					"client_secret" => $this->Settings["secret"],
					"refresh_token" => $this->Settings["refresh_token"]
				]), true);
				
				if ($response["access_token"]) {
					$this->InstanceURL = $response["instance_url"];
					$this->EndpointURL = $this->InstanceURL."/services/data/v28.0/";
					$this->Settings["token"] = $response["access_token"];
					$this->Connected = true;
				}
			}
		}
		
		/*
			Function: getObject
				Returns a Salesforce object for the given name.

			Parameters:
				name - The object's name.

			Returns:
				A BigTree\Salesforce\Object object.
		*/
		
		public function getObject(string $name): ?Object
		{
			$response = $this->call("sobjects/$name/");
			
			if (!isset($response->objectDescribe)) {
				return null;
			}
			
			return new Object($response->objectDescribe, $this);
		}
		
		/*
			Function: getObjects
				Returns all the available Salesforce objects in your account.

			Returns:
				An array of BigTree\Salesforce\Object objects.
		*/
		
		public function getObjects(): ?array
		{
			$response = $this->call("sobjects/");
			
			if (!isset($response->sobjects)) {
				return null;
			}
			
			$objects = [];
			
			foreach ($response->sobjects as $object) {
				$objects[] = new Object($object, $this);
			}
			
			return $objects;
		}
		
	}
