<?
	/*
		Class: BTXCacheableModule
			Cachable Module; Simple text based cacheing of cURLed data 
	*/
	
	class BTXCacheableModule extends BigTreeModule {
		
		var $version = "0.1";
		var $curl_options = array();
		
		/*
			Constructor
				Setting $debug to true forces through cache.
		*/
		public function __construct($debug = false) {
			global $server_root;
			
			$this->debug = $debug;
			$this->cache_root = $server_root . "cache/custom/";
			$this->cache_base = $this->cache_root . $this->cache_prefix;
			
			if (!is_dir($this->cache_root)) {
				mkdir($this->cache_root);
				chmod($this->cache_root,0777);
			}
		}
		
		/*
			Function: cacheAge
				Return cache filetime; "0" if file does not exist
		*/
		public function cacheAge($file) {
			return file_exists($file) ? filemtime($file) : 0;
		}
		
		/*
			Function: cacheCurl
				cURL w/ Caching; allows for custom formatter (must be class method)
		*/
		public function cacheCurl($curl_url, $cache_file, $formatter = false, $is_xml = false) {
			$cache_age = $this->cacheAge($cache_file);
			
			if ($cache_age === false || $cache_age < (time() - $this->max_cache_age) || $this->debug) {
				$result = BigTree::curl($curl_url, array(), $this->curl_options);
				if ($is_xml !== false) {
					$result = $this->xmlToArray($result);
				}
				if ($formatter !== false) {
					$result = $this->{$formatter}($result);
				}
				if (is_array($result)) {
					$result = json_encode($result);
				}
				file_put_contents($cache_file, $result);
				chmod($cache_file, 0777);
			} else {
				$result = file_get_contents($cache_file);
			}
			
			return json_decode($result, true);
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
		
		/*
			Function: xmlToArray
				Convert ugly objects to simple arrays; keys to lowercase
		*/ 
		private function xmlToArray($xml, $root = true) {
			if (!is_object($xml)) {
				$xml = simplexml_load_string($xml);
			}
			
			if (!$xml->children()) {
				return (string)$xml;
			}
			
			$array = array();
			foreach ($xml->children() as $element => $node) {
				$totalElement = count($xml->{$element});
		 		$key = strtolower($element);
		 		
				if (!isset($array[$element])) {
					$array[$key] = "";
				}
		 		
				// Has attributes
				if ($attributes = $node->attributes()) {
					$data = array(
						'attributes' => array(),
						'value' => (count($node) > 0) ? $this->xmlToArray($node, false) : (string)$node
					);
		 			
					foreach ($attributes as $attr => $value) {
						$data['attributes'][strtolower($attr)] = (string)$value;
					}
		 			
					if ($totalElement > 1) {
						$array[$key][] = $data;
					} else {
						$array[$key] = $data;
					}
		 			
				// Just a value
				} else {
					if ($totalElement > 1) {
						$array[$key][] = $this->xmlToArray($node, false);
					} else {
						$array[$key] = $this->xmlToArray($node, false);
					}
				}
			}
		 	
			if ($root) {
				return array($xml->getName() => $array);
			} else {
				return $array;
			}
		}
	}
?>