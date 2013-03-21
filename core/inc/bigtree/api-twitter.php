<?
	/*
		Class: BigTreeTwitterAPI
			
	*/
	
	class BigTreeTwitterAPI {
		
		var $debug = false;
		var $Client;
		var $Connected = false;
		
		/*
			Constructor:
				
		*/
		function __construct($debug = false) {
			global $cms,$admin;
			
			include BigTree::path("inc/lib/twitter/twitteroauth.php");
			
			$this->debug = $debug;
			$this->settings = $cms->getSetting("bigtree-internal-twitter-api");
			
			if ($this->settings["key"] && $this->settings["secret"] && $this->settings["token"] && $this->settings["token_secret"]) {
				$this->Connected = true;
				$this->Client = new TwitterOAuth($this->settings["key"], $this->settings["secret"], $this->settings["token"], $this->settings["token_secret"]);
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
			return $this->Client->get($endpoint, $params);
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