<?
	/*
		Class: BigTreeInstagramAPI
			
	*/
	
	class BigTreeInstagramAPI {
		
		var $debug = false;
		var $Client;
		var $Connected = false;
		
		/*
			Constructor:
				
		*/
		function __construct($debug = false) {
			global $cms,$admin;
			
			include BigTree::path("inc/lib/instagram/instagram.class.php");
			
			$this->debug = $debug;
			$this->settings = $cms->getSetting("bigtree-internal-instagram-api");
			
			if ($this->settings["id"] && $this->settings["secret"] && $this->settings["token"]) {
				$this->Connected = true;
				$this->Client = new Instagram(array(
					"apiKey" => $this->settings["id"], 
					"apiSecret" => $this->settings["secret"]
				));
				$this->Client->setAccessToken($this->settings["token"]);
			}
			
			$this->max_cache_age = 60 * 60; // 1 hour
			$this->cache_root = SERVER_ROOT . "cache/custom/";
			$this->cache_base = $this->cache_root . "instagram-";
			
			if (!is_dir($this->cache_root)) {
				mkdir($this->cache_root);
				chmod($this->cache_root, 0777);
			}
		}
		
		/*
			getPhotos:
				
		*/
		function getPhotos($user_id = false, $limit = 10) {
			if (!$this->Connected) {
				return false;
			}
			
			$user_id = ($user_id) ? $user_id : $this->settings["user_id"];
			$cache_file = $this->cache_base . $user_id . "-photos.btx";
			$cache_age = $this->cacheAge($cache_file);
			
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$response = $this->Client->getUserMedia($user_id, $limit);
				
				if ($response->meta->code == 200) {
					$response = json_encode($response->data);
					$this->cacheData($response, $cache_file);
					
				}
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