<?
	/*
		Class: BigTreeTwitterAPI
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
			Function: callAPI
				Calls the Twitter API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The Twitter API endpoint to hit.
				params - The parameters to send to the API (key/value array).

			Returns:
				Information directly from the API.
		*/

		function callAPI($endpoint,$params = array()) {
			if (!$this->Connected) {
				throw new Exception("The Twitter API is not connected.");
			}

			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,"GET",$params,array("FailOnAccessError" => true),$response)) {
				return $response;
			} else {
				return false;
			}
		}

		/*
			Function: get
				Calls the OAuth API
		*/

		protected function get($endpoint = false, $params = array()) {
			global $cms;

			if (!$this->Connected) {
				throw new Exception("The Twitter API is not connected.");
			}

			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.twitter",$cache_key,900);
				if ($record) {
					return $record;
				}
			}
			
			if ($this->OAuthClient->CallAPI($this->URL.$endpoint,"GET",$params,array("FailOnAccessError" => true),$response)) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.twitter",$cache_key,$response);
				}
				return $response;
			} else {
				return false;
			}
		}

		/*
			Function: getMentions
				Returns the timeline of mentions for the authenticated user.
			
			Parameters:
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/mentions_timeline API call.

			Returns:
				A BigTreeTwitterResultSet object.
		*/

		function getMentions($count = 10,$params = array()) {
			$response = $this->get("statuses/mentions_timeline.json",array_merge($params,array("count" => $limit)));
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getMentions",array($count,$params),$tweets);
		}
	
		/*
			Function: getTimeline
				Returns recent tweets from the given user's timeline.
				If no user is provided the connected user's timeline will be used.

			Parameters:
				user_name - The user to retrieve tweets for  (defaults to the authenticated user)
				count - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				An array of tweets.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		*/

		function getTimeline($user_name = false, $count = 10, $params = array()) {
			$user_name = $user_name ? $user_name : $this->Settings["user_name"];
			$response = $this->get("statuses/user_timeline.json",array_merge($params,array("screen_name" => $user_name,"count" => $count)));
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return new BigTreeTwitterResultSet($this,"getTimeline",array($user_name,$count,$params),$tweets);
		}

		/*
			Function: getHomeTimeline
				Returns recent tweets from the given user's timeline.
				If no user is provided the connected user's timeline will be used.

			Parameters:
				user_name - The user to retrieve tweets for  (defaults to the authenticated user)
				limit - The number of tweets to return (defaults to 10)
				params - Additional parameters (key/value array) to pass to the the statuses/user_timeline API call.

			Returns:
				An array of tweets.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline
		*/

		function getHomeTimeline($user_name = false, $limit = 10, $params = array()) {
			$user_name = $user_name ? $user_name : $this->Settings["user_name"];
			$response = $this->get("statuses/user_timeline.json",array_merge($params,array("screen_name" => $user_name,"count" => $limit)));
			$tweets = array();
			foreach ($response as $tweet) {
				$tweets[] = new BigTreeTwitterTweet($tweet,$this);
			}
			return $tweets;
		}
		
		/*
			Function: getUserInfo 
				Return information about a given Twitter username.

			Parameters:
				user_name - The "screen name" of the Twitter user to retrieve information for (defaults to the connected user)
				params - Additional parameters (key/value array) to pass to the users/show API call.

			Returns:
				An array of user information.

			See Also:
				https://dev.twitter.com/docs/api/1.1/get/users/show
		*/

		function getUserInfo($user_name = false, $params = array()) {
			$user_name = $user_name ? $user_name : $this->Settings["user_name"];
			return $this->get("users/show",array_merge($params,array("screen_name" => $user_name)));
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
			$this->Retweets = $tweet->retweet_count;
			$this->Favorites = $tweet->favorite_count;
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
				$this->Retweet = true;
				$this->OriginalTweet = new BigTreeTwitterTweet($tweet->retweeted_status,$api);
			} else {
				$this->Retweet = false;
			}
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