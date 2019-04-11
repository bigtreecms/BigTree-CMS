<?php
	/*
		Class: BigTree\Facebook\API
			Facebook API class that implements some API calls.
	*/
	
	namespace BigTree\Facebook;
	
	use BigTree\OAuth;
	use stdClass;
	
	class API extends OAuth
	{
		
		public $AuthorizeURL = "https://www.facebook.com/dialog/oauth";
		public $EndpointURL = "https://graph.facebook.com/v2.8/";
		public $OAuthVersion = "2.0";
		public $RequestType = "header";
		public $Scope = "";
		public $TokenURL = "https://graph.facebook.com/v2.8/oauth/access_token";
		
		const ALBUM_FIELDS = "id,name,description,link,cover_photo,count,place,type,created_time";
		
		/*
			Constructor:
				Sets up the Facebook API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/
		
		function __construct(bool $cache = true)
		{
			parent::__construct("bigtree-internal-facebook-api", "Facebook API", "org.bigtreecms.api.facebook", $cache);
			
			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/facebook/return/";
			
			// Set access scope
			$this->Scope = $this->Settings["scope"];
		}
		
		/*
			Function: getAlbum
				 Returns an album for the given album ID.

			Parameters:
				album_id - ID of album

			 Returns:
				 A BigTree\Facebook\Album object or false if the object id does not exist.
		*/
		
		function getAlbum(string $album_id): ?Album
		{
			$response = $this->call($album_id."?fields=".API::ALBUM_FIELDS);
			
			if (!$response->id) {
				return null;
			}
			
			return new Album($response, $this);
		}
		
		/*
			Function: getUser
				Returns a user for the given user ID.
				Returns the authenticated user if no ID is passed in.

			Parameters:
				user - The ID of the person to return.

			Returns:
				A BigTree\Facebook\Person object.
		*/
		
		function getUser(string $user = "me"): ?User
		{
			$response = $this->call($user);
			
			if (!$response->id) {
				return null;
			}
			
			return new User($response, $this);
		}
		
	}
