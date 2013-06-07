<?
	/*
		Class: BigTreeTwitterAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeTwitterAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "https://api.twitter.com/1.1/";
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

			// If we don't have the setting for the Twitter API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-twitter-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-twitter-api", 
					"name" => "Twitter API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Twitter";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->access_token_secret = $this->Settings["token_secret"];
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/twitter/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"] && $this->Settings["token_secret"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();

			$this->Cache = $cache;
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
				throw Exception("The Twitter API is not connected.");
			}

			if ($this->Client->CallAPI($this->URL.$endpoint.".json","GET",$params,array("FailOnAccessError" => true),$response)) {
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
				throw Exception("The Twitter API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.twitter",$cache_key,900);
				if ($record) {
					return $record;
				}
			}
			
			if ($this->Client->CallAPI($this->URL.$endpoint.".json","GET",$params,array("FailOnAccessError" => true),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.twitter",$cache_key,$response);
				}
				return $response;
			} else {
				return false;
			}
		}

		/*
			Function: getSearchResults 
				Return a list of recent tweets that match the given query.

			Parameters:
				query - A string to query for
				limit - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the search/tweets API call.

			Returns:
				An array of tweets.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/search/tweets
		*/

		function getSearchResults($query = false, $limit = 10, $params = array()) {
			return $this->get("search/tweets",array_merge($params,array("q" => $query,"count" => $limit,"result_type" => "recent")));
		}
	
		/*
			Function: getTimeline
				Returns recent tweets from the given user's timeline.
				If no user is provided the connected user's timeline will be used.

			Parameters:
				user_name - The user to retrieve tweets for 
				limit - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				An array of tweets.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		*/

		function getTimeline($user_name = false, $limit = 10, $params = array()) {
			$user_name = $user_name ? $user_name : $this->Settings["user_name"];
			return $this->get("statuses/user_timeline",array_merge($params,array("screen_name" => $user_name,"count" => $limit)));
		}
		
		/*
			Function: getUserInfo 
				Return information about a given Twitter username.

			Parameters:
				user_name - The "screen name" of the Twitter user to retrieve information for (defaults to the connected user)
				params - Additional parameters (key/value array) to pass to the users/show API call.

			Returns:
				An array of user information.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/users/show
		*/

		function getUserInfo($user_name = false, $params = array()) {
			$user_name = $user_name ? $user_name : $this->Settings["user_name"];
			return $this->get("users/show",array_merge($params,array("screen_name" => $user_name)));
		}
	}
?>