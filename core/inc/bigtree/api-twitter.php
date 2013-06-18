<?
	/*
		Class: BigTreeTwitterAPI
			Twitter API implementation.
			All calls return false on API failure and set the "Errors" property to an array of errors returned by the Twitter API.
	*/
	
	require_once BigTree::path("inc/lib/oauth_client.php");
	
	class BigTreeTwitterAPI {
		
		var $OAuthClient;
		var $Connected = false;
		var $URL = "https://api.twitter.com/1.1/";
		var $Settings = array();
		var $Cache = true;
		
		/*
			Constructor:
				Sets up the Twitter API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the Twitter API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-twitter-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-twitter-api", 
					"name" => "Twitter API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Build OAuth client
			$this->OAuthClient = new oauth_client_class;
			$this->OAuthClient->server = "Twitter";
			$this->OAuthClient->client_id = $this->Settings["key"]; 
			$this->OAuthClient->client_secret = $this->Settings["secret"];
			$this->OAuthClient->access_token = $this->Settings["token"]; 
			$this->OAuthClient->access_token_secret = $this->Settings["token_secret"];
			$this->OAuthClient->redirect_uri = ADMIN_ROOT."developer/services/twitter/return/";
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"] && $this->Settings["token_secret"]) {
				$this->Connected = true;
			}
			
			// Init Client
			$this->OAuthClient->Initialize();
		}

		/*
			Function: call
				Calls the Twitter API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The Twitter API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$options = array()) {
			global $cms;
			if ($method != "GET") {
				return $this->callUncached($endpoint,$params,$method);				
			}

			if (!$this->Connected) {
				throw new Exception("The Twitter API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.twitter",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from Twitter normally.
					return json_decode(json_encode($record));
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.twitter",$cache_key,$response);
				}
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error,true);
				return false;
			}
		}

		/*
			Function: callUncached
				Calls the Twitter API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Twitter API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				options - Additional options to pass to OAuthClient.

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$options = array()) {
			if (!$this->Connected) {
				throw new Exception("The Twitter API is not connected.");
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,$method,$params,array_merge($options,array("FailOnAccessError" => true)),$response)) {
				return $response;
			} else {
				$this->Errors = json_decode($this->OAuthClient->api_error,true);
				return false;
			}
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
			$users = array();
			foreach ($response->users as $user) {
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			$params["cursor"] = $response->next_cursor;
			return new BigTreeTwitterResultSet($this,"getFollowers",array($username,$count,$params),$users);
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
				If no user is provided the connected user's timeline will be used.

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
			Function: postTweet
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

		function postTweet($content,$image = false,$auto_truncate = true,$params = array()) {
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
			$users = array();
			foreach ($response as $user) {
				$users[] = new BigTreeTwitterUser($user,$this);
			}
			return new BigTreeTwitterResultSet($this,"searchUsers",array($query,$count,$params),$users);
		}

	}

	/*
		Class: BigTreeTwitterResultSet
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
				A BigTreeTwitterResultSet with the next page of results.
		*/

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
	}

	/*
		Class: BigTreeTwitterTweet
	*/

	class BigTreeTwitterTweet {

		/*
			Constructor:
				Creates a tweet object from Twitter data.

			Parameters:
				tweet - Twitter data
				api - Reference to the BigTreeTwitterAPI class instance
		*/

		function __construct($tweet,&$api) {
			$this->API = $api;
			$this->Content = $tweet->text;
			$this->ID = $tweet->id;
			$this->Timestamp = $tweet->created_at;
			$this->Source = $tweet->source;
			$this->User = new BigTreeTwitterUser($tweet->user,$api);
			$this->Place = new BigTreeTwitterPlace($tweet->place,$api);
			$this->RetweetCount = $tweet->retweet_count;
			$this->FavoriteCount = $tweet->favorite_count;
			$this->Hashtags = array();
			if (is_array($tweet->entities->hashtags)) {
				foreach ($tweet->entities->hashtags as $hashtag) {
					$this->Hashtags[] = $hashtag->text;
				}
			}
			$this->Symbols = array();
			if (is_array($tweet->entities->symbols)) {
				foreach ($tweet->entities->symbols as $symbol) {
					$this->Symbols[] = $symbol->text;
				}
			}
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
			$this->Mentions = array();
			if (is_array($tweet->entities->user_mentions)) {
				foreach ($tweet->entities->user_mentions as $mention) {
					$this->Mentions[] = new BigTreeTwitterUser($mention,$api);
				}
			}
			$this->Favorited = $tweet->favorited;
			$this->Retweeted = $tweet->retweeted;
			$this->Language = $tweet->lang;
			if ($tweet->retweeted_status) {
				$this->IsRetweet = true;
				$this->OriginalTweet = new BigTreeTwitterTweet($tweet->retweeted_status,$api);
			} else {
				$this->IsRetweet = false;
			}
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
	}

	/*
		Class: BigTreeTwitterUser
	*/

	class BigTreeTwitterUser {

		/*
			Constructor:
				Creates a user object from Twitter data.

			Parameters:
				user - Twitter data
				api - Reference to the BigTreeTwiterAPI class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			$this->ID = $user->id;
			$this->Name = $user->name;
			$this->Username = $user->screen_name;
			$this->Location = $user->location;
			$this->Description = $user->description;
			$this->URL = $user->url;
			$this->Protected = $user->protected;
			$this->FollowersCount = $user->followers_count;
			$this->FriendsCount = $user->friends_count;
			$this->ListedCount = $user->listed_count;
			$this->Timestamp = $user->created_at;
			$this->Favorites = $user->favourites_count;
			$this->Timezone = $user->time_zone;
			$this->TimezoneOffset = $user->utc_offset;
			$this->GeoEnabled = $user->geo_enabled;
			$this->Verified = $user->verified;
			$this->TweetCount = $user->statuses_count;
			$this->Language = $user->lang;
			$this->Following = $user->following;
			$this->Image = $user->profile_image_url;
			$this->ImageHTTPS = $user->profile_image_url_https;
		}
	}

	/*
		Class: BigTreeTwitterPlace
	*/

	class BigTreeTwitterPlace {

		/*
			Constructor:
				Creates a place object from Twitter data.

			Parameters:
				place - Twitter data
				api - Reference to the BigTreeTwitterAPI class instance
		*/

		function __construct($place,&$api) {
			$this->API = $api;
			$this->ID = $place->id;
			$this->Name = $place->name;
			$this->FullName = $place->full_name;
			$this->Country = $place->country;
			$this->CountryCode = $place->country_code;
			$this->BoundingBox = $place->bounding_box->coordinates;
			$this->URL = $place->url;
			$this->Type = $place->place_type;
		}
	}
?>