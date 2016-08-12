<?php
	/*
		Class: BigTree\YouTube\API
			YouTube API class that implements most API calls (media posting excluded).
	*/

	namespace BigTree\YouTube;

	use BigTree\OAuth;
	use BigTree\GoogleResultSet;

	class API extends OAuth {
		
		public $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		public $EndpointURL = "https://www.googleapis.com/youtube/v3/";
		public $OAuthVersion = "2.0";
		public $RequestType = "header";
		public $Scope = "https://www.googleapis.com/auth/youtube";
		public $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
		/*
			Constructor:
				Sets up the YouTube API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			parent::__construct("bigtree-internal-youtube-api","YouTube API","org.bigtreecms.api.youtube",$cache);

			// Set OAuth Return URL
			$this->ReturnURL = ADMIN_ROOT."developer/services/youtube/return/";
		}

		/*
			Function: createActivity
				Creates a bulletin and posts it to the authenticated user's channel.

			Parameters:
				bulletin - The message to post

			Returns:
				A BigTree\YouTube\Activity object on success.
		*/

		function createActivity($bulletin) {
			$object = json_encode(array("snippet" => array("description" => $bulletin)));
			$response = $this->call("activities?part=snippet",$object,"POST");
			
			if (!$response->id) {
				return false;
			}
			
			return new Activity($response,$this);
		}

		/*
			Function: createPlaylist
				Creates a new playlist for the authenticated user.

			Parameters:
				title - The title of the playlist
				description - The description of the playlist (optional)
				privacy - The privacy status of the playlist (optional, defaults to public)
				tags - An array of tags to tag to the playlist (optional)

			Returns:
				A BigTree\YouTube\Playlist object on success.
		*/

		function createPlaylist($title,$description = "",$privacy = "public",$tags = array()) {
			$object = json_encode(array(
				"snippet" => array(
					"title" => $title,
					"description" => $description,
					"tags" => $tags
				),
				"status" => array(
					"privacyStatus" => $privacy,
				)
			));
			$response = $this->call("playlists?part=snippet,status",$object,"POST");

			if (!isset($response->id)) {
				return false;
			}

			return new Playlist($response,$this);
		}

		/*
			Function: createPlaylistItem
				Adds a video to a playlist.
				Authenticated user must be the owner of the playlist.
				*Currently note/start_at/end_at do not seem to be supported by the YouTube API.

			Parameters:
				playlist - The ID of the playlist to add the video to
				video - The ID of the video to add
				position - Position to place the video in the playlist (optional)
				note - A note to attach to the video (optional)
				start_at - Time object (stdClass with Hours, Minutes, Seconds properties) to set as the start point for the video (optional)
				end_at - Time object (stdClass with Hours, Minutes, Seconds properties) to set as the end point for the video (optional)

			Returns:
				A BigTree\YouTube\PlaylistItem object on success.
		*/

		function createPlaylistItem($playlist,$video,$position = false,$note = false,$start_at = false,$end_at = false) {
			$object = array(
				"snippet" => array(
					"playlistId" => $playlist,
					"resourceId" => array("kind" => "youtube#video","videoId" => $video)
				),
				"contentDetails" => array()
			);
			if ($position !== false) {
				$object["snippet"]["position"] = $position;
			}
			if ($note !== false) {
				$object["contentDetails"]["note"] = $note;
			}
			if ($start_at !== false) {
				$object["contentDetails"]["startAtMs"] = $this->timeJoin($start_at);
			}
			if ($end_at !== false) {
				$object["contentDetails"]["endAtMs"] = $this->timeJoin($end_at);
			}
			$response = $this->call("playlistItems?part=snippet,contentDetails,status",json_encode($object),"POST");
			
			if (!isset($response->id)) {
				return false;
			}
			
			return new PlaylistItem($response,$this);
		}

		/*
			Function: deletePlaylist
				Deletes a playlist owned by the authenticated user.

			Parameters:
				id - The ID of the playlist to delete.
		*/

		function deletePlaylist($id) {
			$this->call("playlists?id=$id",array(),"DELETE");
		}

		/*
			Function: deletePlaylistItem
				Deletes a playlist item owned by the authenticated user.

			Parameters:
				id - The ID of the playlist item to delete.
		*/

		function deletePlaylistItem($id) {
			$this->call("playlistItems?id=$id",array(),"DELETE");
		}

		/*
			Function: deleteVideo
				Deletes a video (must be owned by the authenticated user).

			Parameters:
				id - The ID of the video to delete.
		*/

		function deleteVideo($id) {
			$this->call("videos?id=$id",array(),"DELETE");
		}
		
		/*
			Function: getActivities
				Returns a list of activities for a channel.

			Parameters:
				channel - A channel ID or "home" (default) for all the channels the authenticated user follows or "mine" for the authenticated user's activities
				count - The number of activities to return (default 10, max 50)
				params - Additional parameters to pass to the activities API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\YouTube\Activity objects.
		*/

		function getActivities($channel = "home",$count = 10,$params = array()) {
			$params = array_merge(array("part" => "id,snippet,contentDetails","maxResults" => $count),$params);
			if ($channel == "home") {
				$params["home"] = "true";
			} elseif ($channel == "mine") {
				$params["mine"] = "true";
			} else {
				$params["channelId"] = $channel;
			}
			$response = $this->call("activities",$params);
			
			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $activity) {
				$results[] = new Activity($activity,$this);
			}
			
			return new GoogleResultSet($this,"getActivities",array($channel,$count,$params),$response,$results);
		}

		/*
			Function: getCategories
				Returns a list of YouTube categories.

			Parameters:
				region - ISO 3166 country code (defaults to US)

			Returns:
				A key/value array of id => category name.

			See Also:
				http://www.iso.org/iso/country_codes/iso_3166_code_lists/country_names_and_code_elements.htm

		*/

		function getCategories($region = "US") {
			$response = $this->call("videoCategories",array("part" => "id,snippet","regionCode" => $region));
			
			$categories = array();
			foreach ($response->items as $item) {
				$categories[$item->id] = $item->snippet->title;
			}
			
			return $categories;
		}

		/*
			Function: getChannel
				Returns channel information for a given ID or username.
				If neither a username or password is provided, the authenticated user's channel is returned.

			Parameters:
				username - The channel username (optional)
				id - The channel ID (optional)

			Returns:
				A BigTree\YouTube\Channel object.
		*/

		function getChannel($username = false,$id = false) {
			$params = array("part" => "id,snippet,statistics");
			if ($id) {
				$params["id"] = $id;
			} elseif ($username) {
				$params["forUsername"] = $username; 
			} else {
				$params["mine"] = "true";
			}
			$response = $this->call("channels",$params);
			
			if (!isset($response->items[0])) {
				return false;
			}
			
			return new Channel($response->items[0],$this);
		}

		/*
			Function: getChannelVideos
				Returns the videos for a given channel ID.

			Parameters:
				channel - The channel ID to retrieve videos for.
				count - Number of videos to return (defaults to 10).
				order - The order to sort by (options are date, rating, relevance, title, viewCount) — defaults to date.
				params - Additional parameters to pass to the search API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\YouTube\Video objects.
		*/

		function getChannelVideos($channel,$count = 10,$order = "date",$params = array()) {
			$response = $this->call("search",array_merge(array(
				"part" => "snippet",
				"type" => "video",
				"channelId" => $channel,
				"order" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $video) {
				$results[] = new Video($video,$this);
			}
			
			return new GoogleResultSet($this,"getChannelVideos",array($channel,$count,$order,$params),$response,$results);
		}	

		/*
			Function: getPlaylist
				Returns a playlist with the given ID.

			Parameters:
				id - The ID of the playlist to return.

			Returns:
				A BigTree\YouTube\Playlist object.
		*/

		function getPlaylist($id) {
			$response = $this->call("playlists",array("part" => "id,snippet,status","id" => $id));
			
			if (!isset($response->items)) {
				return false;
			}
			
			return new Playlist($response->items[0],$this);
		}

		/*
			Function: getPlaylists
				Returns playlists for a given channel (or the authenticated user's playlist if user is not specified)

			Parameters:
				channel - The channel ID to pull playlists for (or false to use the authenticated user's)
				count - The number of results to return per page (defaults to 50, max 50)
				params - Additional parameters to pass to the subscriptions API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\YouTube\Playlist objects.
		*/

		function getPlaylists($channel = false,$count = 50,$params = array()) {
			if ($channel) {
				$params["channelId"] = $channel;
			} else {
				$params["mine"] = "true";
			}
			$params["maxResults"] = $count;
			$params["part"] = "id,snippet,status";

			$response = $this->call("playlists",$params);
			
			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $item) {
				$results[] = new Playlist($item,$this);
			}
			
			return new GoogleResultSet($this,"getPlaylists",array($channel,$count,$params),$response,$results);
		}

		/*
			Function: getPlaylistItems
				Returns the videos in a given playlist.

			Parameters:
				playlist - The playlist ID to retrieve videos for.
				count - The number of results to return per page (defaults to 50, max 50)
				params - Additional parameters to pass to the playlistItems API call.

			Return:
				A BigTree\GoogleResultSet of BigTree\YouTube\PlaylistItem objects.
		*/

		function getPlaylistItems($playlist,$count = 50,$params = array()) {
			$response = $this->call("playlistItems",array_merge(array("part" => "id,snippet,contentDetails,status","playlistId" => $playlist,"maxResults" => $count),$params));
			
			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $item) {
				$results[] = new PlaylistItem($item,$this);
			}
			
			return new GoogleResultSet($this,"getPlaylistItems",array($playlist,$count,$params),$response,$results);
		}

		/*
			Function: getSubscribers
				Returns a list of channel IDs that are subscribed to the authenticated user.

			Parameters:
				count - The number of results to return per page (defaults to 50, max 50)
				order - Sort order (options are "alphabetical", "relevance", "unread" — defaults to "relevance")
				params - Additional parameters to pass to the subscriptions API call.

			Returns:
				A BigTree\GoogleResultSet of channel IDs.
		*/

		function getSubscribers($count = 50,$order = "relevance",$params = array()) {
			$response = $this->call("subscriptions",array_merge(array("part" => "id,snippet,contentDetails","mySubscribers" => "true","maxResults" => $count,"order" => $order),$params));
			
			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $item) {
				$results[] = $item->snippet->channelId;
			}
			
			return new GoogleResultSet($this,"getSubscribers",array($count,$order,$params),$response,$results);
		}

		/*
			Function: getSubscriptions
				Returns a list of channels the authenticated user is subscribed to.

			Parameters:
				count - The number of results to return per page (defaults to 50, max 50)
				order - Sort order (options are "alphabetical", "relevance", "unread" — defaults to "relevance")
				params - Additional parameters to pass to the videos API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\YouTube\Subscription objects.
		*/

		function getSubscriptions($count = 50,$order = "relevance",$params = array()) {
			$response = $this->call("subscriptions",array_merge(array("part" => "id,snippet,contentDetails","mine" => "true","maxResults" => $count,"order" => $order),$params));
			
			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $item) {
				$results[] = new Subscription($item,$this);
			}
			
			return new GoogleResultSet($this,"getSubscriptions",array($count,$order,$params),$response,$results);
		}

		/*
			Function: getVideo
				Gets information about a given video ID.

			Parameters:
				id - The video ID to retrieve information for.
				params - Additional parameters to pass to the videos API call.

			Returns:
				A BigTree\YouTube\Video object.
		*/

		function getVideo($id,$params = array()) {
			$response = $this->call("videos",array_merge(array(
				"part" => "id,snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails",
				"id" => $id
			),$params));
			
			if (isset($response->items) && count($response->items)) {
				return new Video($response->items[0],$this);
			}
			
			return false;
		}

		/*
			Function: rateVideo
				Causes the authenticated user to set/clear a rating on a video.

			Parameters:
				id - The video ID to rate.
				rating - "like", "dislike", or "none" (for clearing an existing rating)

			Returns:
				true on success.
		*/

		function rateVideo($id,$rating) {
			$response = $this->call("videos/rate?id=$id&rating=$rating",array(),"POST",array("Content-length: 0"));
			
			if ($response) {
				$this->Errors[] = array("reason" => $response[0]->reason, "message" => $response[0]->message);
				return false;
			} elseif ($response === false) {
				return false;
			}
			
			return true;
		}

		/*
			Function: searchChannels
				Searches YouTube for channels

			Parameters:
				query - A string to search for.
				order - The order to sort by (options are date, rating, relevance, title, videoCount, viewCount) — defaults to relevance.
				count - Number of videos to return (defaults to 10).
				params - Additional parameters to pass to the search API call.
		*/

		function searchChannels($query,$count = 10,$order = "relevance",$params = array()) {
			$response = $this->call("search",array_merge(array(
				"part" => "snippet",
				"type" => "channel",
				"q" => $query,
				"order" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $channel) {
				$results[] = new Channel($channel,$this);
			}
			
			return new GoogleResultSet($this,"searchChannels",array($query,$order,$count,$params),$response,$results);
		}
		
		/*
			Function: searchVideos
				Searches YouTube for videos

			Parameters:
				query - A string to search for.
				order - The order to sort by (options are date, rating, relevance, title, viewCount) — defaults to relevance.
				count - Number of videos to return (defaults to 10).
				params - Additional parameters to pass to the search API call.
		*/

		function searchVideos($query,$count = 10,$order = "relevance",$params = array()) {
			$response = $this->call("search",array_merge(array(
				"part" => "snippet",
				"type" => "video",
				"q" => $query,
				"order" => $order,
				"maxResults" => $count
			),$params));

			if (!isset($response->items)) {
				return false;
			}
			
			$results = array();
			foreach ($response->items as $video) {
				$results[] = new Video($video,$this);
			}
			
			return new GoogleResultSet($this,"searchVideos",array($query,$order,$count,$params),$response,$results);
		}

		/*
			Function: subscribe
				Subscribes the authenticated user to a given channel ID.

			Parameters:
				channel - Channel ID to subscribe to.
		*/

		function subscribe($channel) {
			$this->call("subscriptions?part=snippet",json_encode(array("snippet" => array("resourceId" => array("channelId" => $channel)))),"POST");
		}

		/*
			Function: timeJoin
				Joins a time object made by timeSplit into one readable by the YouTube API.
		*/

		function timeJoin($time) {
			$t = "PT";
			if ($time->Hours) {
				$t .= $time->Hours."H";
			}
			if ($time->Minutes) {
				$t .= $time->Minutes."M";
			}
			return $t.$time->Seconds."S";
		}

		/*
			Function: timeSplit
				Splits a YouTube video time length into an object.
		*/

		function timeSplit($time) {
			$t = new \stdClass;
			$t->Hours = 0;
			$t->Minutes = 0;
			$t->Seconds = 0;

			// Remove PT
			$time = substr($time,2);
			// See if we have hours
			$h_pos = strpos($time,"H");
			if ($h_pos !== false) {
				$t->Hours = substr($time,0,$h_pos);
				$time = substr($time,$h_pos + 1);
			}
			// See if we have minutes
			$m_pos = strpos($time,"M");
			if ($m_pos !== false) {
				$t->Minutes = substr($time,0,$m_pos);
				$time = substr($time,$m_pos + 1);
			}
			// Now seconds
			$t->Seconds = floatval($time);

			return $t;
		}

		/*
			Function: unsubscribe
				Unsubscribes the authenticated user from a given channel ID.

			Parameters:
				channel - Channel ID to unsubscribe from.
		*/

		function unsubscribe($channel) {
			// Get subscription ID
			$response = $this->call("subscriptions",array("part" => "id","mine" => "true","forChannelId" => $channel));
			
			if (!isset($response->items)) {
				return false;
			}
			
			$this->call("subscriptions?id=".$response->items[0]->id,false,"DELETE");
			
			return true;
		}

		/*
			Function: updatePlaylist
				Updates a playlist that is owned by the authenticated user.

			Parameters:
				id - The ID of the playlist.
				title - The new title for the playlist.
				description - The new description for the playlist (optional).
				privacy - The new privacy status for the playlist (optional, defaults to public).
				tags - The new tags for the playlist (optional, array).

			Returns:
				true on success.
		*/

		function updatePlaylist($id,$title,$description = "",$privacy = "public",$tags = array()) {
			$object = json_encode(array(
				"id" => $id,
				"snippet" => array(
					"title" => $title,
					"description" => $description,
					"tags" => $tags
				),
				"status" => array(
					"privacyStatus" => $privacy,
				)
			));
			$response = $this->call("playlists?part=snippet,status",$object,"PUT");
			
			if (!isset($response->id)) {
				return false;
			}
			
			return true;
		}

		/*
			Function: updatePlaylistItem
				Updates the details of an item in a playlist.
				Authenticated user must be the owner of the playlist.
				*Currently note/start_at/end_at do not seem to be supported by the YouTube API.

			Parameters:
				item - The ID of the playlist item to update
				playlist - The ID of the playlist this item is in
				video - The video ID for this playlist item
				position - Position to place the video in the playlist (optional)
				note - A note to attach to the video (optional)
				start_at - Time object (stdClass with Hours, Minutes, Seconds properties) to set as the start point for the video (optional)
				end_at - Time object (stdClass with Hours, Minutes, Seconds properties) to set as the end point for the video (optional)

			Returns:
				A BigTree\YouTube\PlaylistItem object on success.
		*/

		function updatePlaylistItem($item,$playlist,$video,$position = false,$note = false,$start_at = false,$end_at = false) {
			$object = array(
				"id" => $item,
				"snippet" => array(
					"playlistId" => $playlist,
					"resourceId" => array("kind" => "youtube#video","videoId" => $video)
				),
				"contentDetails" => array()
			);
			if ($position !== false) {
				$object["snippet"]["position"] = $position;
			}
			if ($note !== false) {
				$object["contentDetails"]["note"] = $note;
			}
			if ($start_at !== false) {
				$object["contentDetails"]["startAtMs"] = $this->timeJoin($start_at);
			}
			if ($end_at !== false) {
				$object["contentDetails"]["endAtMs"] = $this->timeJoin($end_at);
			}
			$response = $this->call("playlistItems?part=snippet,contentDetails,status",json_encode($object),"PUT");
			
			if (!isset($response->id)) {
				return false;
			}
			
			return new PlaylistItem($response,$this);
		}
	}

