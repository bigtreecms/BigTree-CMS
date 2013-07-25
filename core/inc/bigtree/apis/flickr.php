<?
	/*
		Class: BigTreeFlickrAPI
	*/
	
	class BigTreeFlickrAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "http://ycpi.api.flickr.com/services/rest";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Flickr API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
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
			Function: call
				Calls the Flickr API directly with the given API parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($params = array(),$method = "GET",$options = array()) {
			global $cms;
			$params["key"] = $this->Settings["token"];
			if ($method != "GET") {
				return $this->callUncached($params,$method);				
			}

			if (!$this->Connected) {
				throw new Exception("The Flickr API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5(json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.flickr",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Flickr normally.
					return json_decode(json_encode($record));
				}
			}
			
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			if ($this->OAuthClient->CallAPI($this->URL,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.flickr",$cache_key,$response);
				}
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				return false;
			}
		}

		/*
			Function: callUncached
				Calls the Flickr API directly with the given API parameters.
				Does not cache information.

			Parameters:
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API.
		*/

		function callUncached($params = array(),$method = "GET",$options = array()) {
			if (!$this->Connected) {
				throw new Exception("The Flickr API is not connected.");
			}
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			if ($this->OAuthClient->CallAPI($this->URL,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error);
				return false;
			}
		}

		/*
			Function: callAPI
				Calls the API via cURL/OAuth

			Parameters:
				url - The URL to hit
				method - The HTTP method to use
				data - POST vars / body
				excluded - Keys that are excluded from HMAC hashing

			Returns:
				Response data.
		*/

		function callAPI($url,$method = "GET",$data = array(),$excluded = array()) {
			// Extract GET vars from the URL.
			parse_str(parse_url($url,PHP_URL_QUERY),$get);
			if (count($get)) {
				$url = substr($url,0,strpos($url,"?"));
			}

			// Add OAuth related parameters.
			$oauth = array();
			$oauth["oauth_consumer_key"] = $this->Settings["key"];
			$oauth["oauth_nonce"] = uniqid();
			$oauth["oauth_signature_method"] = "HMAC-SHA1";
			$oauth["oauth_timestamp"] = time();
			$oauth["oauth_version"] = "1.0";
			$oauth["oauth_token"] = $this->Settings["token"];

			// Merge GET and POST and OAuth
			$mixed = array_merge($get,$data,$oauth);
			// Sort keys
			ksort($mixed);
			// Create a string for signing
			$string = "";
			foreach ($mixed as $key => $val) {
				if (!in_array($key,$excluded)) {
					$string .= "&".rawurlencode($key)."=".rawurlencode($val);
				}
			}

			// Signature
			$oauth["oauth_signature"] = base64_encode(hash_hmac("sha1",strtoupper($method)."&".rawurlencode($url)."&".rawurlencode(substr($string,1)),$this->Settings["secret"]."&".$this->Settings["token_secret"],true));
			
			// Build out our new URL with OAuth vars + GET vars we extracted.
			$url .= "?";
			foreach ($get as $key => $val) {
				$url .= "$key=".rawurlencode($val)."&";
			}

			// If we're using GET or DELETE, append OAuth vars, otherwise add them to the POST data.
			if (($method == "POST" || $method == "PUT") && is_array($data)) {
				$data = array_merge($oauth,$data);
			} else {
				foreach ($oauth as $key => $val) {
					$url .= "$key=$val&";
				}
			}

			// Trim trailing ? or & from the URL, not that it should matter.
			$url = substr($url,0,-1);
			
			return BigTree::cURL($url,$data,array(CURLOPT_CUSTOMREQUEST => $method));
		}

		/*
			Function: uploadPhoto
				Uploads a photo to the authenticated user's Flickr account.

			Parameters:
				photo - The file to upload.
				title - A title for the photo (optional).
				description - A description for the photo (optional).
				tags - An array of tags to apply to the photo (optional).
				public - Whether the public can view this photo (optional, defaults to true).
				family - Whether "family" can view this photo (optional, defaults to true).
				friends - Whether "friends" can view this photo (optional, defaults to true).
				safety - Content safety level: 1 for Safe, 2 for Moderate, 3 for Restricted (defaults to Safe)
				type - Content type: 1 for Photo, 2 for Screenshot, 3 for Other (defaults to Photo)
				hidden - Whether to hide from global search results (defaults to false)

			Returns:
				The ID of the photo if successful.
		*/

		function uploadPhoto($photo,$title = "",$description = "",$tags = array(),$public = true,$family = true,$friends = true,$safety = 1,$type = 1,$hidden = false) {
			$xml = $this->callAPI("http://up.flickr.com/services/upload/","POST",
				array("photo" => "@".$photo,"title" => $title,"description" => $description,"tags" => implode(" ",$tags),"is_public" => $public,"is_family" => $family,"is_friends" => $friends,"safety_level" => $safety,"content_type" => $type,"hidden" => ($hidden ? 2 : 1)),
				array("photo")
			);
			$doc = @simplexml_load_string($xml);
			if (isset($doc->photoid)) {
				return strval($doc->photoid);
			}
			return false;
		}
	}
?>