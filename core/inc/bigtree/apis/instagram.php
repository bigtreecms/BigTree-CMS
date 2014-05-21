<?
	/*
		Class: BigTreeInstagramAPI
			Instagram API class that implements most API calls (media posting excluded).
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));

	class BigTreeInstagramAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://api.instagram.com/oauth/authorize/";
		var $EndpointURL = "https://api.instagram.com/v1/";
		var $OAuthVersion = "1.0";
		var $RequestType = "custom";
		var $Scope = "basic comments relationships likes";
		var $TokenURL = "https://api.instagram.com/oauth/access_token";
		
		/*
			Constructor:
				Sets up the Instagram API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
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

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			$response = parent::callUncached($endpoint,$params,$method,$headers);
			if (isset($response->meta->error_message)) {
				$this->Errors[] = $response->meta->error_message;
				return false;
			}
			return $response;
		}

		/*
			Function: comment
				Leaves a comment on a media post by the authenticated user.
				This method requires special access permissions for your Instagram application.
				Please email apidevelopers@instagram.com for access. 

			Parameters:
				id - The media ID to comment on.
				comment - The text to leave as a comment.

			Returns:
				true if successful
		*/

		function comment($id,$comment) {
			$response = $this->call("media/$id/comments",array("text" => $comment),"POST");
			if ($response->meta->code == 200) {
				return true;
			}
			return false;
		}

		/*
			Function: deleteComment
				Leaves a comment on a media post by the authenticated user.

			Parameters:
				id - The media ID the comment was left on.
				comment - The comment ID.

			Returns:
				true if successful
		*/

		function deleteComment($id,$comment) {
			$response = $this->call("media/$id/comments/$comment",array(),"DELETE");
			if ($response->meta->code == 200) {
				return true;
			}
			return false;
		}

		/*
			Function: getComments
				Returns a list of comments for a given media ID.

			Parameters:
				id - The media ID to retrieve comments for.

			Returns:
				An array of BigTreeInstagramComment objects.
		*/

		function getComments($id) {
			$response = $this->call("media/$id/comments");
			if (!isset($response->data)) {
				return false;
			}
			$comments = array();
			foreach ($response->data as $comment) {
				$comments[] = new BigTreeInstagramComment($comment,$id,$this);
			}
			return $comments;
		}

		/*
			Function: getFeed
				Returns the authenticated user's feed.

			Parameters:
				count - The number of media results to return (defaults to 10)
				params - Additional parameters to pass to the users/self/feed API call

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/

		function getFeed($count = 10,$params = array()) {
			$response = $this->call("users/self/feed",array_merge($params,array("count" => $count)));
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"getFeed",array($count,array_merge($params,array("max_id" => end($results)->ID))),$results);
		}

		/*
			Function: getFriends
				Returns a list of people the given user ID follows

			Parameters:
				id - The user ID to retrieve the friends of

			Returns:
				An array of BigTreeInstagramUser objects
		*/

		function getFriends($id) {
			$response = $this->call("users/$id/follows");
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $user) {
				$results[] = new BigTreeInstagramUser($user,$this);
			}
			return $results;
		}

		/*
			Function: getFollowers
				Returns a list of people the given user ID is followed by

			Parameters:
				id - The user ID to retrieve the followers of

			Returns:
				An array of BigTreeInstagramUser objects
		*/

		function getFollowers($id) {
			$response = $this->call("users/$id/followed-by");
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $user) {
				$results[] = new BigTreeInstagramUser($user,$this);
			}
			return $results;
		}

		/*
			Function: getFollowRequests
				Returns a list of people that are awaiting permission to follow the authenticated user

			Returns:
				An array of BigTreeInstagramUser objects
		*/

		function getFollowRequests() {
			$response = $this->call("users/self/requested-by");
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $user) {
				$results[] = new BigTreeInstagramUser($user,$this);
			}
			return $results;
		}

		/*
			Function: getLikedMedia
				Returns a list of media the authenticated user has liked

			Parameters:
				count - The number of media results to return (defaults to 10)
				params - Additional parameters to pass to the users/self/media/liked API call
			
			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/

		function getLikedMedia($count = 10,$params = array()) {
			$response = $this->call("users/self/media/liked",array_merge($params,array("count" => $count)));
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"getLikedMedia",array($count,array_merge($params,array("max_like_id" => end($results)->ID))),$results);
		}

		/*
			Function: getLikes
				Returns a list of users that like a given media ID.

			Parameters:
				id - The media ID to get likes for

			Returns:
				An array of BigTreeInstagramUser objects.
		*/

		function getLikes($id) {
			$response = $this->call("media/$id/likes");
			if (!isset($response->data)) {
				return false;
			}
			$users = array();
			foreach ($response->data as $user) {
				$users[] = new BigTreeInstagramUser($user,$this);
			}
			return $users;
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
			if (!isset($response->data)) {
				return false;
			}
			return new BigTreeInstagramLocation($response->data,$this);
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
			Function: getLocationMedia
				Returns recent media from a given location

			Parameters:
				id - The location ID to pull media for
				params - Additional parameters to pass to the locations/{id}/media/recent API call

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/locations/
		*/

		function getLocationMedia($id,$params = array()) {
			$response = $this->call("locations/$id/media/recent",$params);
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"getLocationMedia",array($id,array("max_id" => end($results)->ID)),$results);
		}

		/*
			Function: getMedia
				Gets information about a given media ID

			Parameters:
				id - The media ID

			Returns:
				A BigTreeInstagramMedia object.
		*/

		function getMedia($id) {
			$response = $this->call("media/$id");
			if (!isset($response->data)) {
				return false;
			}
			return new BigTreeInstagramMedia($response->data,$this);
		}
		
		/*
			Function: getRelationship
				Returns the relationship of the given user to the authenticated user

			Parameters:
				id - The user ID to check the relationship of

			Returns:
				An object containg an "Incoming" key (whether they follow you, have requested to follow you, or nothing) and "Outgoing" key (whether you follow them, block them, etc)
		*/

		function getRelationship($id) {
			$response = $this->call("users/$id/relationship");
			if (!isset($response->data)) {
				return false;
			}
			$obj = new stdClass;
			$obj->Incoming = $response->data->incoming_status;
			$obj->Outgoing = $response->data->outgoing_status;
			return $obj;
		}

		/*
			Function: getTaggedMedia
				Returns recent photos that contain a given tag.

			Parameters:
				tag - The tag to search for
				params - Additional parameters to pass to the tags/{tag}/media/recent API call

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/tags/	
		*/

		function getTaggedMedia($tag,$params = array()) {
			$tag = (substr($tag,0,1) == "#") ? substr($tag,1) : $tag;
			$response = $this->call("tags/$tag/media/recent",$params);
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"getTaggedMedia",array($tag,array("min_id" => end($results)->ID)),$results);
		}

		/*
			Function: getUser
				Returns information about a given user ID.

			Parameters:
				id - The user ID to look up

			Returns:
				A BigTreeInstagramUser object.
		*/

		function getUser($id) {
			$response = $this->call("users/$id");
			if (!isset($response->data)) {
				return false;
			}
			return new BigTreeInstagramUser($response->data,$this);
		}

		/*
			Function: getUserMedia
				Returns recent media from a given user ID.

			Parameters:
				id - The user ID to return media for.
				count - The number of media results to return (defaults to 10).
				params - Additional parameters to pass to the users/{id}/media/recent API call.

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/

		function getUserMedia($id,$count = 10,$params = array()) {
			$response = $this->call("users/$id/media/recent",array_merge($params,array("count" => $count)));
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"getUserMedia",array($id,$count,array_merge($params,array("max_id" => end($results)->ID))),$results);
		}

		/*
			Function: like
				Sets a like on the given media by the authenticated user.

			Parameters:
				id - The media ID to like

			Returns:
				true if successful
		*/

		function like($id) {
			$response = $this->call("media/$id/likes",array(),"POST");
			if ($response->meta->code == 200) {
				return true;
			}
			return false;
		}

		/*
			Function: popularMedia
				Returns a list of popular media.

			Returns:
				An array of BigTreeInstagramMedia objects.
		*/

		function popularMedia() {
			$response = $this->call("media/popular");
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return $results;
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
			if (!isset($response->data)) {
				return false;
			}
			$locations = array();
			foreach ($response->data as $location) {
				$locations[] = new BigTreeInstagramLocation($location,$this);
			}
			return $locations;
		}

		/*
			Function: searchMedia
				Search for media taken in a given area.

			Parameters:
				latitude - Latitude
				longitude - Longitude
				distance - Distance (in meters) to search (default is 1000, max is 5000)
				params - Additional parameters to pass to the media/search API call

			Returns:
				A BigTreeInstagramResultSet of BigTreeInstagramMedia objects.

			See Also:
				http://instagram.com/developer/endpoints/media/
		*/

		function searchMedia($latitude,$longitude,$distance = 1000,$params = array()) {
			$response = $this->call("media/search",array_merge($params,array("lat" => $latitude,"lng" => $longitude,"distance" => intval($distance))));
			if (!isset($response->data)) {
				return false;
			}
			$results = array();
			foreach ($response->data as $media) {
				$results[] = new BigTreeInstagramMedia($media,$this);
			}
			return new BigTreeInstagramResultSet($this,"searchMedia",array($latitude,$longitude,$distance,array_merge($params,array("max_timestamp" => strtotime(end($results)->Timestamp)))),$results);
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
			if (!isset($response->data)) {
				return false;
			}
			$tags = array();
			foreach ($response->data as $tag) {
				$tags[] = new BigTreeInstagramTag($tag,$this);
			}
			return $tags;
		}

		/*
			Function: searchUsers
				Returns users that match the search query.

			Parameters:
				query - String to search for.
				count - Number of results to return (defaults to 10)

			Returns:
				An array of BigTreeInstagramUser objects.
		*/

		function searchUsers($query,$count = 10) {
			$response = $this->call("users/search",array("q" => $query,"count" => $count));
			if (!isset($response->data)) {
				return false;
			}
			$users = array();
			foreach ($response->data as $user) {
				$users[] = new BigTreeInstagramUser($user,$this);
			}
			return $users;
		}

		/*
			Function: setRelationship
				Modifies the authenticated user's relationship with the given user.

			Parameters:
				id - The user ID to set relationship status with
				action - "follow", "unfollow", "block", "unblock", "approve", or "deny"

			Returns:
				true if successful.
		*/

		function setRelationship($id,$action) {
			$response = $this->call("users/$id/relationship",array("action" => $action),"POST");
			if (!isset($response->data)) {
				return false;
			}
			return true;
		}

		/*
			Function: unlike
				Removes a like on the given media set by the authenticated user.

			Parameters:
				id - The media ID to like

			Returns:
				true if successful
		*/

		function unlike($id) {
			$response = $this->call("media/$id/likes",array(),"DELETE");
			if ($response->meta->code == 200) {
				return true;
			}
			return false;
		}

	}

	/*
		Class: BigTreeInstagramComment
			An Instagram object that contains information about and methods you can perform on a comment.
	*/

	class BigTreeInstagramComment {
		protected $API;

		/*
			Constructor:
				Creates a comment object from Instagram data.

			Parameters:
				comment - Instagram data
				media_id - ID for the media the comment was attached to
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($comment,$media_id,&$api) {
			$this->API = $api;
			isset($comment->text) ? $this->Content = $comment->text : false;
			isset($comment->id) ? $this->ID = $comment->id : false;
			$this->MediaID = $media_id;
			isset($comment->created_time) ? $this->Timestamp = date("Y-m-d H:i:s",$comment->created_time) : false;
			isset($comment->from) ? $this->User = new BigTreeInstagramUser($comment->from,$api) : false;
		}

		/*
			Function: delete
				Deletes the comment (must belong to the authenticated user)

			Returns:
				true if successful
		*/

		function delete() {
			return $this->API->deleteComment($this->MediaID,$this->ID);
		}
	}

	/*
		Class: BigTreeInstagramLocation
			An Instagram object that contains information about and methods you can perform on a location.
	*/

	class BigTreeInstagramLocation {
		protected $API;

		/*
			Constructor:
				Creates a location object from Instagram data.

			Parameters:
				location - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($location,$api) {
			$this->API = $api;
			isset($location->id) ? $this->ID = $location->id : false;
			isset($location->latitude) ? $this->Latitude = $location->latitude : false;
			isset($location->longitude) ? $this->Longitude = $location->longitude : false;
			isset($location->name) ? $this->Name = $location->name : false;
		}

		/*
			Function: getMedia
				Alias for BigTreeInstagramAPI::getLocationMedia
		*/

		function getMedia() {
			return $this->API->getLocationMedia($this->ID);
		}

	}

	/*
		Class: BigTreeInstagramMedia
			An Instagram object that contains information about and methods you can perform on media.
	*/

	class BigTreeInstagramMedia {
		protected $API;

		/*
			Constructor:
				Creates a media object from Instagram data.

			Parameters:
				media - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($media,&$api) {
			$this->API = $api;
			isset($media->caption) ? $this->Caption = $media->caption->text : false;
			isset($media->filter) ? $this->Filter = $media->filter : false;
			isset($media->id) ? $this->ID = $media->id : false;
			isset($media->images->standard_resolution->url) ? $this->Image = $media->images->standard_resolution->url : false;
			isset($media->user_has_liked) ? $this->Liked = $media->user_has_liked : false;
			if (isset($media->likes)) {
				$this->LikesCount = $media->likes->count;
				$this->Likes = array();
				foreach ($media->likes->data as $user) {
					$this->Likes[] = new BigTreeInstagramUser($user,$api);
				}
			}
			if (isset($media->location)) {
				$this->Location = new BigTreeInstagramLocation($media->location,$api);
			}
			isset($media->images->low_resolution->url) ? $this->SmallImage = $media->images->low_resolution->url : false;
			if (isset($media->tags)) {
				$this->Tags = array();
				foreach ($media->tags as $tag_name) {
					$tag = new BigTreeInstagramTag(false,$api);
					$tag->Name = $tag_name;
					$this->Tags[] = $tag;
				}
			}
			isset($media->images->thumbnail->url) ? $this->ThumbnailImage = $media->images->thumbnail->url : false;
			isset($media->created_time) ? $this->Timestamp = date("Y-m-d H:i:s",$media->created_time) : false;
			isset($media->type) ? $this->Type = $media->type : false;
			isset($media->link) ? $this->URL = $media->link : false;
			isset($media->user) ? $this->User = new BigTreeInstagramUser($media->user,$api) : false;
			isset($media->users_in_photo) ? $this->UsersInPhoto = $media->users_in_photo : false;
			if (isset($media->videos)) {
				$this->Videos = new stdClass;
				$this->Videos->Standard = new stdClass;
				$this->Videos->Standard->URL = $media->videos->standard_resolution->url;
				$this->Videos->Standard->Height = $media->videos->standard_resolution->height;
				$this->Videos->Standard->Width = $media->videos->standard_resolution->width;
				$this->Videos->LowRes = new stdClass;
				$this->Videos->LowRes->URL = $media->videos->low_resolution->url;
				$this->Videos->LowRes->Height = $media->videos->low_resolution->height;
				$this->Videos->LowRes->Width = $media->videos->low_resolution->width;
			}
		}

		/*
			Function: comment
				Alias for BigTreeInstagramAPI::comment
		*/

		function comment($comment) {
			return $this->API->comment($this->ID,$comment);
		}

		/*
			Function: getComments
				Alias for BigTreeInstagramAPI::getComments
		*/

		function getComments() {
			return $this->API->getComments($this->ID);
		}

		/*
			Function: getLikes
				Alias for BigTreeInstagramAPI::getLikes
		*/

		function getLikes() {
			return $this->API->getLikes($this->ID);
		}

		/*
			Function: getLocation
				Alias for BigTreeInstagramAPI::getLocation
		*/

		function getLocation() {
			return $this->API->getLikes($this->Location->ID);
		}

		/*
			Function: getUser
				Alias for BigTreeInstagramAPI::getUser
		*/

		function getUser() {
			return $this->API->getUser($this->User->ID);
		}

		/*
			Function: like
				Alias for BigTreeInstagramAPI::like
		*/

		function like() {
			return $this->API->like($this->ID);
		}

		/*
			Function: unlike
				Alias for BigTreeInstagramAPI::unlike
		*/

		function unlike() {
			return $this->API->unlike($this->ID);
		}
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

		function __construct(&$api,$last_call,$params,$results) {
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

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
	}

	/*
		Class: BigTreeInstagramTag
			An Instagram object that contains information about and methods you can perform on a tag.
	*/

	class BigTreeInstagramTag {
		protected $API;

		/*
			Constructor:
				Creates a tag object from Instagram data.

			Parameters:
				tag - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($tag,&$api) {
			$this->API = $api;
			isset($tag->media_count) ? $this->MediaCount = $tag->media_count : false;
			isset($tag->name) ? $this->Name = $tag->name : false;
		}

		/*
			Function: getMedia
				Alias for BigTreeInstagramAPI::getTaggedMedia
		*/

		function getMedia() {
			return $this->API->getTaggedMedia($this->Name);
		}
	}

	/*
		Class: BigTreeInstagramUser
			An Instagram object that contains information about and methods you can perform on a user.
	*/

	class BigTreeInstagramUser {
		protected $API;

		/*
			Constructor:
				Creates a user object from Instagram data.

			Parameters:
				user - Instagram data
				api - Reference to the BigTreeInstagramAPI class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			isset($user->bio) ? $this->Description = $user->bio : false;
			isset($user->counts->followed_by) ? $this->FollowersCount = $user->counts->followed_by : false;
			isset($user->counts->follows) ? $this->FriendsCount = $user->counts->follows : false;
			isset($user->id) ? $this->ID = $user->id : false;
			isset($user->profile_picture) ? $this->Image = $user->profile_picture : false;
			isset($user->counts->media) ? $this->MediaCount = $user->counts->media : false;
			isset($user->full_name) ? $this->Name = $user->full_name : false;
			isset($user->website) ? $this->URL = $user->website : false;
			isset($user->username) ? $this->Username = $user->username : false;
		}

		/*
			Function: getMedia
				Alias for BigTreeInstagramAPI::getUserMedia
		*/

		function getMedia() {
			return $this->API->getUserMedia($this->ID);
		}

		/*
			Function: getFriends
				Alias for BigTreeInstagramAPI::getFriends
		*/

		function getFriends() {
			return $this->API->getFriends($this->ID);
		}

		/*
			Function: getFollowers
				Alias for BigTreeInstagramAPI::getFollowers
		*/

		function getFollowers() {
			return $this->API->getFollowers($this->ID);
		}

		/*
			Function: getRelationship
				Alias for BigTreeInstagramAPI::getRelationship
		*/

		function getRelationship() {
			return $this->API->getRelationship($this->ID);
		}

		/*
			Function: setRelationship
				Alias for BigTreeInstagramAPI::setRelationship
		*/

		function setRelationship($action) {
			return $this->API->setRelationship($this->ID,$action);
		}
	}
?>