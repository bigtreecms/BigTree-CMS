<?
	/*
		Class: BigTreeSalesforceAPI
	*/
	
	class BigTreeSalesforceAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = false;
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Salesforce API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the Salesforce API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-salesforce-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-salesforce-api", 
					"name" => "Salesforce API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Salesforce";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->redirect_uri = str_replace("http://","https://",ADMIN_ROOT)."developer/services/salesforce/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
				$this->OAuthClient->authorization = "Authorization: Bearer ".$this->Settings["token"];
			}
			
			// Init Client
			$this->OAuthClient->Initialize();

			// Setup Endpoints
			$this->URL = $this->Settings["instance"]."/services/data/v28.0/";
		}
		
		/*
			Function: call
				Calls the Salesforce API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The Salesforce API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$options = array()) {
			global $cms;
			if ($method != "GET") {
				return $this->callUncached($endpoint,$params,$method);				
			}

			if (!$this->Connected) {
				throw new Exception("The Salesforce API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.salesforce",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Salesforce normally.
					return json_decode(json_encode($record));
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.salesforce",$cache_key,$response);
				}
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				return false;
			}
		}

		/*
			Function: callUncached
				Calls the Salesforce API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Salesforce API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$options = array()) {
			if (!$this->Connected) {
				throw new Exception("The Salesforce API is not connected.");
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				return false;
			}
		}

		/*
			Function: getObject
				Returns a Salesforce object for the given name.

			Parameters:
				name - The object's name.

			Returns:
				A BigTreeSalesforceObject object.
		*/

		function getObject($name) {
			$response = $this->call("sobjects/$name/");
			if (!isset($response->objectDescribe)) {
				return false;
			}
			return new BigTreeSalesforceObject($response->objectDescribe,$this);
		}

		/*
			Function: getObjects
				Returns all the available Salesforce objects in your account.

			Returns:
				An array of BigTreeSalesforceObject objects.
		*/

		function getObjects() {
			$response = $this->call("sobjects/");
			if (!isset($response->sobjects)) {
				return false;
			}
			$objects = array();
			foreach ($response->sobjects as $object) {
				$objects[] = new BigTreeSalesforceObject($object,$this);
			}
			return $objects;
		}
	}

	/*
		Class: BigTreeSalesforceObject
	*/

	class BigTreeSalesforceObject {

		/*
			Constructor:
				Creates a new BigTreeSalesforceObject

			Parameters:
				object - Salesforce data
				api - Reference to BigTreeSalesforceAPI class instance
		*/

		function __construct($object,&$api) {
			$this->Activateable = $object->activateable;
			$this->API = $api;
			$this->Createable = $object->createable;
			$this->Deleteable = $object->deleteable;
			$this->Label = $object->label;
			$this->LabelPlural = $object->labelPlural;
			$this->Layoutable = $object->layoutable;
			$this->Mergeable = $object->mergeable;
			$this->Name = $object->name;
			$this->Queryable = $object->queryable;
			$this->Replicateable = $object->replicateable;
			$this->Retrieveable = $object->retrieveable;
			$this->Searchable = $object->searchable;
			$this->Triggerable = $object->triggerable;
			$this->Undeleteable = $object->undeleteable;
			$this->Updateable = $object->updateable;
		}
	}
?>