<?
	/*
		Class: BigTreeInstagramAPI
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeInstagramAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "https://api.instagram.com/v1/";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Instagram API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;
			
			// If we don't have the setting for the Instagram API, create it
			$this->Settings = $cms->getSetting("bigtree-internal-instagram-api");			
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-instagram-api", 
					"name" => "Instagram API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Instagram";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->scope = $this->Settings["scope"] ? $this->Settings["scope"] : "basic";
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/instagram/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();
		}
		
		/*
			Function: call
				Calls the Instagram API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The Instagram API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$options = array()) {
			global $cms;

			if (!$this->Connected) {
				throw new Exception("The Instagram API is not connected.");
			}

			if ($method != "GET") {
				return $this->callUncached($endpoint,$params,$method);				
			// Instagram wants everything in GET URL vars.
			} elseif (count($params)) {
				if (strpos($endpoint,"?") === false) {
					$endpoint .= "?";
				}
				foreach ($params as $key => $val) {
					$endpoint .= "&$key=".urlencode($val);
				}
				$params = array();
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.instagram",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Instagram normally.
					return json_decode(json_encode($record));
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.instagram",$cache_key,$response);
				}
				return $response;
			} else {
				$error_info = json_decode($this->OAuthClient->api_error,true);
				$this->Errors = array($error_info["meta"]);
				return false;
			}
		}

		/*
			Function: callUncached
				Calls the Instagram API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Instagram API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$options = array()) {
			if (!$this->Connected) {
				throw new Exception("The Instagram API is not connected.");
			}

			// Instagram wants everything in GET URL vars.
			if ($method == "GET" && count($params)) {
				if (strpos($endpoint,"?") === false) {
					$endpoint .= "?";
				}
				foreach ($params as $key => $val) {
					$endpoint .= "&$key=".urlencode($val);
				}
				$params = array();
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				return $response;
			} else {
				$error_info = json_decode($this->OAuthClient->api_error,true);
				$this->Errors = array($error_info["meta"]);
				return false;
			}
		}
		
		
		/*
			Function: getUserMedia
				Returns photos for a given user ID.

			Parameters:
				user_id - The ID of the user to retrieve media for (defaults to the connected user)
				limit - The number of results to return (defaults to 10)
				params - Additional parameters to pass to the users/{id}/media/recent API call

			Returns:
				An array of results.
		*/

		function getUserMedia($user_id = false, $limit = 10, $params = array()) {
			$user_id = $user_id ? $user_id : $this->Settings["user_id"];
			return $this->call("users/$user_id/media/recent?count=$limit",$params);
		}
		
		/*
			Function: getTaggedMedia
				Returns recent photos that contain a given tag.

			Parameters:
				tag - The tag to search for
				params - Additional parameters to pass to the tags/{tag}/media/recent API call

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.				
		*/

		function getTaggedMedia($tag,$params = array()) {
			$tag = (substr($tag,0,1) == "#") ? substr($tag,1) : $tag;
			$response = $this->call("tags/$tag/media/recent",$params);
			if (!$response->data) {
				return false;
			}
			$a = false;
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$a);
			}
			return new BigTreeInstagramResultSet($a,"getTaggedMedia",array($tag,array("min_id" => end($results)->ID)),$results);
		}

		/*
			Function: getLocation
				Returns location information for a given ID.

			Parameters:
				id - The location ID

			Returns:
				A BigTreeInstagramLocation object.
		*/

		function getLocation($id) {
			$response = $this->call("locations/$id");
			if (!$response->data) {
				return false;
			}
			return new BigTreeInstagramLocation($response->data,$api);
		}

		/*
			Function: getLocationByFoursquareID
				Returns location information for a given Foursquare API v2 ID.

			Parameters:
				id - The Foursquare API ID.

			Returns:
				A BigTreeInstagramLocation object.
		*/

		function getLocationByFoursquareID($id) {
			$response = $this->searchLocations(false,false,false,$id);
			if (!$response) {
				return false;
			}
			return $response[0];
		}

		/*
			Function: getLocationByLegacyFoursquareID
				Returns location information for a given Foursquare API v1 ID.

			Parameters:
				id - The Foursquare API ID.

			Returns:
				A BigTreeInstagramLocation object.
		*/

		function getLocationByLegacyFoursquareID($id) {
			$response = $this->searchLocations(false,false,false,false,$id);
			if (!$response) {
				return false;
			}
			return $response[0];
		}

		/*
			Function: searchLocations
				Returns locations that match the search location or Foursquare ID

			Parameters:
				latitude - Latitude (required if not searching by Foursquare ID)
				longitude - Longitude (required if not searching by Foursquare ID)
				distance - Numeric value in meters to search from the lat/lon location (defaults to 1000)
				foursquare_id - Foursquare API v2 ID to search by (ignores lat/lon)
				legacy_foursquare_id - Legacy Foursquare API v1 ID to search by (ignores lat/lon and API v2 ID)

			Returns:
				An array of BigTreeInstagramLocation objects
		*/

		function searchLocations($latitude = false,$longitude = false,$distance = 1000,$foursquare_id = false,$legacy_foursquare_id = false) {
			if ($legacy_foursquare_id) {
				$response = $this->call("locations/search",array("foursquare_id" => $legacy_foursquare_id));
			} elseif ($foursquare_id) {
				$response = $this->call("locations/search",array("foursquare_v2_id" => $foursquare_id));
			} else {
				$response = $this->call("locations/search",array("lat" => $latitude,"lng" => $longitude,"distance" => intval($distance)));
			}
			if (!$response->data) {
				return false;
			}
			$locations = array();
			foreach ($response->data as $location) {
				$locations[] = new BigTreeInstagramLocation($location,$this);
			}
			return $locations;
		}

		/*
			Function: searchTags
				Returns tags that match the search query.
				Exact match is the first result followed by most popular.
				If the exact match is popular enough, it is the only result.

			Parameters:
				tag - Tag to search for

			Returns:
				An array of BigTreeInstagramTag objects.
		*/

		function searchTags($tag) {
			$response = $this->call("tags/search",array("q" => (substr($tag,0,1) == "#") ? substr($tag,1) : $tag));
			if (!$response->data) {
				return false;
			}
			$tags = array();
			foreach ($response->data as $tag) {
				$tags[] = new BigTreeInstagramTag($tag,$this);
			}
			return $tags;
		}
	}

	/*
		Class: BigTreeInstagramResultSet
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

		function __construct(&$api,$last_call,$params,$results) {
			$this->API = $api;
			$this->Results = $results;
			$this->LastCall = $last_call;
			$last = end($results);
			// Set the max_id field on what would be the $params array sent to any call (since it's always last)
			$params[count($params) - 1]["max_id"] = $last->ID - 1;
			$this->LastParameters = $params;
		}

		/*
			Function: nextPage
				Calls the previous method with a max_id of the last received ID.

			Returns:
				A BigTreeInstagramResultSet with the next page of results.
		*/

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
	}

	/*
		Class: BigTreeInstagramMedia
	*/

	class BigTreeInstagramMedia {

		/*
			Constructor:
				Creates a media object from Instagram data.

			Parameters:
				media - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($media,&$api) {
			$this->API = $api;
			$this->ID = $media->id;
			$this->Type = $media->type;
			$this->UsersInPhoto = $media->users_in_photo;
			$this->Filter = $media->filter;
			if ($media->tags) {
				$this->Tags = array();
				foreach ($media->tags as $tag_name) {
					$tag = new BigTreeInstagramTag(false,$api);
					$tag->Name = $tag_name;
					$this->Tags[] = $tag;
				}
			}
			$this->Caption = $media->caption ? $media->caption->text : "";
			if ($media->likes) {
				$this->LikesCount = $media->likes->count;
				$this->Likes = array();
				foreach ($media->likes->data as $user) {
					$this->Likes[] = new BigTreeInstagramUser($user,$api);
				}
			}
			$this->URL = $media->link;
			$this->User = new BigTreeInstagramUser($media->user,$api);
			$this->Timestamp = date("Y-m-d H:i:s",$media->created_time);
			if ($media->images) {
				$this->Image = $media->images->standard_resolution->url;
				$this->SmallImage = $media->images->low_resolution->url;
				$this->ThumbnailImage = $media->images->thumbnail->url;
			}
			if ($media->location) {
				$this->Location = new BigTreeInstagramLocation($media->location,$api);
			}
		}
	}

	/*
		Class: BigTreeInstagramComment
	*/

	class BigTreeInstagramComment {

		/*
			Constructor:
				Creates a comment object from Instagram data.

			Parameters:
				comment - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($comment,&$api) {
			$this->API = $api;
			$this->Timestamp = date("Y-m-d H:i:s",$comment->created_time);
			$this->ID = $comment->id;
			$this->Content = $comment->text;
			$this->User = new BigTreeInstagramUser($comment->from,$api);
		}
	}

	/*
		Class: BigTreeInstagramUser
	*/

	class BigTreeInstagramUser {

		/*
			Constructor:
				Creates a user object from Instagram data.

			Parameters:
				user - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			$this->ID = $user->id;
			$this->Username = $user->username;
			$this->Name = $user->full_name;
			$this->Image = $user->profile_picture;
			$this->Description = $user->bio;
			$this->URL = $user->website;
			if ($user->counts) {
				$this->FollowersCount = $user->counts->followed_by;
				$this->FriendsCount = $user->counts->follows;
				$this->MediaCount = $user->counts->media;
			}
		}
	}

	/*
		Class: BigTreeInstagramLocation
	*/

	class BigTreeInstagramLocation {

		/*
			Constructor:
				Creates a location object from Instagram data.

			Parameters:
				location - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($location,$api) {
			$this->API = $api;
			$this->ID = $location->id;
			$this->Name = $location->name;
			$this->Latitude = $location->latitude;
			$this->Longitude = $location->longitude;
		}
	}

	/*
		Class: BigTreeInstagramTag
	*/

	class BigTreeInstagramTag {

		/*
			Constructor:
				Creates a tag object from Instagram data.

			Parameters:
				tag - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($tag,&$api) {
			$this->API = $api;
			$this->Name = $tag->name;
			$this->MediaCount = $tag->media_count;
		}
	}
?>