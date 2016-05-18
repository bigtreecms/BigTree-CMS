<?php
	/*
		Class: BigTree\Flickr\API
			Flickr API class that implements most people and photo related API methods.
	*/

	namespace BigTree\Flickr;

	use BigTree\cURL;
	use BigTree\OAuth;
	use BigTree\Router;
	use stdClass;

	class API extends OAuth {
		
		public $AuthorizeURL = "https://www.flickr.com/services/oauth/request_token";
		public $EndpointURL = "https://ycpi.api.flickr.com/services/rest";
		public $OAuthVersion = "1.0";
		public $RequestType = "hash";
		public $TokenURL = "https://www.flickr.com/services/oauth/authorize";
		
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
			Function: addTagsToPhoto
				Adds tags to a photo.
		
			Parameters:
				photo - The ID of the photo to add tags to.
				tags - A single tag as a string or an array of tags.

			Returns:
				true if successful
		*/

		function addTagsToPhoto($photo,$tags) {
			if (is_array($tags)) {
				$tags = implode(",",$tags);
			}

			$response = $this->call("flickr.photos.addTags",array("photo_id" => $photo,"tags" => $tags),"POST");

			if ($response->stat == "ok") {
				return true;
			} else {
				return false;
			}
		}

		/*
			Function: callUncached
				Overrides BigTreeOAuthAPIBase to always request normal JSON.
		*/

		function callUncached($endpoint = "",$params = array(),$method = "GET",$headers = array()) {
			$params["method"] = $endpoint;
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			$response = parent::callUncached("",$params,$method,$headers);

			if ($response->stat == "fail") {
				$this->Errors[] = $response->message;
				return false;
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

		function deletePhoto($photo) {
			$response = $this->call("flickr.photos.delete",array("photo_id" => $photo),"POST");

			if ($response->stat == "ok") {
				return true;
			} else {
				return false;
			}
		}

		/*
			Function: getContactsPhotos
				Returns recent photos from the contacts of the authenticated user.

			Parameters:
				count - Number of photos to return (defaults to 10, max 50)
				just_friends - Only return photos from friends instead of all contacts (defaults to false)
				include_self - Include your own photos in the stream (defaults to false)
				info - A comma separated list of additional information to retrieve (defaults to license, date_upload, date_taken, owner_name, icon_server, original_format, last_update)

			Returns:
				An array of Photo objects or false if the call fails.
		*/

		function getContactsPhotos($count = 10,$just_friends = false,$include_self = false,$info = "license,date_upload,date_taken,owner_name,icon_server,original_format,last_update") {
			$params = array("count" => $count,"extras" => $info);
			if ($just_friends) {
				$params["just_friends"] = 1;
			}
			if ($include_self) {
				$params["include_self"] = 1;
			}
			$response = $this->call("flickr.photos.getContactsPhotos",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				// Fix Flickr's inconsistent API job.
				$owner = new stdClass;
				$owner->nsid = $photo->owner;
				$owner->username = $photo->username;
				$photo->owner = $owner;
				$photos[] = new Photo($photo,$this);
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

		function getGroup($id) {
			$response = $this->call("flickr.groups.getInfo",array("group_id" => $id));

			if (!isset($response->group)) {
				return false;
			}

			return new Group($response->group,$this);
		}

		/*
			Function: getMyGeotaggedPhotos
				Returns a list of the authenticated user's photos that have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getMyGeotaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.photos.getWithGeoData",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getMyGeotaggedPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getMyRecentlyUpdatedPhotos
				Returns a list of the authenticated user's photos that have been recently updated (including changes to metadata, new comments, etc).

			Parameters:
				since - A date from which to pull updates (defaults to one week) — should be formatted in something strtotime() understands
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getMyRecentlyUpdatedPhotos($since = "-1 week",$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$params["min_date"] = date("Y-m-d H:i:s",strtotime($since));
			$response = $this->call("flickr.photos.recentlyUpdated",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getMyRecentlyUpdatedPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getMyUncategorizedPhotos
				Returns a list of the authenticated user's photos that are not a part of any set.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getUntagged API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getMyUncategorizedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.photos.getNotInSet",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getMyUncategorizedPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getMyUngeotaggedPhotos
				Returns a list of the authenticated user's photos that do not have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getMyUngeotaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.photos.getWithoutGeoData",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getMyUngeotaggedPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getMyUntaggedPhotos
				Returns a list of the authenticated user's photos that are not tagged.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getNotInSet API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getMyUntaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.photos.getUntagged",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getMyUntaggedPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getPerson
				Returns information about a person.

			Parameters:
				id - The ID of the person.

			Returns:
				A BigTree\Flickr\Person object or false if the person isn't found.
		*/

		function getPerson($id) {
			$response = $this->call("flickr.people.getInfo",array("user_id" => $id));

			if (!isset($response->person)) {
				return false;
			}

			return new Person($response->person,$this);
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

		function getPhoto($id,$secret = false) {
			$response = $this->call("flickr.photos.getInfo",array("photo_id" => $id,"secret" => $secret));

			if (!isset($response->photo)) {
				return false;
			}

			return new Photo($response->photo,$this);
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
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getPhotosByLocation($latitude,$longitude,$radius = 10,$radius_unit = "mi",$per_page = 100,$sort = "date-posted-desc",$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["lat"] = $latitude;
			$params["lon"] = $longitude;
			$params["radius"] = $radius;
			$params["radius_units"] = $radius_unit;
			$params["extras"] = $info;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getPhotosByLocation",array($latitude,$longitude,$radius,$radius_unit,$per_page,$sort,$info,$params),$photos,$response->photos->page,$response->photos->pages);
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
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects.
		*/

		function getPhotosByTag($tags,$per_page = 100,$sort = "date-posted-desc",$require_all = false,$user = false,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			if (is_array($tags)) {
				$tags = implode(",",$tags);
			}
			if ($user) {
				$params["user_id"] = $user;
			}
			if ($require_all) {
				$params["tag_mode"] = "all";
			}
			$params["tags"] = $tags;
			$params["extras"] = $info;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getPhotosByTag",array($tags,$per_page,$sort,$require_all,$user,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getPhotosForPerson
				Returns the photos a given person has uploaded.

			Parameters:
				person - The ID of the person whom you wish to pull the photos of (use "me" for the authenticated user's photos).
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getPhotosForPerson($person,$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.people.getPhotos",$params);
			
			if (!isset($response->photos)) {
				return false;
			}
			
			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}
			
			return new ResultSet($this,"getPhotosForPerson",array($person,$per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getPhotosOfPerson
				Returns photos containing a given person.

			Parameters:
				person - The ID of the person whom you wish to pull the photos of (use "me" for the authenticated user).
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.people.getPhotosOf API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getPhotosOfPerson($person,$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.people.getPhotosOf",$params);
			
			if (!isset($response->photos)) {
				return false;
			}
			
			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}
			
			return new ResultSet($this,"getPhotosOfPerson",array($person,$per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: getRecentPhotos
				Returns a list of public photos recently updated to Flickr.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getRecent API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getRecentPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$response = $this->call("flickr.photos.getRecent",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"getRecentPhotos",array($per_page,$info,$params),$photos,$response->photos->page,$response->photos->pages);
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			$this->Settings["token_secret"] = "";
			$admin = new \BigTreeAdmin;
			$response = $this->callAPI("http://www.flickr.com/services/oauth/request_token","GET",array("oauth_callback" => $this->ReturnURL));
			parse_str($response);

			if ($oauth_callback_confirmed) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				header("Location: http://www.flickr.com/services/oauth/authorize?perms=delete&oauth_token=".$oauth_token);
				die();
			} else {
				$admin->growl($oauth_problem,"Flickr API","error");
				Router::redirect(ADMIN_ROOT."developer/services/flickr/");
			}
		}

		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/

		function oAuthRefreshToken() {
			$response = json_decode(cURL::request($this->TokenURL,array(
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
			Function: removeTagFromPhoto
				Removes a tag from a photo.

			Parameters:
				tag - The tag ID to remove.

			Returns:
				true if successful
		*/

		function removeTagFromPhoto($tag) {
			$response = $this->call("flickr.photos.removeTag",array("tag_id" => $tag));

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

		function searchPeople($query) {
			// Search by email
			if (strpos($query,"@") !== false) {
				$response = $this->call("flickr.people.findByEmail",array("find_email" => $query));
			// Search by username
			} else {
				$response = $this->call("flickr.people.findByUsername",array("username" => $query));
			}

			if (!isset($response->user)) {
				return false;
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
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.search API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function searchPhotos($query,$per_page = 100,$sort = "date-posted-desc",$user = false,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			if ($user) {
				$params["user_id"] = $user;
			}
			$params["text"] = $query;
			$params["extras"] = $info;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$response = $this->call("flickr.photos.search",$params);

			if (!isset($response->photos)) {
				return false;
			}

			$photos = array();
			foreach ($response->photos->photo as $photo) {
				$photos[] = new Photo($photo,$this);
			}

			return new ResultSet($this,"searchPhotos",array($query,$per_page,$sort,$user,$info,$params),$photos,$response->photos->page,$response->photos->pages);
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

		function setPhotoInformation($photo,$title = "",$description = "",$tags = "") {
			if (is_array($tags)) {
				$tags = implode(",",$tags);
			}
			$meta = $this->call("flickr.photos.setMeta",array("photo_id" => $photo,"title" => $title,"description" => $description),"POST");
			$tags = $this->call("flickr.photos.setTags",array("photo_id" => $photo,"tags" => $tags),"POST");

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

		function uploadPhoto($photo,$title = "",$description = "",$tags = array(),$public = true,$family = true,$friends = true,$safety = 1,$type = 1,$hidden = false) {
			$xml = $this->callAPI("http://up.flickr.com/services/upload/","POST",
				array("photo" => "@".$photo,"title" => $title,"description" => $description,"tags" => implode(" ",$tags),"is_public" => $public,"is_family" => $family,"is_friends" => $friends,"safety_level" => $safety,"content_type" => $type,"hidden" => ($hidden ? 2 : 1)),
				array(),
				array("photo")
			);
			$doc = @simplexml_load_string($xml);

			if (isset($doc->photoid)) {
				return strval($doc->photoid);
			}

			return false;
		}
		
	}
