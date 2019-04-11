<?php
	/*
		Class: BigTree\Instagram\API
			Instagram API class that implements most API calls (media posting excluded).
	*/
	
	namespace BigTree\Instagram;
	
	use BigTree\OAuth;
	use stdClass;
	
	class API extends OAuth
	{
		
		public $AuthorizeURL = "https://api.instagram.com/oauth/authorize/";
		public $EndpointURL = "https://api.instagram.com/v1/";
		public $OAuthVersion = "1.0";
		public $RequestType = "custom";
		public $Scope = "basic comments relationships likes";
		public $TokenURL = "https://api.instagram.com/oauth/access_token";
		
		/*
			Constructor:
				Sets up the Instagram API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		function __construct(bool $cache = true)
		{
			parent::__construct("bigtree-internal-instagram-api", "Instagram API", "org.bigtreecms.api.instagram", $cache);
			
			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/instagram/return/";
			
			// Just send the request with the secret.
			$this->RequestParameters = [];
			$this->RequestParameters["access_token"] = &$this->Settings["token"];
		}
		
		/*
			Function: callUncached
				Piggybacks on the base call to provide error checking for Instagram.
		*/
		
		function callUncached(string $endpoint = "", array $params = [], string $method = "GET",
							  array $headers = []): ?stdClass
		{
			$response = parent::callUncached($endpoint, $params, $method, $headers);
			
			if (isset($response->meta->error_message)) {
				$this->Errors[] = $response->meta->error_message;
				
				return null;
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
		
		function comment(string $id, string $comment): bool
		{
			$response = $this->call("media/$id/comments", ["text" => $comment], "POST");
			
			if (!empty($response->meta->code) && $response->meta->code == 200) {
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
		
		function deleteComment(string $id, string $comment): bool
		{
			$response = $this->call("media/$id/comments/$comment", [], "DELETE");
			
			if (!empty($response->meta->code) && $response->meta->code == 200) {
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
				An array of BigTree\Instagram\Comment objects.
		*/
		
		function getComments(string $id): ?array
		{
			$response = $this->call("media/$id/comments");
			$comments = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $comment) {
				$comments[] = new Comment($comment, $id, $this);
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
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/
		
		function getFeed(int $count = 10, array $params = []): ?ResultSet
		{
			$response = $this->call("users/self/feed", array_merge($params, ["count" => $count]));
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last ID in this page
			$params["max_id"] = end($results)->ID;
			
			return new ResultSet($this, "getFeed", [$count, $params], $results);
		}
		
		/*
			Function: getFriends
				Returns a list of people the given user ID follows

			Parameters:
				id - The user ID to retrieve the friends of

			Returns:
				An array of BigTree\Instagram\User objects
		*/
		
		function getFriends(string $id): ?array
		{
			$response = $this->call("users/$id/follows");
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $user) {
				$results[] = new User($user, $this);
			}
			
			return $results;
		}
		
		/*
			Function: getFollowers
				Returns a list of people the given user ID is followed by

			Parameters:
				id - The user ID to retrieve the followers of

			Returns:
				An array of BigTree\Instagram\User objects
		*/
		
		function getFollowers(string $id): ?array
		{
			$response = $this->call("users/$id/followed-by");
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $user) {
				$results[] = new User($user, $this);
			}
			
			return $results;
		}
		
		/*
			Function: getFollowRequests
				Returns a list of people that are awaiting permission to follow the authenticated user

			Returns:
				An array of BigTree\Instagram\User objects
		*/
		
		function getFollowRequests(): ?array
		{
			$response = $this->call("users/self/requested-by");
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $user) {
				$results[] = new User($user, $this);
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
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/
		
		function getLikedMedia(int $count = 10, array $params = []): ?ResultSet
		{
			$response = $this->call("users/self/media/liked", array_merge($params, ["count" => $count]));
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last ID in this page
			$params["max_like_id"] = end($results)->ID;
			
			return new ResultSet($this, "getLikedMedia", [$count, $params], $results);
		}
		
		/*
			Function: getLikes
				Returns a list of users that like a given media ID.

			Parameters:
				id - The media ID to get likes for

			Returns:
				An array of BigTree\Instagram\User objects.
		*/
		
		function getLikes(string $id): ?array
		{
			$response = $this->call("media/$id/likes");
			$users = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $user) {
				$users[] = new User($user, $this);
			}
			
			return $users;
		}
		
		/*
			Function: getLocation
				Returns location information for a given ID.

			Parameters:
				id - The location ID

			Returns:
				A BigTree\Instagram\Location object.
		*/
		
		function getLocation(string $id): ?Location
		{
			$response = $this->call("locations/$id");
			
			if (!isset($response->data)) {
				return null;
			}
			
			return new Location($response->data, $this);
		}
		
		/*
			Function: getLocationByFoursquareID
				Returns location information for a given Foursquare API v2 ID.

			Parameters:
				id - The Foursquare API ID.

			Returns:
				A BigTree\Instagram\Location object.
		*/
		
		function getLocationByFoursquareID($id): ?Location
		{
			$response = $this->searchLocations(null, null, null, $id);
			
			if (!$response) {
				return null;
			}
			
			return $response[0];
		}
		
		/*
			Function: getLocationByLegacyFoursquareID
				Returns location information for a given Foursquare API v1 ID.

			Parameters:
				id - The Foursquare API ID.

			Returns:
				A BigTree\Instagram\Location object.
		*/
		
		function getLocationByLegacyFoursquareID(string $id): ?Location
		{
			$response = $this->searchLocations(null, null, null, null, $id);
			
			if (!$response) {
				return null;
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
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/locations/
		*/
		
		function getLocationMedia(string $id, array $params = []): ?ResultSet
		{
			$response = $this->call("locations/$id/media/recent", $params);
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last ID in this page
			$params["max_id"] = end($results)->ID;
			
			return new ResultSet($this, "getLocationMedia", [$id, $params], $results);
		}
		
		/*
			Function: getMedia
				Gets information about a given media ID

			Parameters:
				id - The media ID
				shortcode - The media shortcode (from instagram.com shortlink URL, optional & replaces ID)

			Returns:
				A BigTree\Instagram\Media object.
		*/
		
		function getMedia(string $id, bool $shortcode = false): ?Media
		{
			if ($shortcode) {
				$response = $this->call("media/shortcode/$id");
			} else {
				$response = $this->call("media/$id");
			}
			
			if (!isset($response->data)) {
				return null;
			}
			
			return new Media($response->data, $this);
		}
		
		/*
			Function: getRelationship
				Returns the relationship of the given user to the authenticated user

			Parameters:
				id - The user ID to check the relationship of

			Returns:
				An object containg an "Incoming" key (whether they follow you, have requested to follow you, or nothing) and "Outgoing" key (whether you follow them, block them, etc)
		*/
		
		function getRelationship(string $id): ?stdClass
		{
			$response = $this->call("users/$id/relationship");
			
			if (!isset($response->data)) {
				return null;
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
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/tags/
		*/
		
		function getTaggedMedia(string $tag, array $params = []): ?ResultSet
		{
			$tag = (substr($tag, 0, 1) == "#") ? substr($tag, 1) : $tag;
			$response = $this->call("tags/$tag/media/recent", $params);
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last ID in this page
			$params["min_id"] = end($results)->ID;
			
			return new ResultSet($this, "getTaggedMedia", [$tag, $params], $results);
		}
		
		/*
			Function: getUser
				Returns information about a given user ID.

			Parameters:
				id - The user ID to look up

			Returns:
				A BigTree\Instagram\User object.
		*/
		
		function getUser(string $id): ?User
		{
			$response = $this->call("users/$id");
			
			if (!isset($response->data)) {
				return null;
			}
			
			return new User($response->data, $this);
		}
		
		/*
			Function: getUserMedia
				Returns recent media from a given user ID.

			Parameters:
				id - The user ID to return media for.
				count - The number of media results to return (defaults to 10).
				params - Additional parameters to pass to the users/{id}/media/recent API call.

			Returns:
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/users/
		*/
		
		function getUserMedia(string $id, int $count = 10, array $params = []): ?ResultSet
		{
			$response = $this->call("users/$id/media/recent", array_merge($params, ["count" => $count]));
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last ID in this page
			$params["max_id"] = end($results)->ID;
			
			return new ResultSet($this, "getUserMedia", [$id, $count, $params], $results);
		}
		
		/*
			Function: like
				Sets a like on the given media by the authenticated user.

			Parameters:
				id - The media ID to like

			Returns:
				true if successful
		*/
		
		function like(string $id): bool
		{
			$response = $this->call("media/$id/likes", [], "POST");
			
			if (!empty($response->meta->code) && $response->meta->code == 200) {
				return true;
			}
			
			return false;
		}
		
		/*
			Function: popularMedia
				Returns a list of popular media.

			Returns:
				An array of BigTree\Instagram\Media objects.
		*/
		
		function popularMedia(): ?array
		{
			$response = $this->call("media/popular");
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
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
				An array of BigTree\Instagram\Location objects
		*/
		
		function searchLocations(?string $latitude = "", ?string $longitude = "", ?int $distance = 1000,
								 ?string $foursquare_id = null, ?string $legacy_foursquare_id = null): ?array
		{
			if ($legacy_foursquare_id) {
				$params = ["foursquare_id" => $legacy_foursquare_id];
			} elseif ($foursquare_id) {
				$params = ["foursquare_v2_id" => $foursquare_id];
			} else {
				$params = ["lat" => $latitude, "lng" => $longitude, "distance" => intval($distance)];
			}
			
			$response = $this->call("locations/search", $params);
			$locations = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $location) {
				$locations[] = new Location($location, $this);
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
				A BigTree\Instagram\ResultSet of BigTree\Instagram\Media objects.

			See Also:
				http://instagram.com/developer/endpoints/media/
		*/
		
		function searchMedia(string $latitude, string $longitude, int $distance = 1000, array $params = []): ?ResultSet
		{
			$response = $this->call("media/search", array_merge($params, ["lat" => $latitude, "lng" => $longitude, "distance" => intval($distance)]));
			$results = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $media) {
				$results[] = new Media($media, $this);
			}
			
			// Set the next page to use the last timestamp in this page
			$params["max_timestamp"] = strtotime(end($results)->Timestamp);
			
			return new ResultSet($this, "searchMedia", [$latitude, $longitude, $distance, $params], $results);
		}
		
		/*
			Function: searchTags
				Returns tags that match the search query.
				Exact match is the first result followed by most popular.
				If the exact match is popular enough, it is the only result.

			Parameters:
				tag - Tag to search for

			Returns:
				An array of BigTree\Instagram\Tag objects.
		*/
		
		function searchTags(string $tag): ?array
		{
			$response = $this->call("tags/search", ["q" => (substr($tag, 0, 1) == "#") ? substr($tag, 1) : $tag]);
			$tags = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $tag) {
				$tags[] = new Tag($tag, $this);
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
				An array of BigTree\Instagram\User objects.
		*/
		
		function searchUsers(string $query, int $count = 10): ?array
		{
			$response = $this->call("users/search", ["q" => $query, "count" => $count]);
			$users = [];
			
			if (!isset($response->data)) {
				return null;
			}
			
			foreach ($response->data as $user) {
				$users[] = new User($user, $this);
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
		
		function setRelationship(string $id, string $action): bool
		{
			$response = $this->call("users/$id/relationship", ["action" => $action], "POST");
			
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
		
		function unlike(string $id): bool
		{
			$response = $this->call("media/$id/likes", [], "DELETE");
			
			if ($response->meta->code == 200) {
				return true;
			}
			
			return false;
		}
		
	}
