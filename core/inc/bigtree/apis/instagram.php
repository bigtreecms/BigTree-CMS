<?php
	/*
		Class: BigTreeInstagramAPI
			Instagram Basic Display API class that implements reading the authenticated user's basic information and recent media posts.
	*/

	require_once SERVER_ROOT."core/inc/bigtree/apis/_oauth.base.php";
	
	class BigTreeInstagramAPI extends BigTreeOAuthAPIBase {

		public $AuthorizeURL = "https://api.instagram.com/oauth/authorize/";
		public $EndpointURL = "https://graph.instagram.com/";
		public $OAuthVersion = "1.0";
		public $RequestType = "custom";
		public $Scope = "user_profile,user_media";
		public $TokenURL = "https://api.instagram.com/oauth/access_token";

		/*
			Constructor:
				Sets up the Instagram API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		public function __construct($cache = true) {
			parent::__construct("bigtree-internal-instagram-api","Instagram API","org.bigtreecms.api.instagram",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/instagram/return/";

			// Just send the request with the secret.
			$this->RequestParameters = array();
			$this->RequestParameters["access_token"] = &$this->Settings["token"];
		}

		/*
			Function: callUncached
				Piggybacks on the base call to provide error checking for Instagram.
		*/

		public function callUncached($endpoint = "", $params = array(), $method = "GET", $headers = array()) {
			$response = parent::callUncached($endpoint,$params,$method,$headers);
			
			if (isset($response->meta->error_message)) {
				$this->Errors[] = $response->meta->error_message;
			
				return false;
			}
			
			return $response;
		}
		
		/*
			Function: getFeed
				Returns the authenticated user's last 25 posts.

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.
		*/

		public function getFeed($after = null) {
			$params = [
				"fields" => "id, caption, media_type, media_url, permalink, thumbnail_url, timestamp, username"
			];
			
			if ($after) {
				$params["after"] = $after;
			}
			
			$response = $this->call("me/media", $params);
			
			if (!isset($response->data)) {
				return false;
			}
			
			$results = [];

			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media, $this);
			}

			return new BigTreeInstagramResultSet($this, "getFeed", [$response->paging->cursors->after], $results);
		}

		/*
			Function: getMedia
				Gets information about a given media ID

			Parameters:
				id - The media ID

			Returns:
				A BigTreeInstagramMedia object.
		*/

		public function getMedia($id) {
			$response = $this->call($id, ["fields" => "id, caption, media_type, media_url, permalink, thumbnail_url, timestamp, username"]);
			
			return $response ? new BigTreeInstagramMedia($response, $this) : false;
		}
		
		/*
			Function: getUser
				Returns information about a given user ID.

			Parameters:
				id - The user ID to look up

			Returns:
				A BigTreeInstagramUser object.
		*/

		public function getUser($id) {
			$response = $this->call($id, ["fields" => "id, username, media_count, account_type"]);
			
			return $response ? new BigTreeInstagramUser($response, $this) : false;
		}

		/*
			Function: getUserMedia
				Returns the most recent 25 posts from a given user ID.

			Parameters:
				id - The user ID to return media for.

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.
		*/

		public function getUserMedia($id, $after = null) {
			$params = [
				"fields" => "id, caption, media_type, media_url, permalink, thumbnail_url, timestamp, username"
			];
			
			if ($after) {
				$params["after"] = $after;
			}
			
			$response = $this->call("$id/media", $params);
			
			if (!isset($response->data)) {
				return false;
			}
			
			$results = [];
			
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media, $this);
			}
			
			return new BigTreeInstagramResultSet($this, "getUserMedia", [$id, $response->paging->cursors->after], $results);
		}
		
		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/
		
		public function oAuthRefreshToken() {
			$response = $this->callUncached("refresh_access_token", ["grant_type" => "ig_refresh_token"]);
			
			if ($response->access_token) {
				$this->Settings["token"] = $response->access_token;
				$this->Settings["expires"] = strtotime("+".$response->expires_in." seconds");
				$this->saveSettings();
			}
		}
		
		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
		*/
		
		public function oAuthSetToken($code) {
			parent::oAuthSetToken($code);
			
			$response = $this->callUncached("access_token", [
				"grant_type" => "ig_exchange_token",
				"client_secret" => $this->Settings["secret"],
				"access_token" => $this->Settings["token"]
			]);
			
			$this->Settings["token"] = $response->access_token;
			$this->Settings["expires"] = $response->expires_in ? strtotime("+".$response->expires_in." seconds") : false;
		}
		
		protected function _deprecated() {
			trigger_error("Method is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
			
			return false;
		}
		
		public function comment() { return $this->_deprecated(); }
		public function deleteComment() { return $this->_deprecated(); }
		public function getComments() { return $this->_deprecated(); }
		public function getFriends() { return $this->_deprecated(); }
		public function getFollowers() { return $this->_deprecated(); }
		public function getFollowRequests() { return $this->_deprecated(); }
		public function getLikedMedia() { return $this->_deprecated(); }
		public function getLikes() { return $this->_deprecated(); }
		public function getLocation() { return $this->_deprecated(); }
		public function getLocationByFoursquareID() { return $this->_deprecated(); }
		public function getLocationByLegacyFoursquareID() { return $this->_deprecated(); }
		public function getLocationMedia() { return $this->_deprecated(); }
		public function getRelationship() { return $this->_deprecated(); }
		public function getTaggedMedia() { return $this->_deprecated(); }
		public function like() { return $this->_deprecated(); }
		public function popularMedia() { return $this->_deprecated(); }
		public function searchLocations() { return $this->_deprecated(); }
		public function searchMedia() { return $this->_deprecated(); }
		public function searchTags() { return $this->_deprecated(); }
		public function searchUsers() { return $this->_deprecated(); }
		public function setRelationship() { return $this->_deprecated(); }
		public function unlike() { return $this->_deprecated(); }
	}

	class BigTreeInstagramComment {
		
		public function __construct() {
			trigger_error("BigTreeInstagramComment is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
		}

		public function delete() {}
		
	}

	class BigTreeInstagramLocation {
		
		public function __construct() {
			trigger_error("BigTreeInstagramLocation is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
		}

		public function getMedia() {}
		
	}

	/*
		Class: BigTreeInstagramMedia
			An Instagram object that contains information about and methods you can perform on media.
	*/

	class BigTreeInstagramMedia {
		protected $API;
		
		public $Caption;
		public $ID;
		public $Parent;
		public $Permalink;
		public $ThumbnailURL;
		public $Timestamp;
		public $Type;
		public $URL;
		public $Username;

		/*
			Constructor:
				Creates a media object from Instagram data.

			Parameters:
				media - Instagram data
		*/

		public function __construct($media, $api) {
			$this->Caption = $media->caption;
			$this->ID = $media->id;
			$this->Permalink = $media->permalink;
			$this->ThumbnailURL = $media->thumbnail_url;
			$this->Timestamp = $media->timestamp;
			$this->Type = $media->media_type;
			$this->URL = $media->media_url;
			$this->Username = $media->username;
			
			if (!empty($media->parent)) {
				$this->Parent = $media->parent;
			}
			
			if ($this->Type == "CAROUSEL_ALBUM") {
				$this->API = $api;
			}
		}
		
		/*
			Function: getAlbumContents
				Returns the other media objects inside an album media type.

			Returns:
				An array of BigTreeInstagramMedia objects.
		*/
		
		public function getAlbumContents() {
			if ($this->Type != "CAROUSEL_ALBUM") {
				return [];
			}
			
			$response = $this->API->call($this->ID."/children", [
				"fields" => "id, media_type, media_url, permalink, thumbnail_url, timestamp, username"
			]);
			
			if (!isset($response->data)) {
				return false;
			}
			
			$results = [];
			
			foreach ($response->data as $media) {
				$media->caption = $this->Caption;
				$media->parent = $this->ID;
				
				$results[] = new BigTreeInstagramMedia($media, $this);
			}
			
			return $results;
		}
		
		protected function _deprecated() {
			trigger_error("Method is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
			
			return false;
		}

		public function comment() { return $this->_deprecated(); }
		public function getComments() { return $this->_deprecated(); }
		public function getLikes() { return $this->_deprecated(); }
		public function getLocation() { return $this->_deprecated(); }
		public function getUser() { return $this->_deprecated(); }
		public function like() { return $this->_deprecated(); }
		public function unlike() { return $this->_deprecated(); }
	}

	/*
		Class: BigTreeInstagramResultSet
			An object that contains multiple results from an Instagram API query.
	*/

	class BigTreeInstagramResultSet {

		/*
			Constructor:
				Creates a result set of Instagram data.

			Parameters:
				api - An instance of BigTreeInstagramAPI
				last_call - Method called on BigTreeInstagramAPI
				params - The parameters sent to last call
				results - Results to store
		*/

		public function __construct(&$api, $last_call, $params, $results) {
			$this->API = $api;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Calls the previous method again (with modified parameters)

			Returns:
				A BigTreeInstagramResultSet with the next page of results.
		*/

		public function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall), array_values($this->LastParameters));
		}
	}

	class BigTreeInstagramTag {
		
		public function __construct() {
			trigger_error("BigTreeInstagramTag is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
		}

		public function getMedia() {}
		
	}

	/*
		Class: BigTreeInstagramUser
			An Instagram object that contains information about and methods you can perform on a user.
	*/

	class BigTreeInstagramUser {
		protected $API;
		
		public $AccountType;
		public $ID;
		public $MediaCount;
		public $Username;

		/*
			Constructor:
				Creates a user object from Instagram data.

			Parameters:
				user - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		public function __construct($user, &$api) {
			$this->API = $api;
			$this->AccountType = $user->account_type;
			$this->ID = $user->id;
			$this->MediaCount = $user->media_count;
			$this->Username = $user->username;
		}

		/*
			Function: getMedia
				Alias for BigTreeInstagramAPI::getUserMedia
		*/

		public function getMedia() {
			return $this->API->getUserMedia($this->ID);
		}
		
		protected function _deprecated() {
			trigger_error("Method is not supported on the Instagram Basic Display API.", E_USER_NOTICE);
			
			return false;
		}

		public function getFriends() { return $this->_deprecated(); }
		public function getFollowers() { return $this->_deprecated(); }
		public function getRelationship() { return $this->_deprecated(); }
		public function setRelationship() { return $this->_deprecated(); }
		
	}
