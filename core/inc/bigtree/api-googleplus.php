<?
	/*
		Class: BigTreeGooglePlusAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeGooglePlusAPI {
		
		var $debug = false;
		var $Client;
		var $Connected = false;
		var $URL = "https://www.googleapis.com/plus/v1/";
		var $SettingsKey = "bigtree-internal-googleplus-api";
		
		/*
			Constructor:
		*/
		function __construct($debug = false) {
			global $cms,$admin;
			
			$this->debug = $debug;
			$this->settings = $cms->getSetting($this->SettingsKey);
			
			if (!$this->settings) {
				if ($admin) {
					$admin->createSetting(array(
						"id" => $this->SettingsKey, 
						"name" => "Google+ API", 
						"description" => "", 
						"type" => "", 
						"locked" => "on", 
						"module" => "", 
						"encrypted" => "", 
						"system" => "on"
					));
				}
			}
			
			// Build API client
			$this->Client = new oauth_client_class;
			
			$this->Client->server = 'Google';
			$this->Client->offline = true;
			$this->Client->client_id = $this->settings["key"]; 
			$this->Client->client_secret = $this->settings["secret"];
			$this->Client->access_token = $this->settings["token"]; 
			$this->Client->access_token_expiry = $this->settings["token_expiry"];
			$this->Client->refresh_token = $this->settings["refresh_token"];
			
			// Scope
			$this->Client->scope = "https://www.googleapis.com/auth/plus.me https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile";
			//'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile';
			
			$this->Client->redirect_uri = ADMIN_ROOT."developer/services/googleplus/return/";
			
			// Check if we're conected
			if ($this->settings["key"] && $this->settings["secret"] && $this->settings["token"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->Client->Initialize();
			
			// Set cache stuffs
			$this->max_cache_age = 60 * 60; // 1 hour
			$this->cache_root = SERVER_ROOT . "cache/custom/";
			$this->cache_base = $this->cache_root . "googleplus-";
			
			if (!is_dir($this->cache_root)) {
				mkdir($this->cache_root);
				chmod($this->cache_root, 0777);
			}
		}
		
		/*
			Function: saveSettings
				Update API settings
		*/
		function saveSettings() {
			global $admin;
			if ($admin) {
				$admin->updateSettingValue($this->SettingsKey, $this->settings);
			}
		}
		
		/*
			Function: get
				Make API call
		*/
		function get($endpoint = false, $params = array()) {
			if (!$this->Connected || !$endpoint) {
				return false;
			}
			
			if ($this->Client->CallAPI($this->URL.$endpoint, 'GET', $params, array('FailOnAccessError' => true), $response)) {
				return $response;
			} else {
				return false;
			}
		}
		
		
		/*
			Function: getCached
				Return cached version, if it exists
		*/
		function getCached($endpoint = false, $params = array(), $cache_file) {
			if (!$this->Connected || !$endpoint) {
				return false;
			}
			
			$cache_age = $this->cacheAge($cache_file);
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$response = $this->get($endpoint, $params);
				
				$response = json_encode($response);
				$this->cacheData($response, $cache_file);
			} else {
				$response = file_get_contents($cache_file);
			}
			
			return json_decode($response, true);
		}
		
		/*
			Function: cacheAge
				Return cache filetime; "0" if file does not exist
		*/
		public function cacheAge($file) {
			clearstatcache();
			return file_exists($file) ? filemtime($file) : 0;
		}
		
		/*
			Function: cacheData
				Manually cache a data set
		*/
		public function cacheData($data, $cache_file) {
			if (is_array($data)) {
				$data = json_encode($data);
			}
			file_put_contents($cache_file, $data);
			chmod($cache_file, 0777);
		}
		
		/*
			Function: clearCache
				Clear cahced files; empty filename clears all
		*/
		public function clearCache($cache_file = false) {
			if ($cache_file === false) {
				$dir = opendir($this->cache_root);
				while ($file = readdir($dir)) {
					if (substr($file, 0, strlen($this->cache_prefix)) == $this->cache_prefix) {
						unlink($this->cache_root . $file);
					}
				}
			} else {
				unlink($this->cache_root . $cache_file);
			}
		}
		
		
		// SERVICE SPECIFIC -----
		
		
		/*
			Function: getActivities
				Return posts
		*/
		function getActivities($user_id = false, $limit = 10, $params = array()) {
			$user_id = ($user_id) ? $user_id : $this->settings["user_id"];
			$cache_file = $this->cache_base . $user_id . "-timeline.btx";
			
			return $this->getCached("people/".$user_id."/activities/public", $params, $cache_file);
		}
		
		/*
			Function: search 
				Search for tweets and junk
		*/
/*
		function search($query = false, $limit = 10) {
			if (!$this->Connected) {
				return false;
			}
			
			$cache_file = $this->cache_base . $query . "-search.btx";
			
			return $this->getCached('search/tweets', array(
				"q" => $query,
				"count" => $limit,
				"result_type" => "recent"
			), $cache_file);
		}
*/
		
		/*
			Function: user 
				Return user info, including last tweet
		*/
/*
		function user($user_name = false) {
			if (!$this->Connected) {
				return false;
			}
			
			$user_name = ($user_name) ? $user_name : $this->settings["user_name"];
			$cache_file = $this->cache_base . $user_name . "-user.btx";
			
			return $this->getCached('users/show', array(
				"screen_name" => $user_name
			), $cache_file);
		}
*/
	}
?>