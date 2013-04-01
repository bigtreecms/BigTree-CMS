<?
	/*
		Class: BigTreeTwitterAPI
			
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeTwitterAPI {
		
		var $debug = false;
		var $Client;
		var $Connected = false;
		var $URL = "https://api.twitter.com/1.1/";
		
		/*
			Constructor:
				
		*/
		function __construct($debug = false) {
			global $cms,$admin;
			
			$this->debug = $debug;
			$this->settings = $cms->getSetting("bigtree-internal-twitter-api");
			
			$this->Client = new oauth_client_class;
			$this->Client->server = 'Twitter';
			$this->Client->client_id = $this->settings["key"]; 
			$this->Client->client_secret = $this->settings["secret"];
			$this->Client->access_token = $this->settings["token"]; 
			$this->Client->access_token_secret = $this->settings["token_secret"];
			
			$this->Client->redirect_uri = ADMIN_ROOT."developer/services/twitter/return/";
			
			if ($this->settings["key"] && $this->settings["secret"] && $this->settings["token"] && $this->settings["token_secret"]) {
				$this->Connected = true;
			}
			
			$this->max_cache_age = 60 * 60; // 1 hour
			$this->cache_root = SERVER_ROOT . "cache/custom/";
			$this->cache_base = $this->cache_root . "twitter-";
			
			if (!is_dir($this->cache_root)) {
				mkdir($this->cache_root);
				chmod($this->cache_root, 0777);
			}
		}
		
		/*
			get: 
				
		*/
		function get($endpoint = false, $params = array()) {
			if (!$this->Connected || !$endpoint) {
				return false;
			}
			
			//CallAPI($url, $method, $parameters, $options, &$response);
			if ($this->Client->CallAPI($this->URL.$endpoint, 'GET', $params, array('FailOnAccessError' => true), $response)) {
				return $response;
			} else {
				return false;
			}
		}
		
		/*
			getTimeline:
				
		*/
		function getTimeline($user_name = false, $limit = 10) {
			if (!$this->Connected) {
				return false;
			}
			
			$user_name = ($user_name) ? $user_name : $this->settings["user_name"];
			$cache_file = $this->cache_base . $user_name . "-timeline.btx";
			$cache_age = $this->cacheAge($cache_file);
			
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$response = $this->get('statuses/user_timeline', array(
					"screen_name" => $user_name,
					"count" => $limit
				));
				
				$response = json_encode($response);
				$this->cacheData($response, $cache_file);
			} else {
				$response = file_get_contents($cache_file);
			}
			
			return json_decode($response, true);
		}
		
		/*
			search:
				
		*/
		function search($query = false, $limit = 10) {
			if (!$this->Connected) {
				return false;
			}
			
			$cache_file = $this->cache_base . $query . "-search.btx";
			$cache_age = $this->cacheAge($cache_file);
			
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$response = $this->get('search/tweets', array(
					"q" => $query,
					"count" => $limit,
					"result_type" => "recent"
				));
				
				$response = json_encode($response->statuses);
				$this->cacheData($response, $cache_file);
			} else {
				$response = file_get_contents($cache_file);
			}
			
			return json_decode($response, true);
		}
		
		
		/*
			user:
				
		*/
		function user($user_name = false) {
			if (!$this->Connected) {
				return false;
			}
			
			$user_name = ($user_name) ? $user_name : $this->settings["user_name"];
			$cache_file = $this->cache_base . $user_name . "-user.btx";
			$cache_age = $this->cacheAge($cache_file);
			
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$response = $this->get('users/show', array(
					"screen_name" => $user_name
				));
				
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
	}
?>