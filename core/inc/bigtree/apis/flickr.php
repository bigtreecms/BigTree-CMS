<?
	/*
		Class: BigTreeFlickrAPI
			Flickr API class that implements most people and photo related API methods.
	*/

	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));
	
	class BigTreeFlickrAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://www.flickr.com/services/oauth/request_token";
		var $EndpointURL = "https://ycpi.api.flickr.com/services/rest";
		var $OAuthVersion = "1.0";
		var $RequestType = "hash";
		var $TokenURL = "https://www.flickr.com/services/oauth/authorize";
		
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
			$r = $this->call("flickr.photos.addTags",array("photo_id" => $photo,"tags" => $tags),"POST");
			if ($r->stat == "ok") {
				return true;
			} else {
				return false;
			}
		}

		/*
			Function: callUncached
				Overrides BigTreeOAuthAPIBase to always request normal JSON.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			$params["method"] = $endpoint;
			$params["format"] = "json";
			$params["nojsoncallback"] = true;
			$r = parent::callUncached("",$params,$method,$headers);
			if ($r->stat == "fail") {
				$this->Errors[] = $r->message;
				return false;
			}
			return $r;
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
			$r = $this->call("flickr.photos.delete",array("photo_id" => $photo),"POST");
			if ($r->stat == "ok") {
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
				An array of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getContactsPhotos($count = 10,$just_friends = false,$include_self = false,$info = "license,date_upload,date_taken,owner_name,icon_server,original_format,last_update") {
			$params = array("count" => $count,"extras" => $info);
			if ($just_friends) {
				$params["just_friends"] = 1;
			}
			if ($include_self) {
				$params["include_self"] = 1;
			}
			$r = $this->call("flickr.photos.getContactsPhotos",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				// Fix Flickr's inconsistent API job.
				$owner = new stdClass;
				$owner->nsid = $photo->owner;
				$owner->username = $photo->username;
				$photo->owner = $owner;
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return $photos;
		}

		/*
			Function: getGroup
				Returns information about a group.

			Parameters:
				id - The ID of the group.

			Returns:
				A BigTreeFlickrGroup object or false if the person isn't found.
		*/

		function getGroup($id) {
			$r = $this->call("flickr.groups.getInfo",array("group_id" => $id));
			if (!isset($r->group)) {
				return false;
			}
			return new BigTreeFlickrGroup($r->group,$this);
		}

		/*
			Function: getMyGeotaggedPhotos
				Returns a list of the authenticated user's photos that have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getMyGeotaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.photos.getWithGeoData",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getMyGeotaggedPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getMyRecentlyUpdatedPhotos($since = "-1 week",$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$params["min_date"] = date("Y-m-d H:i:s",strtotime($since));
			$r = $this->call("flickr.photos.recentlyUpdated",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getMyRecentlyUpdatedPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: getMyUncategorizedPhotos
				Returns a list of the authenticated user's photos that are not a part of any set.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getUntagged API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getMyUncategorizedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.photos.getNotInSet",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getMyUncategorizedPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: getMyUngeotaggedPhotos
				Returns a list of the authenticated user's photos that do not have geolocation data.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getWithGeoData API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getMyUngeotaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.photos.getWithoutGeoData",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getMyUngeotaggedPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: getMyUntaggedPhotos
				Returns a list of the authenticated user's photos that are not tagged.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getNotInSet API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getMyUntaggedPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.photos.getUntagged",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getMyUntaggedPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: getPerson
				Returns information about a person.

			Parameters:
				id - The ID of the person.

			Returns:
				A BigTreeFlickrPerson object or false if the person isn't found.
		*/

		function getPerson($id) {
			$r = $this->call("flickr.people.getInfo",array("user_id" => $id));
			if (!isset($r->person)) {
				return false;
			}
			return new BigTreeFlickrPerson($r->person,$this);
		}

		/*
			Function: getPhoto
				Returns information about a photo.

			Parameters:
				id - The ID of the photo.
				secret - The photo's secret (optional).

			Returns:
				A BigTreeFlickrPhoto object or false if the photo isn't found.
		*/

		function getPhoto($id,$secret = false) {
			$r = $this->call("flickr.photos.getInfo",array("photo_id" => $id,"secret" => $secret));
			if (!isset($r->photo)) {
				return false;
			}
			return new BigTreeFlickrPhoto($r->photo,$this);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getPhotosByLocation($latitude,$longitude,$radius = 10,$radius_unit = "mi",$per_page = 100,$sort = "date-posted-desc",$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["lat"] = $latitude;
			$params["lon"] = $longitude;
			$params["radius"] = $radius;
			$params["radius_units"] = $radius_unit;
			$params["extras"] = $info;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$r = $this->call("flickr.photos.search",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getPhotosByLocation",array($latitude,$longitude,$radius,$radius_unit,$per_page,$sort,$info,$params),$photos,$r->photos->page,$r->photos->pages);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects.
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
			$r = $this->call("flickr.photos.search",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getPhotosByTag",array($tags,$per_page,$sort,$require_all,$user,$info,$params),$photos,$r->photos->page,$r->photos->pages);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getPhotosForPerson($person,$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.people.getPhotos",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getPhotosForPerson",array($person,$per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getPhotosOfPerson($person,$per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["user_id"] = $person;
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.people.getPhotosOf",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getPhotosOfPerson",array($person,$per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: getRecentPhotos
				Returns a list of public photos recently updated to Flickr.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				info - A comma separated list of additional information to retrieve (defaults to description, license, date_upload, date_taken, icon_server, original_format, last_update, geo, tags, views, media)
				params - Additional parameters to pass to the flickr.photos.getRecent API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getRecentPhotos($per_page = 100,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			$params["per_page"] = $per_page;
			$params["extras"] = $info;
			$r = $this->call("flickr.photos.getRecent",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"getRecentPhotos",array($per_page,$info,$params),$photos,$r->photos->page,$r->photos->pages);
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			$this->Settings["token_secret"] = "";
			$admin = new BigTreeAdmin;
			$r = $this->callAPI("http://www.flickr.com/services/oauth/request_token","GET",array("oauth_callback" => $this->ReturnURL));
			parse_str($r);
			if ($oauth_callback_confirmed) {
				$this->Settings["token"] = $oauth_token;
				$this->Settings["token_secret"] = $oauth_token_secret;
				BigTree::redirect("http://www.flickr.com/services/oauth/authorize?perms=delete&oauth_token=".$oauth_token);
			} else {
				$admin->growl($oauth_problem,"Flickr API","error");
				BigTree::redirect(ADMIN_ROOT."developer/services/flickr/");
			}
		}

		/*
			Function: oAuthRefreshToken
				Refreshes an existing token setup.
		*/

		function oAuthRefreshToken() {
			$r = json_decode(BigTree::cURL($this->TokenURL,array(
				"client_id" => $this->Settings["key"],
				"client_secret" => $this->Settings["secret"],
				"refresh_token" => $this->Settings["refresh_token"],
				"grant_type" => "refresh_token"
			)));
			if ($r->access_token) {
				$this->Settings["token"] = $r->access_token;
				$this->Settings["expires"] = strtotime("+".$r->expires_in." seconds");
			}
		}

		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
		*/

		function oAuthSetToken($code) {
			$r = $this->callAPI("http://www.flickr.com/services/oauth/access_token","GET",array("oauth_verifier" => $_GET["oauth_verifier"],"oauth_token" => $_GET["oauth_token"]));
			parse_str($r);
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
			$r = $this->call("flickr.photos.removeTag",array("tag_id" => $tag));
			if ($r !== false) {
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
				A BigTreeFlickrPerson object or false if no person is found.
		*/

		function searchPeople($query) {
			// Search by email
			if (strpos($query,"@") !== false) {
				$r = $this->call("flickr.people.findByEmail",array("find_email" => $query));
			// Search by username
			} else {
				$r = $this->call("flickr.people.findByUsername",array("username" => $query));
			}

			if (!isset($r->user)) {
				return false;
			}
			return $this->getPerson($r->user->nsid);
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
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function searchPhotos($query,$per_page = 100,$sort = "date-posted-desc",$user = false,$info = "description,license,date_upload,date_taken,icon_server,original_format,last_update,geo,tags,views,media",$params = array()) {
			if ($user) {
				$params["user_id"] = $user;
			}
			$params["text"] = $query;
			$params["extras"] = $info;
			$params["per_page"] = $per_page;
			$params["sort"] = $sort;
			$r = $this->call("flickr.photos.search",$params);
			if (!isset($r->photos)) {
				return false;
			}
			$photos = array();
			foreach ($r->photos->photo as $photo) {
				$photos[] = new BigTreeFlickrPhoto($photo,$this);
			}
			return new BigTreeFlickrResultSet($this,"searchPhotos",array($query,$per_page,$sort,$user,$info,$params),$photos,$r->photos->page,$r->photos->pages);
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
			$r = $this->call("flickr.photos.setMeta",array("photo_id" => $photo,"title" => $title,"description" => $description),"POST");
			$r = $this->call("flickr.photos.setTags",array("photo_id" => $photo,"tags" => $tags),"POST");
			if ($r !== false) {
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

	/*
		Class: BigTreeFlickrGroup
			A Flickr object that contains information about and methods you can perform on a group.
	*/

	class BigTreeFlickrGroup {
		protected $API;

		function __construct($group,&$api) {
			$this->API = $api;
			isset($group->description->_content) ? $this->Description = $group->description->_content : false;
			$this->ID = isset($group->nsid) ? $group->nsid : $group->id;
			$this->Image = "http://farm".$group->iconfarm.".staticflickr.com/".$group->iconserver."/buddyicons/".$this->ID.".jpg";
			$this->MemberCount = isset($group->members->_content) ? $group->members->_content : $group->members;
			$this->Name = isset($group->name->_content) ? $group->name->_content : $group->name;
			$this->PhotoCount = isset($group->pool_count->_content) ? $group->pool_count->_content : $group->pool_count;
			isset($group->rules->_content) ? $this->Rules = $group->rules->_content : false;
			isset($group->topic_count->_content) ? $this->TopicCount = $group->topic_count->_content : false;
		}
	}

	/*
		Class: BigTreeFlickrPerson
			A Flickr object that contains information about and methods you can perform on a person.
	*/

	class BigTreeFlickrPerson {
		protected $API;

		function __construct($person,&$api) {
			$this->API = $api;
			isset($person->description->_content) ? $this->Description = $person->description->_content : false;
			$this->ID = isset($person->nsid) ? $person->nsid : $person->id;
			isset($person->iconfarm) ? $this->Image = "http://farm".$person->iconfarm.".staticflickr.com/".$person->iconserver."/buddyicons/".$person->nsid.".jpg" : false;
			isset($person->location) ? $this->Location = isset($person->location->_content) ? $person->location->_content : $person->location : false;
			isset($person->mobileurl->_content) ? $this->MobileURL = $person->mobileurl->_content : false;
			isset($person->realname) ? $this->Name = isset($person->realname->_content) ? $person->realname->_content : $person->realname : false;
			isset($person->photos->count->_content) ? $this->PhotoCount = $person->photos->count->_content : false;
			isset($person->photosurl->_content) ? $this->PhotosURL = $person->photosurl->_content : false;
			isset($person->photos->views->_content) ? $this->PhotoViews = $person->photos->views->_content : false;
			isset($person->ispro) ? $this->ProAccount = $person->ispro : false;
			isset($person->profileurl->_content) ? $this->ProfileURL = $person->profileurl->_content : false;
			isset($person->username) ? $this->Username = isset($person->username->_content) ? $person->username->_content : $person->username : false;
		}

		/*
			Function: getGroups
				Returns the groups this person is a member of.

			Returns:
				An array of BigTreeFlickrGroup objects or false if the call fails.
		*/

		function getGroups() {
			$r = $this->API->call("flickr.people.getGroups",array("user_id" => $this->ID));
			if (!isset($r->groups)) {
				return false;
			}
			$groups = array();
			foreach ($r->groups->group as $group) {
				$groups[] = new BigTreeFlickrGroup($group,$this->API);
			}
			return $groups;
		}

		/*
			Function: getPhotos
				Returns the photos this person has uploaded.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getPhotos($per_page = 100,$params = array()) {
			return $this->API->getPhotosForPerson($this->ID,$per_page,$params);
		}

		/*
			Function: getPhotosOf
				Returns photos of this person.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPhoto objects or false if the call fails.
		*/

		function getPhotosOf($per_page = 100,$params = array()) {
			return $this->API->getPhotosOfPerson($this->ID,$per_page,$params);
		}
	}

	/*
		Class: BigTreeFlickrPhoto
			A Flickr object that contains information about and methods you can perform on a photo.
	*/

	class BigTreeFlickrPhoto {
		protected $API;

		function __construct($photo,&$api) {
			$image_base = "http://farm".$photo->farm.".staticflickr.com/".$photo->server."/".$photo->id."_".$photo->secret;

			$this->API = $api;
			if (isset($photo->editability)) {
				$this->CanComment = $photo->editability->cancomment;
				$this->CanAddMeta = $photo->editability->canaddmeta;
			}
			isset($photo->comments->_content) ? $this->CommentCount = $photo->comments->_content : false;
			if (isset($photo->dates) || isset($photo->dateupload)) {
				$this->Dates = new stdClass;
				$this->Dates->Posted = date("Y-m-d H:i:s",isset($photo->dates->posted) ? $photo->dates->posted : $photo->dateupload);
				isset($photo->dates->taken) ? $this->Dates->Taken = $photo->dates->taken : false;
				isset($photo->datetaken) ? $this->Dates->Taken = $photo->datetaken : false;
				isset($photo->dates->lastupdate) ? $this->Dates->Updated = date("Y-m-d H:i:s",$photo->dates->lastupdate) : false;
			}
			isset($photo->description->_content) ? $this->Description = $photo->description->_content : false;
			isset($photo->isfavorite) ? $this->Favorited = $photo->isfavorite : false;
			$this->ID = $photo->id;
			$this->Image100 = $image_base."_t.jpg";
			$this->Image240 = $image_base."_m.jpg";
			$this->Image320 = $image_base."_n.jpg";
			$this->Image500 = $image_base.".jpg";
			$this->Image640 = $image_base."_z.jpg";
			$this->Image800 = $image_base."_c.jpg";
			$this->Image1024 = $image_base."_b.jpg";
			$this->ImageSquare75 = $image_base."_s.jpg";
			$this->ImageSquare150 = $image_base."_q.jpg";
			isset($photo->license) ? $this->License = $photo->license : false;
			if (isset($photo->latitude)) {
				$this->Location = new stdClass;
				$this->Location->Accuracy = $photo->accuracy;
				$this->Location->Latitude = $photo->latitude;
				$this->Location->Longitude = $photo->longitude;
			}
			isset($photo->notes->note) ? $this->Notes = $photo->notes->note : false;
			isset($photo->originalsecret) ? $this->OriginalImage = "http://farm".$photo->farm.".staticflickr.com/".$photo->server."/".$photo->id."_".$photo->originalsecret."_o.".$photo->originalformat : false;
			isset($photo->rotation) ? $this->Rotation = $photo->rotation : false;
			isset($photo->safety_level) ? $this->SafetyLevel = $photo->safety_level : false;
			isset($photo->secret) ? $this->Secret = $photo->secret : false;
			if (isset($photo->tags->tag)) {
				$this->Tags = array();
				foreach ($photo->tags->tag as $tag) {
					$this->Tags[] = new BigTreeFlickrTag($tag,$api);
				}
			} elseif (isset($photo->tags)) {
				$this->Tags = array();
				$tags = explode(" ",$photo->tags);
				foreach ($tags as $t) {
					$this->Tags[] = new BigTreeFlickrTag($t,$api);
				}
			}
			$this->Title = isset($photo->title->_content) ? $photo->title->_content : $photo->title;
			isset($photo->media) ? $this->Type = $photo->media : false;
			if (isset($photo->urls->url)) {
				$this->URLs = new stdClass;
				foreach ($photo->urls->url as $u) {
					$k = ucwords($u->type);
					$this->URLs->$k = $u->_content;
				}
			}
			isset($photo->owner) ? $this->User = new BigTreeFlickrPerson($photo->owner,$api) : false;
			$this->VisibleToFamily = isset($photo->visibility->isfamily) ? $photo->visibility->isfamily : $photo->isfamily;
			$this->VisibleToFriends = isset($photo->visibility->isfriend) ? $photo->visibility->isfriend : $photo->isfriend;
			$this->VisibleToPublic = isset($photo->visibility->ispublic) ? $photo->visibility->ispublic : $photo->ispublic;
		}

		/*
			Function: addTags
				Adds tags to this photo.
		
			Parameters:
				tags - A single tag as a string or an array of tags.

			Returns:
				true if successful
		*/

		function addTags($tags) {
			return $this->API->addTagsToPhoto($this->ID,$tags);
		}

		/*
			Function: getContext
				Gets information about the next and previous photos in the photo stream.
		*/

		function getContext() {
			$r = $this->API->call("flickr.photos.getContext",array("photo_id" => $this->ID));
			if (isset($r->nextphoto)) {
				$this->NextPhoto = new BigTreeFlickrPhoto($r->nextphoto,$this->API);
			}
			if (isset($r->prevphoto)) {
				$this->PreviousPhoto = new BigTreeFlickrPhoto($r->prevphoto,$this->API);
			}
		}

		/*
			Function: getExif
				Gets EXIF/TIFF/GPS information about this photo.
		*/

		function getExif() {
			$r = $this->API->call("flickr.photos.getExif",array("photo_id" => $this->ID,"secret" => $this->Secret));
			if (!isset($r->photo)) {
				return false;
			}
			$tags = array();
			foreach ($r->photo->exif as $item) {
				$tag = new stdClass;
				$tag->Label = $item->label;
				$tag->Name = $item->tag;
				isset($item->clean) ? $tag->RawValue = $item->raw->_content : false;
				$tag->Type = $item->tagspace;
				$tag->TypeID = $item->tagspaceid;
				$tag->Value = isset($item->clean) ? $item->clean->_content : $item->raw->_content;
				$tags[] = $tag;
			}
			return $tags;
		}

		/*
			Function: getFavorites
				Returns the users who have favorited this photo.

			Parameters:
				per_page - Number of results per page (defaults to 50, max 50)
				params - Additional parameters to pass to the flickr.photos.getFavorites API call

			Returns:
				A BigTreeFlickrResultSet of BigTreeFlickrPerson objects.
		*/

		function getFavorites($per_page = 50,$params = array()) {
			$params["photo_id"] = $this->ID;
			$params["per_page"] = $per_page;
			$r = $this->API->call("flickr.photos.getFavorites",$params);
			if (!$r->photo) {
				return false;
			}
			$people = array();
			foreach ($r->photo->person as $person) {
				$people[] = new BigTreeFlickrPerson($person,$this->API);
			}
			return new BigTreeFlickrResultSet($this,"getFavorites",array($per_page,$params),$people,$r->photo->page,$r->photo->pages);
		}

		/*
			Function: getInfo
				Returns additional information on this photo.
				Useful if another call returned limited information about a photo.

			Returns:
				A new BigTreeFlickrPhoto object or false if the call fails.
		*/

		function getInfo() {
			$r = $this->API->call("flickr.photos.getInfo",array("photo_id" => $this->ID,"secret" => $this->Secret));
			if (!isset($r->photo)) {
				return false;
			}
			return new BigTreeFlickrPhoto($r->photo,$this->API);
		}

		/*
			Function: delete
				Deletes this photo.
		
			Returns:
				true if successful
		*/

		function delete() {
			return $this->API->deletePhoto($this->ID);
		}

		/*
			Function: next
				Returns the next photo in the photo stream.
		*/

		function next() {
			if ($this->NextPhoto) {
				return $this->NextPhoto;
			}
			$this->getContext();
			return $this->NextPhoto;
		}

		/*
			Function: previous
				Returns the previous photo in the photo stream.
		*/

		function previous() {
			if ($this->PreviousPhoto) {
				return $this->PreviousPhoto;
			}
			$this->getContext();
			return $this->PreviousPhoto;
		}

		/*
			Function: setContentType
				Sets the content type of the image.

			Parameters:
				type - 1 (Photo), 2 (Screenshot), 3 (Other)

			Returns:
				true if successful
		*/

		function setContentType($type) {
			$r = $this->API->call("flickr.photos.setContentType",array("photo_id" => $this->ID,"content_type" => $type),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}

		/*
			Function: setDateTaken
				Sets the date taken of the image.

			Parameters:
				date - Date in a format understood by strtotime

			Returns:
				true if successful
		*/

		function setDateTaken($date) {
			$date = date("Y-m-d H:i:s",strtotime($date));
			$r = $this->API->call("flickr.photos.setDates",array("photo_id" => $this->ID,"date_taken" => $date),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}

		/*
			Function: setPermissions
				Sets the permissions of the image.

			Parameters:
				public - Visible to public (defaults to true)
				friends - Visible to friends (defaults to true)
				family - Visible to family (defaults to true)
				comments - Who can comment on this image (0 = none, 1 = friends & family, 2 = contacts, 3 = everyone - default)
				metadata - Who can add metadata (tags & notes) to this image (0 = none/owner - default, 1 = friends & family, 2 = contacts, 3 = everyone)

			Returns:
				true if successful
		*/

		function setPermissions($public = true,$friends = true,$family = true,$comments = 3,$metadata = 0) {
			$r = $this->API->call("flickr.photos.setPerms",array("photo_id" => $this->ID,"is_public" => $public,"is_friend" => $friends,"is_family" => $family,"perm_comment" => $comments,"perm_addmeta" => $metadata),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}

		/*
			Function: setSafetyLevel
				Sets the safety level of the image.

			Parameters:
				level - 1 (safe, default), 2 (moderate), 3 (restricted)

			Returns:
				true if successful
		*/

		function setSafetyLevel($level) {
			$r = $this->API->call("flickr.photos.setSafetyLevel",array("photo_id" => $this->ID,"safety_level" => $level),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}

		/*
			Function: setTags
				Sets the tags of the image.

			Parameters:
				tags - An array of tags or a comma separated string of tags

			Returns:
				true if successful
		*/

		function setTags($tags) {
			if (is_array($tags)) {
				$tags = implode(",",$tags);
			}
			$r = $this->API->call("flickr.photos.setTags",array("photo_id" => $this->ID,"tags" => $tags),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}

		/*
			Function: setTitleAndDescription
				Sets the title and description of the image.

			Parameters:
				title - Title to set
				description - Description to set

			Returns:
				true if successful
		*/

		function setTitleAndDescription($title,$description) {
			$r = $this->API->call("flickr.photos.setMeta",array("photo_id" => $this->ID,"title" => $title,"description" => $description),"POST");
			if ($r !== false) {
				return true;
			}
			return false;
		}
	}

	/*
		Class: BigTreeFlickrResultSet
			An object that contains multiple results from a Flickr API query.
	*/

	class BigTreeFlickrResultSet {

		/*
			Constructor:
				Creates a result set of Flickr data.

			Parameters:
				api - An instance of BigTreeFlickrAPI
				last_call - Method called on BigTreeFlickrAPI
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$results,$current_page,$total_pages) {
			$this->API = $api;
			$this->CurrentPage = $current_page;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Results = $results;
			$this->TotalPages = $total_pages;
		}

		/*
			Function: nextPage
				Calls the previous method again (with modified parameters)

			Returns:
				A BigTreeFlickrResultSet with the next page of results.
		*/

		function nextPage() {
			if ($this->CurrentPage < $this->TotalPages) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage + 1;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}

		/*
			Function: previousPage
				Calls the previous method again (with modified parameters)

			Returns:
				A BigTreeFlickrResultSet with the next page of results.
		*/

		function previousPage() {
			if ($this->CurrentPage > 1) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage - 1;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}
	}

	/*
		Class: BigTreeFlickrTag
			A Flickr object that contains information about and methods you can perform on a tag.
	*/

	class BigTreeFlickrTag {

		function __construct($tag,&$api) {
			if (!is_string($tag)) {
				$this->API = $api;
				$this->Author = $tag->author;
				$this->ID = $tag->id;
				$this->Name = $tag->raw;
			} else {
				$this->Name = $tag;
			}
		}

		function __toString() {
			return $this->Name;
		}

		/*
			Function: remove
				Removes this tag from the associated photo.

			Returns:
				true on success
		*/

		function remove() {
			return $this->API->removeTagFromPhoto($this->ID);
		}
	}
?>