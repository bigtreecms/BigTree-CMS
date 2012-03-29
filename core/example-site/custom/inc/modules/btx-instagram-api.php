<?
	/*
		Class: BTXInstagramAPI
			Instagram API class.
			Requires: BTXCacheableModule
	*/
	
	class BTXInstagramAPI extends BTXCacheableModule {
		
		var $version = "0.1";
		
		/*
			Constructor
				Pass $debug as true to bypass cache.
		*/
		public function __construct($debug = false) {
			global $cms;
			
			$this->max_cache_age = 60 * 5; // 5 mins
			$this->cache_prefix = "btx-instagram-api";
			
			parent::__construct($debug);
			
			$this->client_id = $cms->getSetting("btx-instagram-client-id");
		}
		
		/*
			Function: clearClientID
				Clears the Instgram client ID.
		*/
		public function clearClientID($clientID) {
			sqlquery("DELETE FROM bigtree_settings WHERE id = 'btx-instagram-client-id'");
			$this->clearCache();
		}
		
		/*
			Function: searchTag
				Searches Instagram for a given tag and returns the results.
		*/
		public function searchTag($tag = false, $count = false) {
			if (!$tag || !$this->client_id) {
				return false;
			}
			$tag = str_ireplace(" ", "", $tag);
			$curl_url = "https://api.instagram.com/v1/tags/" . $tag . "/media/recent?client_id=" . $this->client_id;
			if ($count) {
				$curl_url .= "&count=" . $count;
			}
			$cache_file = $this->cache_base . "-" . md5($curl_url) . ".btc";
			return $this->cacheCurl($curl_url, $cache_file);
		}
		
		/*
			Function: setClientID
				Sets the Instagram client ID.
				Must be called with $admin instanciated to BigTreeAdmin.
		*/
		public function setClientID($clientID) {
			global $admin; 
			
			if (!$clientID) {
				return false;
			}
			
			// Check to see if this client ID is going to give us an error.
			$this->client_id = $clientID;
			$response = $this->searchTag("test");
			if ($response["meta"]["error_type"]) {
				return false;
			}
			
			sqlquery("DELETE FROM bigtree_settings WHERE id = 'btx-instagram-client-id'");
			$setting = array(
				"id" => "btx-instagram-client-id",
				"title" => "BTX Instagram Client ID",
				"type" => "text",
				"encrypted" => "",
				"system" => "on"
			);
			$admin->createSetting($setting);
			$admin->updateSettingValue("btx-instagram-client-id", $clientID);
			
			$this->clearCache();
			
			return true;
		}
	}
?>