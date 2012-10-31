<?
	/*
		Class: BTXTwitterAPI
			Twitter API integration.
	*/
	
	class BTXTwitterAPI {
		/*
			Constructor:
				Pass $debug as true to bypass cache.
		*/

		function __construct($debug = false) {
			global $cms;
			$this->CacheAge = 60 * 5; // 5 minutes
			$this->Debug = $debug;
		}
		
		/*
			Function: parseTimeline
				Parses a JSON Twitter timeline.

			Parameters:
				json - A JSON string of a twitter timeline or search query.
			
			Returns:
				An array with formatted tweets.
		*/

		function parseTimeline($timeline) {
			if (!is_array($timeline)) {
				$timeline = json_decode($timeline, true);
			}
			if (!$timeline) {
				return false;
			}
			if ($timeline["query"]) {
				$tweets = $timeline["results"];
			} else {
				$tweets = $timeline;
			}
			$return = array();
			for ($i = 0, $count = count($tweets); $i < $count; $i++) {
				$tweet = $tweets[$i];
				$parsed = array();
				
				$parsed["id"] = $tweet["id"];
				if ($tweet["retweeted_status"]) {
					$parsed["text"] = $this->replaceLinks("RT @" . $tweet["retweeted_status"]["user"]["screen_name"] . ": " . $tweet["retweeted_status"]["text"]);
				} else {
					$parsed["text"] = $this->replaceLinks($tweet["text"]);
				}
				$parsed["created"] = $this->relativeTime($tweet["created_at"]);
				$parsed["source"] = $tweet["source"];
				if ($tweet["from_user"]) {
					$parsed["user"] = $tweet["from_user"];
					$parsed["user_image"] = $tweet["profile_image_url"];
				}
				$parsed["original"] = $tweet;
				
				$return[] = $parsed;
			}
			if ($timeline["query"]) {
				$timeline["results"] = $return;
			} else {
				$timeline = $return;
			}
			return $timeline;
		}
		
		/*
			Function: relativeTime
				Turns a timestamp into "… hours ago" formatting.

			Parameters:
				time - A date/time stamp understandable by strtotime

			Returns:
				A string describing how long ago the passed time was.
		*/

		function relativeTime($time) {
			$second = 1;
			$minute = 60;
			$hour = 3600;
			$day = 86400;
			$month = 2592000;			
			$delta = strtotime(date('r')) - strtotime($time);
			
			if ($delta < 2 * $minute) {
				return "1 min ago";
			} elseif ($delta < 45 * $minute) {
				return floor($delta / $minute) . " min ago";
			} elseif ($delta < 90 * $minute) {
				return "1 hour ago";
			} elseif ($delta < 24 * $hour) {
				return floor($delta / $hour) . " hours ago";
			} elseif ($delta < 48 * $hour) {
				return "yesterday";
			} elseif ($delta < 30 * $day) {
				return floor($delta / $day) . " days ago";
			} elseif ($delta < 12 * $month) {
				$months = floor($delta / $day / 30);
				return $months <= 1 ? "1 month ago" : $months . " months ago";
			} else {
				$years = floor($delta / $day / 365);
				return $years <= 1 ? "1 year ago" : $years . " years ago";
			}
		}
		
		/*
			Function: replaceLinks
				Replaces links to other twitter users and hash tags with links to twitter.

			Parameters:
				text - The text to find links in.

			Returns:
				A string with HTML formatted links.
		*/

		function replaceLinks($text) {
			// RANDOM LINKS
			$text = preg_replace("@\b(https?://)?(([0-9a-zA-Z_!~*'().&=+$%-]+:)?[0-9a-zA-Z_!~*'().&=+$%-]+\@)?(([0-9]{1,3}\.){3}[0-9]{1,3}|([0-9a-zA-Z_!~*'()-]+\.)*([0-9a-zA-Z][0-9a-zA-Z-]{0,61})?[0-9a-zA-Z]\.[a-zA-Z]{2,6})(:[0-9]{1,4})?((/[0-9a-zA-Z_!~*'().;?:\@&=+$,%#-]+)*/?)@", '<a href="\0" target="_blank">\0</a>', $text);
			// USERS
			$text = preg_replace('/(^|\s)@(\w+)/','\1<a href="http://www.twitter.com/\2" target="_blank">@\2</a>', $text);
			// HASHTAGS
			$text = preg_replace('/(^|\s)#(\w+)/','\1<a href="http://search.twitter.com/search?q=%23\2" target="_blank">#\2</a>', $text);
			return $text;
		}

		/*
			Function: search
				Searches for tweets.

			Parameters:
				query - The string to search for.
				count - Maximum number of tweets to return.

			Returns:
				A decoded array of tweets.
		*/

		function search($query, $count = false) {
			// See if it's cached.
			$cache_file = SERVER_ROOT."cache/btx-twitter-".base64_encode(json_encode(array("query" => $query,"count" => $count))).".btc";
			if (!$this->Debug && file_exists($cache_file) && time() - filemtime($cache_file) < $this->CacheAge) {
				return unserialize(file_get_contents($cache_file));
			}

			$url = "https://search.twitter.com/search.json?q=" . urlencode($query);
			if ($count) {
				$url .= "&count=" . $count;
			}

			$results = $this->parseTimeline(BigTree::cURL($url));
			if ($results) {
				file_put_contents($cache_file, serialize($results));
			}
			return $results;
		}
		
		/*
			Function: timeline
				Returns the tweets for a given user.

			Parameters:
				username - The user to pull tweets for.
				count - The number of tweets to return.

			Returns:
				A decoded array of tweets.
		*/

		function timeline($username, $count = false) {
			// See if it's cached.
			$cache_file = SERVER_ROOT."cache/btx-twitter-".base64_encode(json_encode(array("username" => $username,"count" => $count))).".btc";
			if (!$this->Debug && file_exists($cache_file) && time() - filemtime($cache_file) < $this->CacheAge) {
				return unserialize(file_get_contents($cache_file));
			}

			$url = "https://api.twitter.com/1/statuses/user_timeline.json?screen_name=" . $username . "&include_rts=true&trim_user=1";
			if ($count) {
				$url .= "&count=" . $count;
			}

			$results = $this->parseTimeline(BigTree::cURL($url));
			if ($results) {
				file_put_contents($cache_file, serialize($results));
			}
			return $results;
		}
		
		/*
			Function: userInformation
				Returns user information for the provided user.

			Parameters:
				username - The username to pull information for.

			Returns:
				An array of user information.
		*/		
		function userInformation($username) {
			// See if it's cached.
			$cache_file = SERVER_ROOT."cache/btx-twitter-".base64_encode($username).".btc";
			if (!$this->Debug && file_exists($cache_file) && time() - filemtime($cache_file) < $this->CacheAge) {
				return unserialize(file_get_contents($cache_file));
			}

			$url = "http://api.twitter.com/1/users/lookup.json?screen_name=" . $username;
			if ($count) {
				$url .= "&count=" . $count;
			}

			$results = json_decode(BigTree::cURL($url),true);
			$user = $results[0];
			if ($user) {
				file_put_contents($cache_file, serialize($user));
			}
			return $user;
		}
	}
?>