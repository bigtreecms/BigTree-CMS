<?
	/*
		Class: BigTreeGoogleAPIBase
			Other Google APIs inherit from this class. Implements common call patterns.
	*/

	require_once(BigTree::path("inc/bigtree/apis/_google.base.php"));

	class BigTreeGoogleAPIBase {

		/*
			Constructor:
				Sets up the Google API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($setting_id,$setting_name,$cache_id,$cache = true) {
			global $cms;
			$this->Cache = $cache;
			$this->CacheIdentifier = $cache_id;

			// If we don't have the setting for the API, create it.
			$this->Settings = $cms->getSetting($setting_id);
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => $setting_id, 
					"name" => $setting_name, 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}

			// If our token is going to expire in the next 30 minutes, refresh it.
			if ($this->Settings["expires"] < time() + 1800) {
				$response = json_decode(BigTree::cURL("https://accounts.google.com/o/oauth2/token",array(
					"client_id" => $this->Settings["key"],
					"client_secret" => $this->Settings["secret"],
					"refresh_token" => $this->Settings["refresh_token"],
					"grant_type" => "refresh_token"
				)));
				if ($response->access_token) {
					$this->Settings["token"] = $response->access_token;
					$this->Settings["expires"] = strtotime("+".$response->expires_in." seconds");
					$admin = new BigTreeAdmin;
					$admin->updateSettingValue($setting_id,$this->Settings);
				}
			}
		}
		
		/*
			Function: call
				Calls the Google API directly with the given API endpoint and parameters.
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
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet($this->CacheIdentifier,$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Google normally.
					return json_decode(json_encode($record));
				}
			}
			// Check again in the cache for this record's ETag

			
			$response = $this->callUncached($endpoint,$params,$method,$headers);
			if ($response !== false) {
				if ($this->Cache) {
					$cms->cachePut($this->CacheIdentifier,$cache_key,$response);
				}
			}
			return $response;
		}

		/*
			Function: callUncached
				Calls the Google API directly with the given API endpoint and parameters.
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

			$endpoint .= (strpos($endpoint,"?") !== false) ? "&access_token=".urlencode($this->Settings["token"]) : "?access_token=".urlencode($this->Settings["token"]);
		
			// Build out GET vars if we're using GET.
			if ($method == "GET" && count($params)) {
				foreach ($params as $key => $val) {
					$endpoint .= "&$key=".urlencode($val);
				}
				// Don't send them as POST content
				$params = array();
			}
			// Send JSON headers if this is a JSON string
			if (is_string($params) && $params) {
				$headers[] = "Content-type: application/json";
			}
			$response = json_decode(BigTree::cURL($this->URL.$endpoint,$params,array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers)));
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
	}

	/*
		Class: BigTreeGoogleResultSet
	*/

	class BigTreeGoogleResultSet {

		/*
			Constructor:
				Creates a result set of Google data.

			Parameters:
				api - An instance of your Google-related API class.
				last_call - Method called on the API class.
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$data,$results) {
			$this->API = $api;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->NextPageToken = $data->nextPageToken;
			$this->PreviousPageToken = $data->prevPageToken;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Calls the previous method and gets the next page of results.

			Returns:
				A BigTreeGoogleResultSet or false if there is not another page.
		*/

		function nextPage() {
			if ($this->NextPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->NextPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}

		/*
			Function: previousPage
				Calls the previous method and gets the previous page of results.

			Returns:
				A BigTreeGoogleResultSet or false if there is not a previous page.
		*/

		function previousPage() {
			if ($this->PreviousPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->PreviousPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
			}
			return false;
		}
	}
?>