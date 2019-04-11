<?php
	
	/*
		Class: BigTree\OAuth
			Other OAuth APIs inherit from this class. Implements common call patterns.
	*/
	
	namespace BigTree;
	
	use stdClass;
	
	class OAuth {
		
		public $Active;
		public $AuthorizeURL = "";
		public $Cache = true;
		public $CacheIdentifier = "";
		public $Connected = false;
		public $EndpointURL = "";
		public $Errors = [];
		public $LastCacheKey = false;
		public $OAuthError = false;
		public $OAuthSettings;
		public $OAuthVersion = false;
		public $RequestParameters = [];
		public $RequestType = false;
		public $ReturnURL = "";
		public $Scope = false;
		public $Setting;
		public $Settings = [];
		public $SettingID = false;
		public $SettingNamespace = "";
		public $TokenURL = "";
		
		/*
			Constructor:
				Sets up the API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		function __construct(string $setting_id, string $setting_name, string $cache_id, bool $cache = true) {
			$this->Cache = $cache;
			$this->CacheIdentifier = $cache_id;
			$this->SettingID = $setting_id;
			
			// If we don't have the setting for the API, create it.
			if (!Setting::exists($setting_id)) {
				Setting::create($setting_id, $setting_name, "", "", [], "", "on", "on");
			}
			
			$this->Setting = new Setting($setting_id);
			
			// Emulate old functionality of $this->Settings by making it a reference to the setting value
			$this->Settings = &$this->Setting->Value;
			
			// Prevent fatal error on bad setting data
			if (!is_array($this->Settings)) {
				$this->Settings = [];
			}
			
			// Make sure Settings is an array
			$this->Settings = array_filter((array) $this->Settings);
			
			// Setup dependency table for cache busting
			$this->Settings["hash_table"] = @is_array($this->Settings["hash_table"]) ? $this->Settings["hash_table"] : [];
			
			// Setup proper namespace
			if ($this->SettingNamespace !== "") {
				$this->OAuthSettings = &$this->Settings[$this->SettingNamespace];
			} else {
				$this->OAuthSettings = &$this->Settings;
			}
			
			if ($this->OAuthSettings["key"] && $this->OAuthSettings["secret"] && $this->OAuthSettings["token"]) {
				$this->Connected = true;
				
				// If our token is going to expire in the next 30 minutes, refresh it.
				if ($this->OAuthSettings["expires"] < time() + 1800 && $this->OAuthSettings["expires"]) {
					$this->oAuthRefreshToken();
				}
			}
		}
		
		/*
			Function: cacheBust
				Busts the cache for everything relating to an object.
		*/
		
		function cacheBust(string $id): void {
			if (is_array($this->OAuthSettings["hash_table"][$id])) {
				foreach ($this->OAuthSettings["hash_table"][$id] as $i) {
					SQL::delete("bigtree_caches", [
						"identifier" => $this->CacheIdentifier,
						"key" => $i
					]);
				}
			}
		}
		
		/*
			Function: cachePush
				Pushes a hash onto the cache hash table.
		*/
		
		function cachePush(string $id): void {
			if (!isset($this->OAuthSettings["hash_table"][$id])) {
				$this->OAuthSettings["hash_table"][$id] = [];
			}
			
			if (!in_array($this->LastCacheKey, $this->OAuthSettings["hash_table"][$id])) {
				$this->OAuthSettings["hash_table"][$id][] = $this->LastCacheKey;
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
		
		function call(string $endpoint = "", $params = [], string $method = "GET", array $headers = []): ?stdClass {
			if ($this->Cache) {
				$this->LastCacheKey = md5($endpoint.json_encode($params));
				$record = Cache::get($this->CacheIdentifier, $this->LastCacheKey, 900);
				
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from OAuth normally.
					return json_decode(json_encode($record));
				}
			}
			
			$response = $this->callUncached($endpoint, $params, $method, $headers);
			
			if (!is_null($response)) {
				if ($this->Cache) {
					Cache::put($this->CacheIdentifier, $this->LastCacheKey, $response);
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
		
		protected function callAPI(string $url, string $method = "GET", array $data = [], array $headers = [],
								   array $excluded = []): string {
			// Add OAuth related parameters.
			$oauth = [];
			$get = [];
			
			// If we have to HMAC sign the request
			if ($this->RequestType == "hash" || $this->RequestType == "hash-header") {
				// Extract GET vars from the URL.
				parse_str(parse_url($url, PHP_URL_QUERY), $get);
				
				if (count($get)) {
					$url = substr($url, 0, strpos($url, "?"));
				}
				
				$oauth["oauth_consumer_key"] = $this->OAuthSettings["key"];
				$oauth["oauth_token"] = $this->OAuthSettings["token"];
				$oauth["oauth_version"] = $this->OAuthVersion;
				$oauth["oauth_nonce"] = md5(uniqid(rand(), true));
				$oauth["oauth_timestamp"] = time();
				$oauth["oauth_signature_method"] = "HMAC-SHA1";
				
				// Merge GET and POST and OAuth
				$mixed = array_merge($get, $data, $oauth);
				
				// Sort keys
				ksort($mixed);
				
				// Create a string for signing
				$string = "";
				
				foreach ($mixed as $key => $val) {
					if (!in_array($key, $excluded)) {
						$string .= "&".rawurlencode($key)."=".rawurlencode($val);
					}
				}
				
				// Signature
				$oauth["oauth_signature"] = base64_encode(hash_hmac("sha1", strtoupper($method)."&".rawurlencode($url)."&".rawurlencode(substr($string, 1)), $this->OAuthSettings["secret"]."&".$this->OAuthSettings["token_secret"], true));
			} elseif ($this->RequestType == "custom") {
				$oauth = $this->RequestParameters;
			} elseif ($this->RequestType == "header") {
				$headers[] = "Authorization: Bearer ".$this->OAuthSettings["token"];
			}
			
			// Build out our new URL with OAuth vars + GET vars we extracted.
			$url .= "?";
			
			foreach ($get as $key => $val) {
				$url .= "$key=".rawurlencode($val)."&";
			}
			
			// If we're using GET or DELETE, append OAuth vars, otherwise add them to the POST data.
			if ($this->RequestType == "hash-header") {
				$oauth_header = [];
				
				foreach ($oauth as $key => $value) {
					$oauth_header[] = $key.'="'.rawurlencode($value).'"';
				}
				
				$headers[] = "Authorization: OAuth ".implode(", ", $oauth_header);
				
				if ($method == "POST") {
					if (is_array($data)) {
						// Make sure we're not posting files first.
						$files_included = false;
						
						foreach ($data as $val) {
							if (substr($val, 0, 1) == "@" && file_exists(substr($val, 1))) {
								$files_included = true;
							}
						}
						
						if (!$files_included) {
							$data_array = $data;
							$data = [];
							
							foreach ($data_array as $key => $val) {
								$data[] = "$key=".rawurlencode($val);
							}
							
							$data = implode("&", $data);
						}
					}
				} else {
					if (is_array($data) && count($data)) {
						$url .= preg_replace("/%5B[0-9]+%5D/simU", "%5B%5D", str_replace("+", "%20", http_build_query($data, "", "&")))."&";
						$data = false;
					}
				}
				
			} elseif (($method == "POST" || $method == "PUT") && is_array($data)) {
				$data = array_merge($oauth, $data);
			} else {
				if (is_array($data) && count($data)) {
					$url .= preg_replace("/%5B[0-9]+%5D/simU", "%5B%5D", str_replace("+", "%20", http_build_query($data, "", "&")))."&";
					$data = false;
				}
				
				foreach ($oauth as $key => $val) {
					$url .= "$key=".rawurlencode($val)."&";
				}
			}
			
			// Trim trailing ? or & from the URL, not that it should matter.
			$url = substr($url, 0, -1);
			
			return cURL::request($url, $data, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers]);
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
		
		function callUncached(string $endpoint = "", $params = [], string $method = "GET", array $headers = []): ?stdClass {
			if (!$this->Connected) {
				trigger_error("This API is not connected.", E_USER_ERROR);
			}
			
			// Some APIs expect us to send a JSON string as the content body instead of POST... and they also want a Content-type header.
			if (is_string($params) && $params) {
				$headers[] = "Content-Type: application/json";
			}
			
			$response = json_decode($this->callAPI($this->EndpointURL.$endpoint, $method, $params, $headers));
			
			if (isset($response->error)) {
				if (is_array($response->error->errors)) {
					foreach ($response->error->errors as $error) {
						$this->Errors[] = $error;
					}
				} else {
					$this->Errors[] = $response->error;
				}
				
				return null;
			} else {
				return $response;
			}
		}
		
		/*
			Function: disconnect
				Removes saved API information.
		*/
		
		function disconnect(): void {
			SQL::delete("bigtree_caches", ["identifier" => $this->CacheIdentifier]);
			SQL::delete("bigtree_settings", $this->SettingID);
		}
		
		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/
		
		function oAuthRedirect(): void {
			header("Location: ".$this->AuthorizeURL.
				   "?client_id=".urlencode($this->OAuthSettings["key"]).
				   "&redirect_uri=".urlencode($this->ReturnURL).
				   "&response_type=code".
				   "&scope=".urlencode($this->Scope).
				   "&approval_prompt=force".
				   "&access_type=offline");
			die();
		}
		
		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/
		
		function oAuthRefreshToken(): void {
			$response = json_decode(cURL::request($this->TokenURL, [
				"client_id" => $this->OAuthSettings["key"],
				"client_secret" => $this->OAuthSettings["secret"],
				"refresh_token" => $this->OAuthSettings["refresh_token"],
				"grant_type" => "refresh_token"
			]));
			
			if ($response->access_token) {
				$this->OAuthSettings["token"] = $response->access_token;
				$this->OAuthSettings["expires"] = strtotime("+".$response->expires_in." seconds");
			}
		}
		
		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
		*/
		
		function oAuthSetToken(string $code): ?stdClass {
			$response = json_decode(cURL::request($this->TokenURL, [
				"code" => $code,
				"client_id" => $this->OAuthSettings["key"],
				"client_secret" => $this->OAuthSettings["secret"],
				"redirect_uri" => $this->ReturnURL,
				"grant_type" => "authorization_code"
			]));
			
			if ($response->error) {
				$this->OAuthError = $response->error;
				
				return null;
			}
			
			// Update Token information and save it back.
			$this->OAuthSettings["token"] = $response->access_token;
			$this->OAuthSettings["refresh_token"] = $response->refresh_token;
			$this->OAuthSettings["expires"] = $response->expires_in ? strtotime("+".$response->expires_in." seconds") : false;
			
			$this->Active = true;
			$this->Setting->save();
			
			$this->Connected = true;
			
			return $response;
		}
	}
	