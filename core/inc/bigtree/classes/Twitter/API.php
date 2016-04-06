<?php
	/*
		Class: BigTree\Twitter\API
			Twitter API class that implements most functionality (limited lists support).
			All calls return false on API failure and set the "Errors" property to an array of errors returned by the Twitter API.
	*/

	namespace BigTree\Twitter;

	use BigTree\OAuth;
	use BigTree\Router;

	class API extends OAuth {

		public $Configuration;
		public $EndpointURL = "https://api.twitter.com/1.1/";
		public $OAuthVersion = "1.0";
		public $RequestType = "hash-header";
		public $TweetLength;

		/*
			Constructor:
				Sets up the Twitter API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-twitter-api","Twitter API","org.bigtreecms.api.twitter",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/twitter/return/";
		}

		/*
			Function: blockUser
				Blocks a given username by the authenticated user.

			Parameters:
				username - The username to block.

			Returns:
				A BigTree\Twitter\User object if successful.
		*/

		function blockUser($username) {
			$response = $this->callUncached("blocks/create.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new User($response,$this);
		}
		function block($username) { return $this->blockUser($username); }

		/*
			Function: callUncached
				Piggybacks on the base call to provide error checking for Twitter.
		*/

		function callUncached($endpoint = "",$params = array(),$method = "GET",$headers = array()) {
			$response = parent::callUncached($endpoint,$params,$method,$headers);
			if (isset($response->errors) && count($response->errors)) {
				foreach ($response->errors as $e) {
					$this->Errors[] = $e->message;
				}
				return false;
			}
			return $response;
		}

		/*
			Function: deleteDirectMessage
				Deletes a direct message that was received by the authenticated user.

			Parameters:
				id - The ID of the direct message.

			Returns:
				true if successful.
		*/

		function deleteDirectMessage($id) {
			$response = $this->callUncached("direct_messages/destroy.json",array("id" => $id),"POST");
			if (!$response) {
				return false;
			}
			return true;
		}

		/*
			Function: deleteTweet
				Deletes a tweet that belongs to the authenticated user.

			Parameters:
				id - The ID of the tweet to delete.

			Returns:
				True if successful.
		*/

		function deleteTweet($id) {
			$response = $this->callUncached("statuses/destroy/$id.json",array(),"POST");
			if (!$response) {
				return false;
			}
			return true;
		}

		/*
			Function: favoriteTweet
				Sets a tweet as a favorite of the authenticated user.

			Parameters:
				id - The tweet ID.

			Returns:
				A BigTree\Twitter\Tweet object if successful.
		*/

		function favoriteTweet($id) {
			$response = $this->callUncached("favorites/create.json",array("id" => $id),"POST");
			if (!$response) {
				return false;
			}
			return new Tweet($response,$this);
		}

		/*
			Function: followUser / friendUser
				Follows/friends a given user by the authenticated user.

			Parameters:
				username - The username to follow/friend.

			Returns:
				A BigTree\Twitter\User object on success.
		*/

		function followUser($username) {
			$response = $this->callUncached("friendships/create.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new User($response,$this);
		}
		function friendUser($username) { return $this->followUser($username); }

		/*
			Function: getBlockedUsers
				Returns a page of users that are blocked by the authenticated user.

			Parameters:
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the blocks/list API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\User objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/blocks/list
		*/

		function getBlockedUsers($skip_status = true,$params = array()) {
			$response = $this->call("blocks/list.json",array_merge($params,array("skip_status" => $skip_status)));
			if (!$response) {
				return false;
			}
			$users = array();
			foreach ($response->users as $user) {
				$users[] = new User($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new ResultSet($this,"getBlockedUsers",array($skip_status,$params),$users);
		}

		/*
			Function: getConfiguration
				Sets up information such as the length of reserved characters for URLs and media uploads.
		*/

		function getConfiguration() {
			$response = $this->call("help/configuration.json");
			if ($response) {
				$this->Configuration = $response;
			}
		}

		/*
			Function: getDirectMessage
				Returns a single direct messages for the given ID (must be sent to or by the authenticated user).

			Parameters:
				id - The ID of the direct message.

			Returns:
				A BigTree\Twitter\DirectMessage object.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/direct_messages
		*/

		function getDirectMessage($id) {
			$response = $this->call("direct_messages/show.json",array("id" => $id));
			if (!$response) {
				return false;
			}
			return new DirectMessage($response,$this);
		}

		/*
			Function: getDirectMessages
				Returns a page of direct messages sent to the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the direct_messages API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\DirectMessage objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/direct_messages
		*/

		function getDirectMessages($count = 10,$params = array()) {
			$response = $this->callUncached("direct_messages.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$results = array();
			foreach ($response as $message) {
				$results[] = new DirectMessage($message,$this);
			}
			return new ResultSet($this,"getDirectMessages",array($count,$params),$results);
		}

		/*
			Function: getFavoriteTweets
				Returns a page of favorite tweets of the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the favorites/list API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\Tweets objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/favorites/list
		*/

		function getFavoriteTweets($count = 10,$params = array()) {
			$response = $this->call("favorites/list.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$results = array();
			foreach ($response as $tweet) {
				$results[] = new Tweet($tweet,$this);
			}
			return new ResultSet($this,"getFavoriteTweets",array($count,$params),$results);
		}

		/*
			Function: getFollowers
				Returns a page of followers for a given username.

			Parameters:
				username - The username to return followers for.
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the followers/list API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\User objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/followers/list
		*/

		function getFollowers($username,$skip_status = true,$params = array()) {
			$response = $this->call("followers/list.json",array_merge($params,array("screen_name" => $username,"skip_status" => $skip_status)));
			if (!$response) {
				return false;
			}
			$users = array();
			foreach ($response->users as $user) {
				$users[] = new User($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new ResultSet($this,"getFollowers",array($username,$skip_status,$params),$users);
		}

		/*
			Function: getFriends
				Returns a page of friends (people they follow) for a given username.

			Parameters:
				username - The username to return followers for.
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the friends/list API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\User objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/friends/list
		*/

		function getFriends($username,$skip_status = true,$params = array()) {
			$response = $this->call("friends/list.json",array_merge($params,array("screen_name" => $username,"skip_status" => $skip_status)));
			if (!$response) {
				return false;
			}
			$users = array();
			foreach ($response->users as $user) {
				$users[] = new User($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new ResultSet($this,"getFriends",array($username,$skip_status,$params),$users);
		}

		/*
			Function: getHomeTimeline
				Returns recent tweets from the authenticated user and everyone the authenticated user follows.

			Parameters:
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\Tweet objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/home_timeline
		*/

		function getHomeTimeline($count = 10, $params = array()) {
			$response = $this->call("statuses/home_timeline.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new Tweet($tweet,$this);
			}
			return new ResultSet($this,"getHomeTimeline",array($count,$params),$tweets);
		}

		/*
			Function: getMentions
				Returns the timeline of mentions for the authenticated user.

			Parameters:
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/mentions_timeline API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\Tweet objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/mentions_timeline
		*/

		function getMentions($count = 10,$params = array()) {
			$response = $this->call("statuses/mentions_timeline.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new Tweet($tweet,$this);
			}
			return new ResultSet($this,"getMentions",array($count,$params),$tweets);
		}

		/*
			Function: getPlace
				Returns information about a place.

			Parameters:
				id - The place ID.

			Returns:
				A BigTree\Twitter\Place object.
		*/

		function getPlace($id) {
			$response = $this->call("geo/id/$id.json");
			if (!$response) {
				return false;
			}
			return new Place($response,$this);
		}

		/*
			Function: getSentDirectMessages
				Returns a page of direct messages sent by the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the direct_messages/sent API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\DirectMessage objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/direct_messages
		*/

		function getSentDirectMessages($count = 10,$params = array()) {
			$response = $this->call("direct_messages/sent.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$results = array();
			foreach ($response as $message) {
				$results[] = new DirectMessage($message,$this);
			}
			return new ResultSet($this,"getSentDirectMessages",array($count,$params),$results);
		}

		/*
			Function: getTweet
				Returns a single tweet.

			Parameters:
				id - The ID of the tweet to return.
				params - Additional parameters (key/value array) to pass to the the statuses/show API call.

			Returns:
				A BigTree\Twitter\Tweet object.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/show
		*/

		function getTweet($id,$params = array()) {
			$response = $this->call("statuses/show.json",array_merge($params,array("id" => $id)));
			if (!$response) {
				return false;
			}
			return new Tweet($response,$this);
		}

		/*
			Function: getUser
				Returns information about a user.

			Parameters:
				username - The username ("screen_name") of a Twitter user
				id - The ID of the Twitter user (replaces username if provided)

			Returns:
				A BigTree\Twitter\User object.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/users/show
		*/

		function getUser($username,$id = false) {
			if ($id) {
				$response = $this->call("users/show.json",array("id" => $id));
			} else {
				$response = $this->call("users/show.json",array("screen_name" => $username));
			}
			if ($response) {
				return new User($response,$this);
			}
			return false;
		}

		/*
			Function: getUserTimeline
				Returns recent tweets from the given user's timeline.

			Parameters:
				user_name - The Twitter user to retrieve tweets for.
				count - The number of tweets to return (defaults to 10).
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\Tweet objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		*/

		function getUserTimeline($user_name, $count = 10, $params = array()) {
			$response = $this->call("statuses/user_timeline.json",array_merge($params,array("screen_name" => $user_name,"count" => $count)));
			if (!$response) {
				return false;
			}
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new Tweet($tweet,$this);
			}
			return new ResultSet($this,"getUserTimeline",array($user_name,$count,$params),$tweets);
		}

		/*
			Function: oAuthRedirect
				Redirects to the OAuth API to authenticate.
		*/

		function oAuthRedirect() {
			// Get a token first because Twitter is silly.
			$response = $this->callAPI("https://api.twitter.com/oauth/request_token","GET",array("oauth_callback" => $this->ReturnURL));
			parse_str($response);
			if ($oauth_callback_confirmed != "true") {
				global $admin;
				$admin->growl("Twitter API","Consumer Key or Secret invalid.","error");
				Router::redirect(ADMIN_ROOT."developer/services/twitter/");
			}
			header("Location: https://api.twitter.com/oauth/authenticate?oauth_token=$oauth_token");
			die();
		}

		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.
		*/

		function oAuthSetToken($code) {
			$response = $this->callAPI("https://api.twitter.com/oauth/access_token","POST",array("oauth_token" => $_GET["oauth_token"],"oauth_verifier" => $_GET["oauth_verifier"]));
			parse_str($response);

			if (!$oauth_token) {
				$this->OAuthError = "Authentication failed.";
				return false;
			}

			// Update Token information and save it back.
			$this->Settings["token"] = $oauth_token;
			$this->Settings["token_secret"] = $oauth_token_secret;

			$this->Connected = true;
			return true;
		}

		/*
			Function: sendDirectMessage
				Sends a direct message by the authenticated user.

			Parameters:
				recipient_username - The recipient's username.
				content - The text to tweet.
				recipient_id - The recipient ID (replaces the recipient_username field).

			Returns:
				A BigTree\Twitter\DirectMessage object or false if the direct message fails or is too long.
				$this->TweetLength will be set to the length of the tweet if it is > 140 characters.
		*/

		function sendDirectMessage($recipient_username,$content,$recipient_id = false) {
			// Figure out how long our content can be
			if (!$this->Configuration) {
				$this->getConfiguration();
			}
			
			// Figure out how many URLs are
			$http_length = substr_count($content,"http://") * $this->Configuration->short_url_length;
			$https_length = substr_count($content,"https://") * $this->Configuration->short_url_length_https;
			$url_length = $http_length + $https_length;

			// Replace URLs so they no longer count toward length
  			$content_trimmed = preg_replace('/((?:http|https)(?::\\/{2}[\\w]+)(?:[\\/|\\.]?)(?:[^\\s"]*))/is',"",$content);
  			if (strlen($content_trimmed) + $url_length > 140) {
  				$this->TweetLength = strlen($content_trimmed) + $url_length;
  				return false;
  			}

  			if ($recipient_id) {
  				$response = $this->callUncached("direct_messages/new.json",array("user_id" => $recipient_id,"text" => $content),"POST");
  			} else {
  				$response = $this->callUncached("direct_messages/new.json",array("screen_name" => $recipient_username,"text" => $content),"POST");
  			}

  			if (!$response) {
				return false;
			}
			return new DirectMessage($response,$this);
		}

		/*
			Function: sendTweet
				Post a tweet by the authenticated user.
				If the tweet content is > 140 characters will fail and return false.

			Parameters:
				content - The text to tweet.
				image - Location of a local image file to upload (optional).
				params - Additional parameters (key/value array) to pass to the the statuses/update API call.

			Returns:
				A BigTree\Twitter\Tweet object or false if the tweet fails or is too long.
				$this->TweetLength will be set to the length of the tweet if it is > 140 characters.

			See Also:
				https://dev.twitter.com/docs/api/1.1/post/statuses/update
		*/

		function sendTweet($content,$image = false,$params = array()) {
			// Figure out how long our content can be
			if (!$this->Configuration) {
				$this->getConfiguration();
			}

			// Figure out how many URLs are
			$http_length = substr_count($content,"http://") * $this->Configuration->short_url_length;
			$https_length = substr_count($content,"https://") * $this->Configuration->short_url_length_https;
			$media_length = $image ? $this->Configuration->characters_reserved_per_media : 0;
			$url_length = $http_length + $https_length + $media_length;
			// Replace URLs so they no longer count toward length
  			$content_trimmed = preg_replace('/((?:http|https)(?::\\/{2}[\\w]+)(?:[\\/|\\.]?)(?:[^\\s"]*))/is',"",$content);
  			if (strlen($content_trimmed) + $url_length > 140) {
  				$this->TweetLength = strlen($content_trimmed) + $url_length;
  				return false;
  			}

			// Upload media first
			if ($image) {
				$params["media_ids"] = $this->uploadMedia($image);
				if (!$media_id) {
					return false;
				}
			}
			
			// Post tweet
			$response = $this->callUncached("statuses/update.json",array_merge($params,array("status" => $content)),"POST");

			if (!$response) {
				return false;
			}
			return new Tweet($response,$this);
		}

		/*
			Function: retweetTweet
				Causes the authenticated user to retweet a tweet.

			Parameters:
				id - The ID of the tweet to retweet.

			Returns:
				True if successful.
		*/

		function retweetTweet($id) {
			$response = $this->callUncached("statuses/retweet/$id.json",array(),"POST");
			if (!$response) {
				return false;
			}
			return true;
		}

		/*
			Function: searchPlaces
				Returns close places for a given latitude/longitude pair.

			Parameters:
				latitude - Latitutude
				longitude - Longitude
				count - The number of results to return (defaults to 20)
				params - Additional parameters (key/value array) to pass to the the geo/search API call.

			Returns:
				An array of BigTree\Twitter\Place objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/geo/search
		*/

		function searchPlaces($latitude,$longitude,$count = 20,$params = array()) {
			$response = $this->call("geo/search.json",array_merge($params,array("lat" => $latitude,"long" => $longitude,"max_results" => $count)));
			if (!isset($response->result)) {
				return false;
			}
			$results = array();
			foreach ($response->result->places as $place) {
				$results[] = new Place($place,$this);
			}
			return $results;
		}

		/*
			Function: searchTweets
				Searches Twitter for a given query and returns tweets.

			Parameters:
				query - String to search for.
				count - Number of results to return (defaults to 10)
				type - Whether to return "recent", "popular", or "mixed" results (defaults to recent)
				latitude - Latitude to search at (optional, for geolocation search)
				longitude - Longitude to search at (optional, for geolocation search)
				radius - How far around a given latitutde/longitude to search (in miles or kilometers, i.e. 1mi or 2km)
				params - Additional parameters (key/value array) to pass to the the search/tweets API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\Tweet objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/search/tweets
		*/

		function searchTweets($query,$count = 10,$type = "recent",$latitude = false,$longitude = false,$radius = false,$params = array()) {
			// We're saving user_params for pagination
			$user_params = $params;
			// Setup default parameters
			$params["q"] = $query;
			$params["count"] = $count;
			$params["result_type"] = $type;
			if ($latitude) {
				$params["geocode"] = "$latitude,$longitude,$radius";
			}
			$response = $this->call("search/tweets.json",$params);
			if (!$response) {
				return false;
			}
			$tweets = array();
			foreach ($response->statuses as $tweet) {
				$tweets[] = new Tweet($tweet,$this);
			}
			return new ResultSet($this,"searchTweets",array($query,$count,$type,$latitude,$longitude,$radius,$user_params),$tweets);
		}

		/*
			Function: searchUsers
				Searches Twitter for a given query and returns users.

			Parameters:
				query - String to search for.
				count - Number of results to return (max 20, default 10)
				params - Additional parameters (key/value array) to pass to the the users/search API call.

			Returns:
				A BigTree\Twitter\ResultSet of BigTree\Twitter\User objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/users/search
		*/

		function searchUsers($query,$count = 10,$params = array()) {
			// Little hack since BigTree\Twitter\ResultSet expects to use max_id but we're looking for page here, so ask for the next one.
			if ($params["page"]) {
				$params["page"]++;
			} else {
				$params["page"] = 1;
			}
			$response = $this->call("users/search.json",array_merge(array("q" => $query,"count" => $count),$params));
			if (!$response) {
				return false;
			}
			$users = array();
			foreach ($response as $user) {
				$users[] = new User($user,$this);
			}
			return new ResultSet($this,"searchUsers",array($query,$count,$params),$users);
		}

		/*
			Function: unblockUser
				Unblocks a given username by the authenticated user.

			Parameters:
				username - The username to unblock.

			Returns:
				A BigTree\Twitter\User object if successful.
		*/

		function unblockUser($username) {
			$response = $this->callUncached("blocks/destroy.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new User($response,$this);
		}
		function unblock($username) { return $this->unblockUser($username); }

		/*
			Function: unfavoriteTweet
				Unsets a tweet as a favorite of the authenticated user.

			Parameters:
				id - The tweet ID.

			Returns:
				A BigTree\Twitter\Tweet object if successful.
		*/

		function unfavoriteTweet($id) {
			$response = $this->callUncached("favorites/destroy.json",array("id" => $id),"POST");
			if (!$response) {
				return false;
			}
			return new Tweet($response,$this);
		}

		/*
			Function: unfollowUser / unfriendUser
				Unfollows/unfriends a given user by the authenticated user.

			Parameters:
				username - The username to follow/friend.

			Returns:
				A BigTree\Twitter\User object on success.
		*/

		function unfollowUser($username) {
			$response = $this->callUncached("friendships/destroy.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new User($response,$this);
		}
		function unfriendUser($username) { return $this->unfollowUser($username); }

		/*
			Function: uploadMedia
				Uploads media to Twitter's hosting service.
				Media IDs expire after 60 minutes if not used.

			Parameters:
				media - An image with maximum size of 5MB or less (PNG, JPG, WebP, GIF) or a video of 15MB or less (MP4)

			Returns:
				Media ID
		*/

		function uploadMedia($media) {
			// Different endpoint, we call this manually
			$media_response = json_decode($this->callAPI("https://upload.twitter.com/1.1/media/upload.json","POST",array("media" => "@".$media),array(),array("media")),true);
			if ($media_response["media_id"]) {
				return $media_response["media_id"];
			} else {
				$this->Errors[] = $media_response["errors"][0]["message"];
				return false;
			}
		}
	}

