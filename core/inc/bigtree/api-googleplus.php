<?
	/*
		Class: BigTreeGooglePlusAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeGooglePlusAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "https://www.googleapis.com/plus/v1/";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Google+ API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($debug = false) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the Google+ API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-googleplus-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-googleplus-api", 
					"name" => "Google+ API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Google";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/googleplus/return/";
			$this->OAuthClient->scope = "https://www.googleapis.com/auth/plus.me https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();
		}
		
		/*
			Function: callAPI
				Calls the Google+ API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Google+ API endpoint to hit.
				params - The parameters to send to the API (key/value array).

			Returns:
				Information directly from the API.
		*/

		function callAPI($endpoint,$params = array()) {
			if (!$this->Connected) {
				throw new Exception("The Google+ API is not connected.");
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,"GET",$params,array("FailOnAccessError" => true),$response)) {
				return $response;
			} else {
				return false;
			}
		}

		/*
			Function: get
				Calls the OAuth API
		*/

		protected function get($endpoint = false, $params = array()) {
			global $cms;

			if (!$this->Connected) {
				throw new Exception("The Google+ API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.googleplus",$cache_key,900);
				if ($record) {
					return $record;
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,"GET",$params,array("FailOnAccessError" => true),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.googleplus",$cache_key,$response);
				}
				return $response;
			} else {
				return false;
			}
		}
		
		/*
			Function: getActivities
				Return posts
		*/

		function getActivities($user_id = false, $limit = 10, $params = array()) {
			$user_id = $user_id ? $user_id : $this->Settings["user_id"];
			return $this->get("people/$user_id/activities/public",$params);
		}
	}
?>