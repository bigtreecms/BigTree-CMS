<?
	/*
		Class: BigTreeTwitterAPI
			
	*/
	
	class BigTreeTwitterAPI {
		
		var $Client;
		var $Connected = false;
		
		/*
			Constructor:
				
		*/
		
		function __construct() {
			global $cms,$admin;
			
			// Setup Google Analytics API info.
			include BigTree::path("inc/lib/twitter/twitteroauth.php");
			
			$this->settings = $cms->getSetting("bigtree-internal-twitter-api");
			
			if ($this->settings["key"] && $this->settings["secret"] && $this->settings["token"] && $this->settings["token_secret"]) {
				$this->Connected = true;
				$this->Client = new TwitterOAuth($this->settings["key"], $this->settings["secret"], $this->settings["token"], $this->settings["token_secret"]);
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
		
		function getTimeline($username = false, $limit = 10) {
			if (!$this->Connected) {
				return false;
			}
			
			return $this->get('statuses/user_timeline', array(
				"screen_name" => ($username) ? $username : $this->settings["username"],
				"count" => $limit
			));
		}
	}
?>