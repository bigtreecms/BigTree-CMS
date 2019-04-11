<?php
	/*
		Class: BigTree\Flickr\API
			Flickr API class that implements most people and photo related API methods.
	*/
	
	namespace BigTree\Flickr;
	
	use BigTree\cURL;
	use BigTree\OAuth;
	use BigTree\Router;
	use BigTree\Utils;
	use stdClass;
	
	class API extends OAuth
	{
		
		public $AuthorizeURL = "https://www.flickr.com/services/oauth/request_token";
		public $DefaultInfo = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media";
		public $EndpointURL = "https://api.flickr.com/services/rest";
		public $OAuthVersion = "1.0";
		public $RequestType = "hash";
		public $TokenURL = "https://www.flickr.com/services/oauth/authorize";
		
		/*
			Constructor:
				Sets up the Flickr API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		public function __construct(bool $cache = true)
		{
			parent::__construct("bigtree-internal-flickr-api", "YouTube API", "org.bigtreecms.api.flickr", $cache);
			
			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/flickr/return/";
		}
		
		/*
			Function: addTagsToPhoto
				Adds tags to a photo.
		
			Parameters:
				photo - The ID of the photo to add tags to.
				tags - A single tag as a string or an array of tags.

			Returns:
				true if successful
		*/
		
		public function addTagsToPhoto(string $photo, $tags): bool
		{
			if (is_array($tags)) {
				$tags = implode(",", $tags);
			}
			
			$response = $this->call("flickr.photos.addTags", ["photo_id" => $photo, "tags" => $tags], "POST");
			
			if ($response->stat == "ok") {
				return true;
			} else {
				return false;
			}
		}
		
		/*
			Function: callUncached
				Overrides BigTree\OAuth to always request normal JSON.
		*/
		
		public function callUncached(string $endpoint = "", array $params = [], string $method = "GET",
							  array $headers = []): ?stdClass
		{
			$params["method"] = $endpoint;
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			$response = parent::callUncached("", $params, $method, $headers);
			
			if ($response->stat == "fail") {
				$this->Errors[] = $response->message;
				
				return null;
			}
			
			return $response;
		}
		
		/*
			Function: deletePhoto
				Deletes a photo.
		
			Parameters:
				photo - The ID of the photo to delete.

			Returns:
				true if successful
		*/
		
		public function deletePhoto(string $photo): bool
		{
			$response = $this->callUncached("flickr.photos.delete", ["photo_id" => $photo], "POST");
			
			if ($response->stat == "ok") {
				return true;
			} else {
				return false;
			}
		}
		
		/*
			Function: getAlbumPhotos
				Returns a list of all of the public photos for a particular photo album

			Parameters:
				id - The ID of the photo album
				privacy - Privacy level of photos to return (defaults to PRIVACY_PUBLIC / 1)
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
	
			Returns:
				A BigTree\Flickr\ResultSet of BigTree\Flickr\Photo objects
		*/
		
		public function getAlbumPhotos(string $id, int $privacy = 1, string $info = ""): ?ResultSet
		{
			$params["photoset_id"] = $id;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$params["privacy_filter"] = $privacy;
			$params["media"] = "photos";
			$r = $this->call("flickr.photosets.getPhotos", $params);
			
			if (!isset($r->photoset)) {
				return null;
			}
			
			$photos = [];
			
			foreach ($r->photoset->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getAlbumPhotos", [$id, $privacy, $info], $photos, $r->photoset->page, $r->photoset->pages);
		}
		
		/*
			Function: getAlbums
				Returns all of the albums for the given user

			Parameters:
				user_id - The user ID to retrieve albums for (defaults to logged in user)
				
			Returns:
				A BigTree\Flickr\ResultSet of BigTree\Flickr\Album objects
		*/
		
		public function getAlbums(?string $user_id = null): ?ResultSet
		{
			$params = [];
			$params["primary_photo_extras"] = "media,date_taken,url_sq,url_t,url_s,url_m,url_o,";
			
			if ($user_id) {
				$params["user_id"] = $user_id;
			}
			
			$r = $this->call("flickr.photosets.getList", $params);
			
			if (!isset($r->photosets->photoset)) {
				return null;
			}
			
			$albums = [];
			
			foreach ($r->photosets->photoset as $album) {
				$albums[] = new Album($album, $this);
			}
			
			return new ResultSet($this, "getAlbums", [$user_id], $albums, $r->photosets->page, $r->photosets->pages);
		}
		
		/*
			Function: getContactsPhotos
				Returns recent photos from the contacts of the authenticated user.

			Parameters:
				count - Number of photos to return (defaults to 10, max 50)
				just_friends - Only return photos from friends instead of all contacts (defaults to false)
				include_self - Include your own photos in the stream (defaults to false)
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)

			Returns:
				An array of Photo objects or false if the call fails.
		*/
		
		public function getContactsPhotos(int $count = 10, bool $just_friends = false, bool $include_self = false,
								   string $info = ""): ?array
		{
			$params = ["count" => $count, "extras" => $info ?: $this->DefaultInfo];
			
			if ($just_friends) {
				$params["just_friends"] = 1;
			}
			
			if ($include_self) {
				$params["include_self"] = 1;
			}
			
			$response = $this->call("flickr.photos.getContactsPhotos", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				// Fix Flickr's inconsistent API job.
				$owner = new stdClass;
				$owner->nsid = $photo->owner;
				$owner->username = $photo->username;
				$photo->owner = $owner;
				$photos[] = new Photo($photo, $this);
			}
			
			return $photos;
		}
		
		/*
			Function: getGroup
				Returns information about a group.

			Parameters:
				id - The ID of the group.

			Returns:
				A BigTree\Flickr\Group object or false if the person isn't found.
		*/
		
		public function getGroup(string $id): ?Group
		{
			$response = $this->call("flickr.groups.getInfo", ["group_id" => $id]);
			
			if (!isset($response->group)) {
				return null;
			}
			
			return new Group($response->group, $this);
		}
		
		/*
			Function: getMyGeotaggedPhotos
				Returns a list of the authenticated user's photos that have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getMyGeotaggedPhotos(int $per_page = 100, string $info = "", array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.photos.getWithGeoData", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getMyGeotaggedPhotos", [$per_page, $info, $params],
								 $photos, $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getMyRecentlyUpdatedPhotos
				Returns a list of the authenticated user's photos that have been recently updated (including changes to metadata, new comments, etc).

			Parameters:
				since - A date from which to pull updates (defaults to one week) — should be formatted in something strtotime() understands
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getMyRecentlyUpdatedPhotos(string $since = "-1 week", int $per_page = 100, string $info = "",
											array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$params["min_date"] = date("Y-m-d H:i:s", strtotime($since));
			$response = $this->call("flickr.photos.recentlyUpdated", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getMyRecentlyUpdatedPhotos", [$per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getMyUncategorizedPhotos
				Returns a list of the authenticated user's photos that are not a part of any set.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getUntagged API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getMyUncategorizedPhotos(int $per_page = 100, string $info = "", array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.photos.getNotInSet", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getMyUncategorizedPhotos", [$per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getMyUngeotaggedPhotos
				Returns a list of the authenticated user's photos that do not have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getMyUngeotaggedPhotos(int $per_page = 100, string $info = "", array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.photos.getWithoutGeoData", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getMyUngeotaggedPhotos", [$per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getMyUntaggedPhotos
				Returns a list of the authenticated user's photos that are not tagged.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getNotInSet API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getMyUntaggedPhotos(int $per_page = 100, string $info = "", array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.photos.getUntagged", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getMyUntaggedPhotos", [$per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getPerson
				Returns information about a person.

			Parameters:
				id - The ID of the person.

			Returns:
				A BigTree\Flickr\Person object or false if the person isn't found.
		*/
		
		public function getPerson(string $id): ?Person
		{
			$response = $this->call("flickr.people.getInfo", ["user_id" => $id]);
			
			if (!isset($response->person)) {
				return null;
			}
			
			return new Person($response->person, $this);
		}
		
		/*
			Function: getPhoto
				Returns information about a photo.

			Parameters:
				id - The ID of the photo.
				secret - The photo's secret (optional).

			Returns:
				A Photo object or false if the photo isn't found.
		*/
		
		public function getPhoto(string $id, ?string $secret = null): ?Photo
		{
			$response = $this->call("flickr.photos.getInfo", ["photo_id" => $id, "secret" => $secret]);
			
			if (!isset($response->photo)) {
				return null;
			}
			
			return new Photo($response->photo, $this);
		}
		
		/*
			Function: getPhotosByLocation
				Returns a list of photos that were taken in a given radius from a location.

			Parameters:
				latitude - Latitude to search from
				longitude - Longitude to search from
				radius - Distance to search from lat/lon coordinates (numeric value, defaults to 10)
				radius_unit - "mi" for miles (default) or "km" for kilometers
				per_page - Number of photos per page, defaults to 100, max of 500.
				sort - Sort order, defaults to date-posted-desc (available: date-posted-asc, date-posted-desc, date-taken-asc, date-taken-desc, interestingness-desc, interestingness-asc, and relevance)
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects or null if the call fails.
		*/
		
		public function getPhotosByLocation(string $latitude, string $longitude, float $radius = 10.0,
									 string $radius_unit = "mi", int $per_page = 100, string $sort = "date-posted-desc",
									 string $info = "", array $params = []): ?ResultSet
		{
			$params["lat"] = $latitude;
			$params["lon"] = $longitude;
			$params["radius"] = $radius;
			$params["radius_units"] = $radius_unit;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getPhotosByLocation",
								 [$latitude, $longitude, $radius, $radius_unit, $per_page, $sort, $info, $params],
								 $photos, $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getPhotosByTag
				Returns photos that match a set of tags.

			Parameters:
				tags - An array (or comma separated string) of tags to search for. You can exclude tags by prepending them with a -
				per_page - Number of photos per page, defaults to 100, max of 500.
				sort - Sort order, defaults to date-posted-desc (available: date-posted-asc, date-posted-desc, date-taken-asc, date-taken-desc, interestingness-desc, interestingness-asc, and relevance)
				require_all - Set to true to require all the tags, leave false to accept any of the tags (defaults to false)
				user - Optional user ID to restrict the results to. Use "me" to only search your photos. (defaults to false)
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects.
		*/
		
		public function getPhotosByTag($tags, int $per_page = 100, string $sort = "date-posted-desc", bool $require_all = false,
								bool $user = false, string $info = "", array $params = []): ?ResultSet
		{
			if (is_array($tags)) {
				$tags = implode(",", $tags);
			}
			
			if ($user) {
				$params["user_id"] = $user;
			}
			
			if ($require_all) {
				$params["tag_mode"] = "all";
			}
			
			$params["tags"] = $tags;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getPhotosByTag", [$tags, $per_page, $sort, $require_all, $user, $info, $params],
								 $photos, $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getPhotosForPerson
				Returns the photos a given person has uploaded.

			Parameters:
				person - The ID of the person whom you wish to pull the photos of (use "me" for the authenticated user's photos).
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A ResultSet of Photo objects or null if the call fails.
		*/
		
		public function getPhotosForPerson(string $person, int $per_page = 100, string $info = "",
									array $params = []): ?ResultSet
		{
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.people.getPhotos", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getPhotosForPerson", [$person, $per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getPhotosOfPerson
				Returns photos containing a given person.

			Parameters:
				person - The ID of the person whom you wish to pull the photos of (use "me" for the authenticated user).
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.people.getPhotosOf API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getPhotosOfPerson(string $person, int $per_page = 100, string $info = "",
								   array $params = []): ?ResultSet
		{
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.people.getPhotosOf", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getPhotosOfPerson", [$person, $per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: getRecentPhotos
				Returns a list of public photos recently updated to Flickr.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.getRecent API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function getRecentPhotos(int $per_page = 100, string $info = "", array $params = []): ?ResultSet
		{
			$params["per_page"] = $per_page;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$response = $this->call("flickr.photos.getRecent", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "getRecentPhotos", [$per_page, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/
		
		public function oAuthRedirect(): void
		{
			$this->Settings["token_secret"] = "";
			$response = $this->callAPI("http://www.flickr.com/services/oauth/request_token", "GET", ["oauth_callback" => $this->ReturnURL]);
			
			// Set empty vars that we're expecting from parse_str
			$oauth_callback_confirmed = "";
			$oauth_token = "";
			$oauth_token_secret = "";
			$oauth_problem = "";
			
			parse_str($response);
			
			if (!empty($oauth_callback_confirmed)) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				
				header("Location: http://www.flickr.com/services/oauth/authorize?perms=delete&oauth_token=".$oauth_token);
				die();
			} else {
				Utils::growl($oauth_problem, "Flickr API", "error");
				Router::redirect(ADMIN_ROOT."developer/services/flickr/");
			}
		}
		
		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/
		
		public function oAuthRefreshToken(): void
		{
			$response = json_decode(cURL::request($this->TokenURL, [
				"client_id" => $this->Settings["key"],
				"client_secret" => $this->Settings["secret"],
				"refresh_token" => $this->Settings["refresh_token"],
				"grant_type" => "refresh_token"
			]));
			
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
		
		public function oAuthSetToken(string $code): ?stdClass
		{
			$response = $this->callAPI("http://www.flickr.com/services/oauth/access_token", "GET",
									   ["oauth_verifier" => $_GET["oauth_verifier"], "oauth_token" => $_GET["oauth_token"]]);
			
			// Setup vars we're expecting a response from in parse_str
			$fullname = "";
			$oauth_token = "";
			$oauth_token_secret = "";
			
			parse_str($response);
			
			if ($fullname) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				$this->Connected = true;
				
				$response_object = new stdClass;
				$response_object->Token = $oauth_token;
				$response_object->Secret = $oauth_token_secret;
				
				return $response_object;
			}
			
			return null;
		}
		
		/*
			Function: removeTagFromPhoto
				Removes a tag from a photo.

			Parameters:
				tag - The tag ID to remove.

			Returns:
				true if successful
		*/
		
		public function removeTagFromPhoto(string $tag): bool
		{
			$response = $this->callUncached("flickr.photos.removeTag", ["tag_id" => $tag]);
			
			if ($response !== false) {
				return true;
			}
			
			return false;
		}
		
		/*
			Function: searchPeople
				Find a person by email or username.
			
			Parameters:
				query - Either an email address or username.

			Returns:
				A BigTree\Flickr\Person object or false if no person is found.
		*/
		
		public function searchPeople(string $query): ?Person
		{
			// Search by email
			if (strpos($query, "@") !== false) {
				$response = $this->call("flickr.people.findByEmail", ["find_email" => $query]);
				// Search by username
			} else {
				$response = $this->call("flickr.people.findByUsername", ["username" => $query]);
			}
			
			if (!isset($response->user)) {
				return null;
			}
			
			return $this->getPerson($response->user->nsid);
		}
		
		/*
			Function: searchPhotos
				Returns a list of photos that match the given query.

			Parameters:
				query - Search terms to query against photo titles, descriptions, and tags
				per_page - Number of photos per page, defaults to 100, max of 500.
				sort - Sort order, defaults to date-posted-desc (available: date-posted-asc, date-posted-desc, date-taken-asc, date-taken-desc, interestingness-desc, interestingness-asc, and relevance)
				user - User ID to limit the search results to (use "me" for the authenticated user).
				info - A comma separated list of additional information to retrieve (defaults to $this->DefaultInfo)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/
		
		public function searchPhotos(string $query, int $per_page = 100, string $sort = "date-posted-desc",
							  ?string $user = null, string $info = "",  array $params = []): ?ResultSet
		{
			if ($user) {
				$params["user_id"] = $user;
			}
			
			$params["text"] = $query;
			$params["extras"] = $info ?: $this->DefaultInfo;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search", $params);
			$photos = [];
			
			if (!isset($response->photos)) {
				return null;
			}
			
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo, $this);
			}
			
			return new ResultSet($this, "searchPhotos", [$query, $per_page, $sort, $user, $info, $params], $photos,
								 $response->photos->page, $response->photos->pages);
		}
		
		/*
			Function: setPhotoInformation
				Sets the title, description, and tags of a photo.
		
			Parameters:
				photo - The ID of the photo to modify.
				title - The title to set.
				description - The description to set.
				tags - An array of tags or a comma separated string of tags.

			Returns:
				true if successful
		*/
		
		public function setPhotoInformation(string $photo, ?string $title = null, ?string $description = null,
									 $tags = ""): bool
		{
			if (is_array($tags)) {
				$tags = implode(",", $tags);
			}
			
			$meta = $this->callUncached("flickr.photos.setMeta", [
				"photo_id" => $photo,
				"title" => $title,
				"description" => $description
			], "POST");
			$tags = $this->callUncached("flickr.photos.setTags", ["photo_id" => $photo, "tags" => $tags], "POST");
			
			if ($meta !== false && $tags !== false) {
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
		
		public function uploadPhoto(string $photo, ?string $title = null, ?string $description = null, array $tags = [],
							 bool $public = true, bool $family = true, bool $friends = true, int $safety = 1,
							 int $type = 1, bool $hidden = false): ?string
		{
			$xml = $this->callAPI("http://up.flickr.com/services/upload/", "POST", [
				"photo" => "@".$photo,
				"title" => $title,
				"description" => $description,
				"tags" => implode(" ", $tags),
				"is_public" => $public,
				"is_family" => $family,
				"is_friends" => $friends,
				"safety_level" => $safety,
				"content_type" => $type,
				"hidden" => ($hidden ? 2 : 1)
			], [], ["photo"]);
			$doc = @simplexml_load_string($xml);
			
			if (isset($doc->photoid)) {
				return strval($doc->photoid);
			}
			
			return null;
		}
		
	}
