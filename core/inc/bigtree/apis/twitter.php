<?
	/*
		Class: BigTreeTwitterAPI
			Twitter API class that implements most functionality (limited lists support).
			All calls return false on API failure and set the "Errors" property to an array of errors returned by the Twitter API.
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));

	class BigTreeTwitterAPI extends BigTreeOAuthAPIBase {
		
		var $EndpointURL = "https://api.twitter.com/1.1/";
		var $OAuthVersion = "1.0";
		var $RequestType = "hash";
		
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
			Function: block
				Blocks a given username by the authenticated user.

			Parameters:
				username - The username to block.

			Returns:
				A BigTreeTwitterUser object if successful.
		*/

		function block($username) {
			$response = $this->call("blocks/create.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterUser($response,$this);
		}

		/*
			Function: callUncached
				Piggybacks on the base call to provide error checking for Twitter.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
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
			$response = $this->call("direct_messages/destroy.json",array("id" => $id),"POST");
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
				A BigTreeTwitterTweet object if successful.
		*/

		function favoriteTweet($id) {
			$response = $this->callUncached("favorites/create.json",array("id" => $id),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterTweet($response,$this);
		}

		/*
			Function: followUser / friendUser
				Follows/friends a given user by the authenticated user.

			Parameters:
				username - The username to follow/friend.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function followUser($username) {
			$response = $this->call("friendships/create.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterUser($response,$this);
		}
		function friendUser($username) { return $this->followUser($username); }

		/*
			Function: getBlockedUsers
				Returns a page of users that are blocked by the authenticated user.

			Parameters:
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the blocks/list API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterUser objects.

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
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new BigTreeTwitterResultSet($this,"getBlockedUsers",array($username,$count,$params),$users);
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
				A BigTreeTwitterDirectMessage object.
			
			See Also:
				https://dev.twitter.com/docs/api/1.1/get/direct_messages
		*/

		function getDirectMessage($id) {
			$response = $this->call("direct_messages/show.json",array("id" => $id));
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterDirectMessage($response,$this);
		}

		/*
			Function: getDirectMessages
				Returns a page of direct messages sent to the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the direct_messages API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterDirectMessage objects.
			
			See Also:
				https://dev.twitter.com/docs/api/1.1/get/direct_messages
		*/

		function getDirectMessages($count = 10,$params = array()) {
			$response = $this->call("direct_messages.json",array_merge($params,array("count" => $count)));
			if (!$response) {
				return false;
			}
			$results = array();
			foreach ($response as $message) {
				$results[] = new BigTreeTwitterDirectMessage($message,$this);
			}
			return new BigTreeTwitterResultSet($this,"getDirectMessages",array($count,$params),$results);
		}

		/*
			Function: getFavoriteTweets
				Returns a page of favorite tweets of the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the favorites/list API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterTweets objects.
			
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
				$results[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getFavoriteTweets",array($count,$params),$results);
		}

		/*
			Function: getFollowers
				Returns a page of followers for a given username.

			Parameters:
				username - The username to return followers for.
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the followers/list API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterUser objects.

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
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new BigTreeTwitterResultSet($this,"getFollowers",array($username,$count,$params),$users);
		}

		/*
			Function: getFriends
				Returns a page of friends (people they follow) for a given username.

			Parameters:
				username - The username to return followers for.
				skip_status - Whether to return the user's current tweet (defaults to true, ignoring it)
				params - Additional parameters (key/value array) to pass to the the friends/list API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterUser objects.

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
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new BigTreeTwitterResultSet($this,"getFriends",array($username,$count,$params),$users);
		}

		/*
			Function: getHomeTimeline
				Returns recent tweets from the authenticated user and everyone the authenticated user follows.

			Parameters:
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterTweet objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/home_timeline
		*/

		function getHomeTimeline($count = 10, $params = array()) {
			$response = $this->call("statuses/home_timeline.json",array_merge($params,array("screen_name" => $user_name,"count" => $count)));
			if (!$response) {
				return false;
			}
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getHomeTimeline",array($count,$params),$tweets);
		}

		/*
			Function: getMentions
				Returns the timeline of mentions for the authenticated user.
			
			Parameters:
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/mentions_timeline API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterTweet objects.

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
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getMentions",array($count,$params),$tweets);
		}

		/*
			Function: getPlace
				Returns information about a place.

			Parameters:
				id - The place ID.

			Returns:
				A BigTreeTwitterPlace object.
		*/

		function getPlace($id) {
			$response = $this->call("geo/id/$id.json");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterPlace($response,$this);
		}

		/*
			Function: getSentDirectMessages
				Returns a page of direct messages sent by the authenticated user.

			Parameters:
				count - Number of results to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the direct_messages/sent API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterDirectMessage objects.
			
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
				$results[] = new BigTreeTwitterDirectMessage($message,$this);
			}
			return new BigTreeTwitterResultSet($this,"getSentDirectMessages",array($count,$params),$results);
		}

		/*
			Function: getTweet
				Returns a single tweet.

			Parameters:
				id - The ID of the tweet to return.
				params - Additional parameters (key/value array) to pass to the the statuses/show API call.

			Returns:
				A BigTreeTwitterTweet object.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/show
		*/

		function getTweet($id,$params = array()) {
			$response = $this->call("statuses/show.json",array_merge($params,array("id" => $id)));
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterTweet($response,$this);
		}

		/*
			Function: getUser
				Returns information about a user.

			Parameters:
				username - The username ("screen_name") of a Twitter user
				id - The ID of the Twitter user (replaces username if provided)

			Returns:
				A BigTreeTwitterUser object.

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
				return new BigTreeTwitterUser($response,$this);
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
				A BigTreeTwitterResultSet of BigTreeTwitterTweet objects.

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
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getUserTimeline",array($user_name,$count,$params),$tweets);
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
				BigTree::redirect(ADMIN_ROOT."developer/services/twitter/");
			}
			BigTree::redirect("https://api.twitter.com/oauth/authenticate?oauth_token=$oauth_token");
		}

		/*
			Function: oAuthSetToken
				Sets token information (or an error) when provided a response code.

			Returns:
				A stdClass object of information if successful.
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
				A BigTreeTwitterDirectMessage object or false if the direct message fails or is too long.
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
			return new BigTreeTwitterDirectMessage($response,$this);
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
				A BigTreeTwitterTweet object or false if the tweet fails or is too long.
				$this->TweetLength will be set to the length of the tweet if it is > 140 characters.

			See Also:
				https://dev.twitter.com/docs/api/1.1/post/statuses/update
		*/

		function sendTweet($content,$image = false,$auto_truncate = true,$params = array()) {
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
			
			// With image, we call statuses/update_with_media
			if ($image) {
				$response = $this->callUncached("statuses/update_with_media.json",array_merge($params,array("status" => $content,"media[]" => $image)),"POST",array("Files" => array("media[]" => array())));
			} else {
				$response = $this->callUncached("statuses/update.json",array_merge($params,array("status" => $content)),"POST");
			}
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterTweet($response,$this);
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
				An array of BigTreeTwitterPlace objects.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/geo/search
		*/

		function searchPlaces($latitude,$longitude,$count = 20,$params = array()) {
			$response = $this->call("geo/search.json",array_merge(array("lat" => $latitude,"long" => $longitude,"max_results" => $count)));
			if (!isset($response->result)) {
				return false;
			}
			$results = array();
			foreach ($response->result->places as $place) {
				$results[] = new BigTreeTwitterPlace($place,$this);
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
				A BigTreeTwitterResultSet of BigTreeTwitterTweet objects.

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
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"searchTweets",array($query,$count,$type,$latitude,$long,$radius,$user_params),$tweets);
		}

		/*
			Function: searchUsers
				Searches Twitter for a given query and returns users.

			Parameters:
				query - String to search for.
				count - Number of results to return (max 20, default 10)
				params - Additional parameters (key/value array) to pass to the the users/search API call.

			Returns:
				A BigTreeTwitterResultSet of BigTreeTwitterUser objects.
			
			See Also:
				https://dev.twitter.com/docs/api/1.1/get/users/search
		*/

		function searchUsers($query,$count = 10,$params = array()) {
			// Little hack since BigTreeTwitterResultSet expects to use max_id but we're looking for page here, so ask for the next one.
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
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			return new BigTreeTwitterResultSet($this,"searchUsers",array($query,$count,$params),$users);
		}

		/*
			Function: unblockUser
				Unblocks a given username by the authenticated user.

			Parameters:
				username - The username to unblock.

			Returns:
				A BigTreeTwitterUser object if successful.
		*/

		function unblockUser($username) {
			$response = $this->call("blocks/destroy.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterUser($response,$this);
		}

		/*
			Function: unfavoriteTweet
				Unsets a tweet as a favorite of the authenticated user.

			Parameters:
				id - The tweet ID.

			Returns:
				A BigTreeTwitterTweet object if successful.
		*/

		function unfavoriteTweet($id) {
			$response = $this->callUncached("favorites/destroy.json",array("id" => $id),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterTweet($response,$this);
		}

		/*
			Function: unfollowUser / unfriendUser
				Unfollows/unfriends a given user by the authenticated user.

			Parameters:
				username - The username to follow/friend.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function unfollowUser($username) {
			$response = $this->call("friendships/destroy.json",array("screen_name" => $username),"POST");
			if (!$response) {
				return false;
			}
			return new BigTreeTwitterUser($response,$this);
		}
		function unfriendUser($username) { return $this->unfollowUser($username); }
	}

	/*
		Class: BigTreeTwitterResultSet
			An object that contains multiple results from a Twitter API query.
	*/

	class BigTreeTwitterResultSet {

		/*
			Constructor:
				Creates a result set of Twitter data.

			Parameters:
				api - An instance of BigTreeTwitterAPI
				last_call - Method called on BigTreeTwitterAPI
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$results) {
			$this->API = $api;
			$this->LastCall = $last_call;
			$last = end($results);
			// Set the max_id field on what would be the $params array sent to any call (since it's always last)
			$params[count($params) - 1]["max_id"] = $last->ID - 1;
			$this->LastParameters = $params;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Calls the previous method with a max_id of the last received ID.

			Returns:
				A BigTreeTwitterResultSet with the next page of results.
		*/

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
	}

	/*
		Class: BigTreeTwitterTweet
			A Twitter object that contains information about and methods you can perform on a tweet.
	*/

	class BigTreeTwitterTweet {
		protected $API;

		/*
			Constructor:
				Creates a tweet object from Twitter data.

			Parameters:
				tweet - Twitter data
				api - Reference to the BigTreeTwitterAPI class instance
		*/

		function __construct($tweet,&$api) {
			$this->API = $api;
			isset($tweet->text) ? $this->Content = $tweet->text : false;
			isset($tweet->favorite_count) ? $this->FavoriteCount = $tweet->favorite_count : false;
			isset($tweet->favorited) ? $this->Favorited = $tweet->favorited : false;
			if (isset($tweet->entities->hashtags)) {
				$this->Hashtags = array();
				if (is_array($tweet->entities->hashtags)) {
					foreach ($tweet->entities->hashtags as $hashtag) {
						$this->Hashtags[] = $hashtag->text;
					}
				}
			}
			$this->ID = $tweet->id;
			isset($tweet->retweeted_status) ? ($this->IsRetweet = $tweet->retweeted_status ? true : false) : false;
			isset($tweet->lang) ? $this->Language = $tweet->lang : false;
			isset($tweet->text) ? $this->LinkedContent = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://twitter.com/search?q=%23\2" target="_blank">#\2</a>',preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>',preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@",'<a href="\0" target="_blank">\0</a>',$tweet->text))) : false;
			if (isset($tweet->entities->media)) {
				$this->Media = array();
				if (is_array($tweet->entities->media)) {
					foreach ($tweet->entities->media as $media) {
						$m = new stdClass;
						$m->DisplayURL = $media->display_url;
						$m->ID = $media->id;
						$m->ExpandedURL = $media->expanded_url;
						$m->SecureURL = $media->media_url_https;
						foreach ($media->sizes as $size => $info) {
							$size_key = ucwords($size);
							$m->Sizes = new stdClass;
							$m->Sizes->$size_key = new stdClass;
							$m->Sizes->$size_key->Height = $info->h;
							$m->Sizes->$size_key->Width = $info->w;
							$m->Sizes->$size_key->SecureURL = $media->media_url_https.":".$size;
							$m->Sizes->$size_key->URL = $media->media_url.":".$size;
						}
						$m->Type = $media->type;
						$m->URL = $media->media_url;
						$this->Media[] = $m;
					}
				}
			}
			if (isset($tweet->entities->user_mentions)) {
				$this->Mentions = array();
				if (is_array($tweet->entities->user_mentions)) {
					foreach ($tweet->entities->user_mentions as $mention) {
						$this->Mentions[] = new BigTreeTwitterUser($mention,$api);
					}
				}
			}
			$tweet->retweeted_status ? $this->OriginalTweet = new BigTreeTwitterTweet($tweet->retweeted_status,$api) : false;
			isset($tweet->place) ? $this->Place = new BigTreeTwitterPlace($tweet->place,$api) : false;
			isset($tweet->retweet_count) ? $this->RetweetCount = $tweet->retweet_count : false;
			isset($tweet->retweeted) ? $this->Retweeted = $tweet->retweeted : false;
			isset($tweet->source) ? $this->Source = $tweet->source : false;
			if (isset($tweet->entities->symbols)) {
				$this->Symbols = array();
				if (is_array($tweet->entities->symbols)) {
					foreach ($tweet->entities->symbols as $symbol) {
						$this->Symbols[] = $symbol->text;
					}
				}
			}
			isset($tweet->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($tweet->created_at)) : false;
			if (isset($tweet->entities->url)) {
				$this->URLs = array();
				if (is_array($tweet->entities->url)) {
					foreach ($tweet->entities->urls as $url) {
						$this->URLs[] = (object) array(
							"URL" => $url->url,
							"ExpandedURL" => $url->expanded_url,
							"DisplayURL" => $url->display_url
						);
					}
				}
			}
			isset($tweet->user) ? $this->User = new BigTreeTwitterUser($tweet->user,$api) : false;
		}

		/*
			Function: __toString
				Returns the Tweet's content when this object is treated as a string.
		*/

		function __toString() {
			return $this->Content;
		}

		/*
			Function: delete
				Deletes the tweet from Twitter.
				The authenticated user must own the tweet.

			Returns:
				True if successful.
		*/

		function delete() {
			return $this->API->deleteTweet($this->ID);
		}

		/*
			Function: favorite
				Favorites the tweet.

			Returns:
				A BigTreeTwitterTweet object if successful.
		*/

		function favorite() {
			return $this->API->favoriteTweet($this->ID);
		}

		/*
			Function: retweet
				Causes the authenticated user to retweet the tweet.

			Returns:
				True if successful.
		*/

		function retweet($id = false) {
			return $this->API->retweetTweet($this->IsRetweet ? $this->OriginalTweet->ID : $this->ID);
		}

		/*
			Function: retweets
				Returns retweets of the tweet.

			Parameters:
				count - The number of retweets to return (defaults to 10, max 100)

			Returns:
				An array of BigTreeTwitterTweet objects.
		*/

		function retweets($count = 10) {
			// We know how many retweets the tweet has already, so don't bother asking Twitter if it's 0.
			if (!$this->RetweetCount) {
				return array();
			}
			if ($this->OriginalTweet) {
				$response = $this->API->call("statuses/retweets/".$this->OriginalTweet->ID.".json");
			} else {
				$response = $this->API->call("statuses/retweets/".$this->ID.".json");
			}
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new BigTreeTwitterTweet($tweet,$this->API);
			}
			return $tweets;
		}

		/*
			Function: retweeters
				Returns a list of Twitter user IDs for users who retweeted this tweet.

			Returns:
				An array of Twitter IDs
		*/

		function retweeters() {
			$id = $this->IsRetweet ? $this->OriginalTweet->ID : $id = $this->ID;
			$response = $this->API->call("statuses/retweeters/ids.json",array("id" => $id));
			if ($response->ids) {
				return $response->ids;
			}
			return false;
		}

		/*
			Function: unfavorite
				Unfavorites the tweet.

			Returns:
				A BigTreeTwitterTweet object if successful.
		*/

		function unfavorite() {
			return $this->API->unfavoriteTweet($this->ID);
		}
	}

	/*
		Class: BigTreeTwitterUser
			A Twitter object that contains information about and methods you can perform on a user.
	*/

	class BigTreeTwitterUser {
		protected $API;

		/*
			Constructor:
				Creates a user object from Twitter data.

			Parameters:
				user - Twitter data
				api - Reference to the BigTreeTwiterAPI class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			isset($user->description) ? $this->Description = $user->description : false;
			isset($user->favourites_count) ? $this->Favorites = $user->favourites_count : false;
			isset($user->followers_count) ? $this->FollowersCount = $user->followers_count : false;
			isset($user->following) ? $this->Following = $user->following : false;
			isset($user->friends_count) ? $this->FriendsCount = $user->friends_count : false;
			isset($user->geo_enabled) ? $this->GeoEnabled = $user->geo_enabled : false;
			isset($user->id) ? $this->ID = $user->id : false;
			isset($user->profile_image_url) ? $this->Image = $user->profile_image_url : false;
			isset($user->profile_image_url_https) ? $this->ImageHTTPS = $user->profile_image_url_https : false;
			isset($user->lang) ? $this->Language = $user->lang : false;
			isset($user->listed_count) ? $this->ListedCount = $user->listed_count : false;
			isset($user->location) ? $this->Location = $user->location : false;
			isset($user->name) ? $this->Name = $user->name : false;
			isset($user->protected) ? $this->Protected = $user->protected : false;
			isset($user->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($user->created_at)) : false;
			isset($user->time_zone) ? $this->Timezone = $user->time_zone : false;
			isset($user->utc_offset) ? $this->TimezoneOffset = $user->utc_offset : false;
			isset($user->statuses_count) ? $this->TweetCount = $user->statuses_count : false;
			isset($user->screen_name) ? $this->Username = $user->screen_name : false;
			isset($user->url) ? $this->URL = $user->url : false;
			isset($user->verified) ? $this->Verified = $user->verified : false;
		}

		/*
			Function: __toString
				Returns the User's username when this object is treated as a string.
		*/

		function __toString() {
			return $this->Username;
		}

		/*
			Function: block
				Blocks the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function block() {
			return $this->API->blockUser($this->ID);
		}

		/*
			Function: follow / friend
				Friends/follows the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function follow() {
			return $this->API->followUser($this->ID);
		}
		function friend() {
			return $this->follow();
		}

		/*
			Function: unblock
				Unblocks the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/
				
		function unblock() {
			return $this->API->unblockUser($this->ID);
		}

		/*
			Function: unfollow / unfriend
				Unfriends/unfollows the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function unfollow() {
			return $this->API->unfollowUser($this->ID);
		}
		function unfriend() {
			return $this->unfollow();
		}			
	}

	/*
		Class: BigTreeTwitterPlace
			A Twitter object that contains information about and methods you can perform on a place.
	*/

	class BigTreeTwitterPlace {
		protected $API;

		/*
			Constructor:
				Creates a place object from Twitter data.

			Parameters:
				place - Twitter data
				api - Reference to the BigTreeTwitterAPI class instance
		*/

		function __construct($place,&$api) {
			$this->API = $api;
			isset($place->bounding_box->coordinates) ? $this->BoundingBox = $place->bounding_box->coordinates : false;
			isset($place->country) ? $this->Country = $place->country : false;
			isset($place->country_code) ? $this->CountryCode = $place->country_code : false;
			isset($place->full_name) ? $this->FullName = $place->full_name : false;
			isset($place->id) ? $this->ID = $place->id : false;
			isset($place->name) ? $this->Name = $place->name : false;
			isset($place->place_type) ? $this->Type = $place->place_type : false;
			isset($place->url) ? $this->URL = $place->url : false;
		}

		/*
			Function: __toString
				Returns the Places's name when this object is treated as a string.
		*/

		function __toString() {
			return $this->Name;
		}
	}

	/*
		Class: BigTreeTwitterDirectMessage
			A Twitter object that contains information about and methods you can perform on a direct message.
	*/

	class BigTreeTwitterDirectMessage {
		protected $API;

		/*
			Constructor:
				Create a direct message object from Twitter data.

			Parameters:
				message - Twitter data
				api - Reference to BigTreeTwitterAPI class instance
		*/

		function __construct($message,&$api) {
			$this->API = $api;
			isset($message->text) ? $this->Content = $message->text : false;
			isset($message->id) ? $this->ID = $message->id : false;
			isset($message->text) ? $this->LinkedContent = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://search.twitter.com/search?q=%23\2" target="_blank">#\2</a>',preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>',preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@",'<a href="\0" target="_blank">\0</a>',$message->text))) : false;
			isset($message->recipient) ? $this->Recipient = new BigTreeTwitterUser($message->recipient,$api) : false;
			isset($message->sender) ? $this->Sender = new BigTreeTwitterUser($message->sender,$api) : false;
			isset($message->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($message->created_at)) : false;
		}

		/*
			Function: __toString
				Returns the Message's content when this object is treated as a string.
		*/

		function __toString() {
			return $this->Content;
		}

		/*
			Function: delete
				Alias for BigTreeTwitterTweet::deleteDirectMessage
		*/

		function delete() {
			return $this->API->deleteDirectMessage($this->ID);
		}

		/*
			Function: reply
				Alias for BigTreeTwitterTweet::sendDirectMessage
		*/

		function reply($content) {
			return $this->API->sendDirectMessage(false,$content,$this->Sender->ID);
		}
	}
?>