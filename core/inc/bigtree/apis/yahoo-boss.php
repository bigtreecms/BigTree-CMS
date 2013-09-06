<?
	/*
		Class: BigTreeYahooBOSSAPI
			Yahoo BOSS API implementation. Currently only supports Geocoding.
	*/

	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));

	class BigTreeYahooBOSSAPI extends BigTreeOAuthAPIBase {

		var $EndpointURL = "http://yboss.yahooapis.com/";
		var $OAuthVersion = "1.0";
		var $RequestType = "hash";
		var $TokenURL = "https://api.instagram.com/oauth/access_token";
		
		/*
			Constructor:
				Sets up the Yahoo BOSS API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-yahoo-boss-api","Yahoo BOSS API","org.bigtreecms.api.yahooboss",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/geocoding/yahoo-boss/return/";
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			$this->Settings["token_secret"] = "";
			$response = $this->callAPI("https://api.login.yahoo.com/oauth/v2/get_request_token","GET",array("oauth_callback" => $this->ReturnURL));
			parse_str($response);
			if ($oauth_callback_confirmed != "true") {
				global $admin;
				$admin->growl("Yahoo BOSS API","Consumer Key or Secret invalid.","error");
				BigTree::redirect(ADMIN_ROOT."developer/geocoding/yahoo-boss/");
			}
			$this->Settings["token_secret"] = $oauth_token_secret;
			BigTree::redirect("https://api.login.yahoo.com/oauth/v2/request_auth?oauth_token=$oauth_token");
		}

		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
		*/

		function oAuthSetToken($code) {
			// Token has to be set first since the signing mechanism assumes it's in Settings
			$this->Settings["token"] = $_GET["oauth_token"];
			$response = $this->callAPI("https://api.login.yahoo.com/oauth/v2/get_token","POST",array("oauth_verifier" => $_GET["oauth_verifier"]));
			parse_str($response);
			
			if (!$oauth_token) {
				$this->OAuthError = "Authentication failed.";
				return false;
			}

			// Update Token information and save it back.
			$this->Settings["token"] = $oauth_token;
			$this->Settings["token_secret"] = $oauth_token_secret;
			$this->Settings["session_handle"] = $oauth_session_handle;
			$this->Settings["expires"] = strtotime("+ ".$oauth_expires_in."seconds");
			$this->Connected = true;
			return true;
		}

		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/

		function oAuthRefreshToken() {
			$response = $this->callAPI("https://api.login.yahoo.com/oauth/v2/get_token","POST",array("oauth_session_handle" => $this->Settings["session_handle"]));
			parse_str($response);
			
			// Failed to get a new token
			if (!$oauth_token) {
				$this->Connected = false;
				return false;
			}

			// Update Token information and save it back.
			$this->Settings["token"] = $oauth_token;
			$this->Settings["token_secret"] = $oauth_token_secret;
			$this->Settings["session_handle"] = $oauth_session_handle;
			$this->Settings["expires"] = strtotime("+ ".$oauth_expires_in."seconds");
		}
	}
?>