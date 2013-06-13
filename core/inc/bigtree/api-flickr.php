<?
	/*
		Class: BigTreeFlickrAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeFlickrAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "http://api.flickr.com/services/rest/";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Flickr API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($debug = false) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the Flickr API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-flickr-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-flickr-api", 
					"name" => "Flickr API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}

			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Flickr";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"];
			$this->OAuthClient->access_token_secret = $this->Settings["token_secret"];
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/flickr/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();
		}
		
		/*
			Function: callAPI
				Calls the Flickr API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				params - The parameters to send to the API (key/value array).

			Returns:
				Information directly from the API.
		*/

		function callAPI($params = array()) {
			if (!$this->Connected) {
				throw new Exception("The Flickr API is not connected.");
			}

			// Make it JSON!
			$params = array_merge($params,array("format" => "json","nojsoncallback" => true));

			if ($this->OAuthClient->CallAPI($this->URL,"GET",$params,array("FailOnAccessError" => true),$response)) {
				return $response;
			} else {
				return false;
			}
		}

		/*
			Function: get
				Calls the OAuth API
		*/

		protected function get($params = array()) {
			global $cms;

			if (!$this->Connected) {
				throw new Exception("The Flickr API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.flickr",$cache_key,900);
				if ($record) {
					return $record;
				}
			}
			
			// Make it JSON!
			$params = array_merge($params,array("format" => "json","nojsoncallback" => true));

			if ($this->OAuthClient->CallAPI($this->URL,"GET",$params,array("FailOnAccessError" => true),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.flickr",$cache_key,$response);
				}
				return $response;
			} else {
				return false;
			}
		}
		
		
		/*
			Function: getImages
				Return images
		*/
		function getImages($user_id = false, $limit = 10, $params = array()) {
			$user_id = ($user_id) ? $user_id : $this->settings["user_id"];
			$cache_file = $this->cache_base . $user_id . "-images.btx";
			
			$params = array_merge($params, array(
				"method" => "flickr.people.getPhotos",
				"user_id" => $user_id,
				"extras" => "description,date_upload,date_taken,"
			));
			
			return $this->getCached("", $params, $cache_file);
		}
		
		
		/*
			Function: getUserId
				Return user id for username
		*/
		function getUserId($user_name = false, $limit = 10, $params = array()) {
			if (!$user_name) {
				return false;
			}
			
			$params = array_merge($params, array(
				"method" => "flickr.people.findByUsername",
				"username" => $user_name
			));
			
			return $this->get("", $params);
		}
		
		
		// FORMAT AN IMAGE URL
		static function imageURL($data, $size = false) {
			//http://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}_[mstzb].jpg
			return "http://farm" . $data["farm"] . ".staticflickr.com/" . $data["server"] . "/" . $data["id"] . "_" . $data["secret"] . ($size ? "_".$size : "") . ".jpg";
		}
		// FORMAT AN IMAGE LINK
		static function imageLink($user_id = false, $photo_id = false) {
			if (!$user_id || !$photo_id) {
				return false;
			}
			return "http://www.flickr.com/photos/" . $user_id . "/" . $photo_id;
		}
		
	}
?>