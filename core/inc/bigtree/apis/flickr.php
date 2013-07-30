<?
	/*
		Class: BigTreeFlickrAPI
	*/

	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));
	
	class BigTreeFlickrAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "http://www.flickr.com/services/oauth/request_token";
		var $EndpointURL = "http://ycpi.api.flickr.com/services/rest";
		var $OAuthVersion = "1.0";
		var $RequestType = "hash";
		var $Scope = "https://www.googleapis.com/auth/youtube";
		var $TokenURL = "http://www.flickr.com/services/oauth/authorize";
		
		/*
			Constructor:
				Sets up the Flickr API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-flickr-api","YouTube API","org.bigtreecms.api.flickr",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/flickr/return/";
		}

		/*
			Function: callUncached
				Overrides BigTreeOAuthAPIBase to always request normal JSON.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			$params["method"] = $endpoint;
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			return parent::callUncached("",$params,$method,$headers);
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			$this->Settings["token_secret"] = "";
			$admin = new BigTreeAdmin;
			$response = $this->callAPI("http://www.flickr.com/services/oauth/request_token","GET",array("oauth_callback" => $this->ReturnURL));
			parse_str($response);
			if ($oauth_callback_confirmed) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				BigTree::redirect("http://www.flickr.com/services/oauth/authorize?perms=delete&oauth_token=".$oauth_token);
			} else {
				$admin->growl($oauth_problem,"Flickr API");
				BigTree::redirect(ADMIN_ROOT."developer/services/flickr/");
			}
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
			$response = $this->callAPI("http://www.flickr.com/services/oauth/access_token","GET",array("oauth_verifier" => $_GET["oauth_verifier"],"oauth_token" => $_GET["oauth_token"]));
			parse_str($response);
			if ($fullname) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				$this->Connected = true;
				return true;
			}
			return false;
		}

		/*
			Function: uploadPhoto
				Uploads a photo to the authenticated user's Flickr account.

			Parameters:
				photo - The file to upload.
				title - A title for the photo (optional).
				description - A description for the photo (optional).
				tags - An array of tags to apply to the photo (optional).
				public - Whether the public can view this photo (optional, defaults to true).
				family - Whether "family" can view this photo (optional, defaults to true).
				friends - Whether "friends" can view this photo (optional, defaults to true).
				safety - Content safety level: 1 for Safe, 2 for Moderate, 3 for Restricted (defaults to Safe)
				type - Content type: 1 for Photo, 2 for Screenshot, 3 for Other (defaults to Photo)
				hidden - Whether to hide from global search results (defaults to false)

			Returns:
				The ID of the photo if successful.
		*/

		function uploadPhoto($photo,$title = "",$description = "",$tags = array(),$public = true,$family = true,$friends = true,$safety = 1,$type = 1,$hidden = false) {
			$xml = $this->callAPI("http://up.flickr.com/services/upload/","POST",
				array("photo" => "@".$photo,"title" => $title,"description" => $description,"tags" => implode(" ",$tags),"is_public" => $public,"is_family" => $family,"is_friends" => $friends,"safety_level" => $safety,"content_type" => $type,"hidden" => ($hidden ? 2 : 1)),
				array("photo")
			);
			$doc = @simplexml_load_string($xml);
			if (isset($doc->photoid)) {
				return strval($doc->photoid);
			}
			return false;
		}
	}
?>