<?
	/*
		Class: BigTreeInstagramAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeInstagramAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "https://api.instagram.com/v1/";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Twitter API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;
			
			// If we don't have the setting for the Instagram API, create it
			$this->Settings = $cms->getSetting("bigtree-internal-instagram-api");			
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-instagram-api", 
					"name" => "Instagram API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Instagram";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->scope = $this->Settings["scope"] ? $this->Settings["scope"] : "basic";
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/instagram/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();
		}
		
		/*
			Function: callAPI
				Calls the Twitter API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Twitter API endpoint to hit.
				params - The parameters to send to the API (key/value array).

			Returns:
				Information directly from the API.
		*/

		function callAPI($endpoint,$params = array()) {
			if (!$this->Connected) {
				throw new Exception("The Instagram API is not connected.");
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
				throw new Exception("The Instagram API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.instagram",$cache_key,900);
				if ($record) {
					return $record;
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,"GET",$params,array("FailOnAccessError" => true),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.instagram",$cache_key,$response);
				}
				return $response;
			} else {
				return false;
			}
		}
		
		
		/*
			Function: getUserMedia
				Returns photos for a given user ID.

			Parameters:
				user_id - The ID of the user to retrieve media for (defaults to the connected user)
				limit - The number of results to return (defaults to 10)
				params - Additional parameters to pass to the users/{id}/media/recent API call

			Returns:
				An array of results.
		*/

		function getUserMedia($user_id = false, $limit = 10, $params = array()) {
			$user_id = $user_id ? $user_id : $this->Settings["user_id"];
			return $this->get("users/$user_id/media/recent?count=$limit",$params);
		}
		
		/*
			Function: getTaggedMedia
				Returns photos that contain a given tag.

			Parameters:
				tag - The tag to search for
				limit - The number of results to return (defaults to 10)
				params - Additional parameters to pass to the tags/{tag}/media/recent API call

			Returns:
				An array of results.				
		*/

		function getTaggedMedia($tag = false, $limit = 10, $params = array()) {
			$tag = (substr($tag,0,1) == "#") ? substr($tag,1) : $tag;
			return $this->get("tags/$tag/media/recent?count=$limit",$params);
		}
	}
?>