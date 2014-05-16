<?
	/*
		Class: BigTreeOAuthAPIBase
			Other OAuth APIs inherit from this class. Implements common call patterns.
	*/

	class BigTreeOAuthAPIBase {

		var $Cache = true;
		var $CacheIdentifier = "";
		var $Connected = false;
		var $Errors = array();
		var $Settings = array();
		var $SettingID = false;

		/*
			Constructor:
				Sets up the API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($setting_id,$setting_name,$cache_id,$cache = true) {
			global $cms;
			$this->Cache = $cache;
			$this->CacheIdentifier = $cache_id;

			// If we don't have the setting for the API, create it.
			$this->Settings = &$cms->autoSaveSetting($setting_id,false);
			$this->SettingID = $setting_id;

			// Setup dependency table for cache busting
			$this->Settings["hash_table"] = is_array($this->Settings["hash_table"]) ? $this->Settings["hash_table"] : array();
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;

				// If our token is going to expire in the next 30 minutes, refresh it.
				if ($this->Settings["expires"] < time() + 1800 && $this->Settings["expires"]) {
					$this->oAuthRefreshToken();
				}
			}
		}

		/*
			Function: cacheBust
				Busts the cache for everything relating to an object.
		*/

		function cacheBust($id) {
			if (is_array($this->Settings["hash_table"][$id])) {
				foreach ($this->Settings["hash_table"][$id] as $i) {
					sqlquery("DELETE FROM bigtree_caches WHERE `identifier` = '".sqlescape($this->CacheIdentifier)."' AND `key` = '".sqlescape($i)."'");
				}
			}
		}

		/*
			Function: cachePush
				Pushes a hash onto the cache hash table.
		*/

		function cachePush($id) {
			if (!isset($this->Settings["hash_table"][$id])) {
				$this->Settings["hash_table"][$id] = array();
			}
			if (!in_array($this->LastCacheKey,$this->Settings["hash_table"][$id])) {
				$this->Settings["hash_table"][$id][] = $this->LastCacheKey;
			}
		}
		
		/*
			Function: call
				Calls the OAuth API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				headers - Additional headers to send.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$headers = array()) {
			global $cms;
			
			if ($this->Cache) {
				$this->LastCacheKey = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet($this->CacheIdentifier,$this->LastCacheKey,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from OAuth normally.
					return json_decode(json_encode($record));
				}
			}
			
			$response = $this->callUncached($endpoint,$params,$method,$headers);
			if ($response !== false) {
				if ($this->Cache) {
					$cms->cachePut($this->CacheIdentifier,$this->LastCacheKey,$response);
				}
			}
			return $response;
		}

		/*
			Function: callAPI
				Calls the API via cURL/OAuth

			Parameters:
				url - The URL to hit
				method - The HTTP method to use
				data - POST vars / body
				headers - Additional headers to send
				excluded - Keys that are excluded from HMAC hashing

			Returns:
				Response data.
		*/

		protected function callAPI($url,$method = "GET",$data = array(),$headers = array(),$excluded = array()) {
			// Add OAuth related parameters.
			$oauth = array();
			$get = array();

			// If we have to HMAC sign the request
			if ($this->RequestType == "hash") {
				// Extract GET vars from the URL.
				parse_str(parse_url($url,PHP_URL_QUERY),$get);
				if (count($get)) {
					$url = substr($url,0,strpos($url,"?"));
				}
	
				$oauth["oauth_consumer_key"] = $this->Settings["key"];
				$oauth["oauth_token"] = $this->Settings["token"];
				$oauth["oauth_version"] = $this->OAuthVersion;
				$oauth["oauth_nonce"] = md5(uniqid(rand(), true));
				$oauth["oauth_timestamp"] = time();
				$oauth["oauth_signature_method"] = "HMAC-SHA1";
	
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
			} elseif ($this->RequestType == "custom") {
				$oauth = $this->RequestParameters;
			} elseif ($this->RequestType == "header") {
				$headers[] = "Authorization: Bearer ".$this->Settings["token"];
			}

			// Build out our new URL with OAuth vars + GET vars we extracted.
			$url .= "?";
			foreach ($get as $key => $val) {
				$url .= "$key=".rawurlencode($val)."&";
			}

			// If we're using GET or DELETE, append OAuth vars, otherwise add them to the POST data.
			if (($method == "POST" || $method == "PUT") && is_array($data)) {
				$data = array_merge($oauth,$data);
			} else {
				if (is_array($data) && count($data)) {
					$url .= preg_replace("/%5B[0-9]+%5D/simU","%5B%5D",str_replace("+","%20",http_build_query($data,"","&")))."&";
					$data = false;
				}
				foreach ($oauth as $key => $val) {
					$url .= "$key=".rawurlencode($val)."&";
				}
			}

			// Trim trailing ? or & from the URL, not that it should matter.
			$url = substr($url,0,-1);

			return BigTree::cURL($url,$data,array(CURLOPT_CUSTOMREQUEST => $method,CURLOPT_HTTPHEADER => $headers));
		}

		/*
			Function: callUncached
				Calls the OAuth API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				headers - Additional headers to send.

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			if (!$this->Connected) {
				throw new Exception("This API is not connected.");
			}

			// Some APIs expect us to send a JSON string as the content body instead of POST... and they also want a Content-type header.
			if (is_string($params) && $params) {
				$headers[] = "Content-Type: application/json";
			}

			$response = json_decode($this->callAPI($this->EndpointURL.$endpoint,$method,$params,$headers));
			if (isset($response->error)) {
				if (is_array($response->error->errors)) {
					foreach ($response->error->errors as $error) {
						$this->Errors[] = $error;
					}
				} else {
					$this->Errors[] = $response->error;
				}
				return false;
			} else {
				return $response;
			}
		}

		/*
			Function: disconnect
				Removes saved API information.
		*/

		function disconnect() {
			sqlquery("DELETE FROM bigtree_caches WHERE identifier = '".sqlescape($this->CacheIdentifier)."'");
			sqlquery("DELETE FROM bigtree_settings WHERE id = '".$this->SettingID."'");
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			BigTree::redirect($this->AuthorizeURL.
				"?client_id=".urlencode($this->Settings["key"]).
				"&redirect_uri=".urlencode($this->ReturnURL).
				"&response_type=code".
				"&scope=".urlencode($this->Scope).
				"&approval_prompt=force".
				"&access_type=offline");
		}

		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/

		function oAuthRefreshToken() {
			$response = json_decode(BigTree::cURL($this->TokenURL,array(
				"client_id" => $this->Settings["key"],
				"client_secret" => $this->Settings["secret"],
				"refresh_token" => $this->Settings["refresh_token"],
				"grant_type" => "refresh_token"
			)));
			if ($response->access_token) {
				$this->Settings["token"] = $response->access_token;
				$this->Settings["expires"] = strtotime("+".$response->expires_in." seconds");
			}
		}

		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
		*/

		function oAuthSetToken($code) {
			$response = json_decode(BigTree::cURL($this->TokenURL,array(
				"code" => $code,
				"client_id" => $this->Settings["key"],
				"client_secret" => $this->Settings["secret"],
				"redirect_uri" => $this->ReturnURL,
				"grant_type" => "authorization_code"
			)));

			if ($response->error) {
				$this->OAuthError = $response->error;
				return false;
			}

			// Update Token information and save it back.
			$this->Settings["token"] = $response->access_token;
			$this->Settings["refresh_token"] = $response->refresh_token;
			$this->Settings["expires"] = $response->expires_in ? strtotime("+".$response->expires_in." seconds") : false;

			$this->Connected = true;
			return $response;
		}
	}
?>