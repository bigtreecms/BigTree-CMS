<?
	/*
		Class: BigTreeYouTubeAPI
			YouTube API class that implements most API calls (media posting excluded).
	*/
	
	require_once(BigTree::path("inc/bigtree/apis/_oauth.base.php"));
	require_once(BigTree::path("inc/bigtree/apis/_google.result-set.php"));

	class BigTreeYouTubeAPI extends BigTreeOAuthAPIBase {
		
		var $AuthorizeURL = "https://accounts.google.com/o/oauth2/auth";
		var $EndpointURL = "https://www.googleapis.com/youtube/v3/";
		var $OAuthVersion = "2.0";
		var $RequestType = "header";
		var $Scope = "https://www.googleapis.com/auth/youtube";
		var $TokenURL = "https://accounts.google.com/o/oauth2/token";
		
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
				A BigTreeYouTubeActivity object on success.
		*/

		function createActivity($bulletin,$channel = false) {
			$object = json_encode(array("snippet" => array("description" => $bulletin)));
			$response = $this->call("activities?part=snippet",$object,"POST");
			if (!$response->id) {
				return false;
			}
			return new BigTreeYouTubeActivity($response,$this);
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
				A BigTreeYouTubePlaylist object on success.
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
			return new BigTreeYouTubePlaylist($response,$this);
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
				A BigTreeYouTubePlaylistItem object on success.
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
			return new BigTreeYouTubePlaylistItem($response,$this);
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
				A BigTreeGoogleResultSet of BigTreeYouTubeActivity objects.
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
				$results[] = new BigTreeYouTubeActivity($activity,$this);
			}
			return new BigTreeGoogleResultSet($this,"getActivities",array($channel,$count,$params),$response,$results);
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
				A BigTreeYouTubeChannel object.
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
			return new BigTreeYouTubeChannel($response->items[0],$this);
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
				A BigTreeGoogleResultSet of BigTreeYouTubeVideo objects.
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
				$results[] = new BigTreeYouTubeVideo($video,$this);
			}
			return new BigTreeGoogleResultSet($this,"getChannelVideos",array($query,$order,$count,$params),$response,$results);
		}	

		/*
			Function: getPlaylist
				Returns a playlist with the given ID.

			Parameters:
				id - The ID of the playlist to return.

			Returns:
				A BigTreeYouTubePlaylist object.
		*/

		function getPlaylist($id) {
			$response = $this->call("playlists",array("part" => "id,snippet,status","id" => $id));
			if (!isset($response->items)) {
				return false;
			}
			return new BigTreeYouTubePlaylist($response->items[0],$this);
		}

		/*
			Function: getPlaylists
				Returns playlists for a given channel (or the authenticated user's playlist if user is not specified)

			Parameters:
				channel - The channel ID to pull playlists for (or false to use the authenticated user's)
				count - The number of results to return per page (defaults to 50, max 50)
				params - Additional parameters to pass to the subscriptions API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeYouTubePlaylist objects.
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
				$results[] = new BigTreeYouTubePlaylist($item,$this);
			}
			return new BigTreeGoogleResultSet($this,"getPlaylists",array($channel,$count,$params),$response,$results);
		}

		/*
			Function: getPlaylistItems
				Returns the videos in a given playlist.

			Parameters:
				playlist - The playlist ID to retrieve videos for.
				count - The number of results to return per page (defaults to 50, max 50)
				params - Additional parameters to pass to the playlistItems API call.

			Return:
				A BigTreeGoogleResultSet of BigTreeYouTubePlaylistItem objects.
		*/

		function getPlaylistItems($playlist,$count = 50,$params = array()) {
			$response = $this->call("playlistItems",array_merge(array("part" => "id,snippet,contentDetails,status","playlistId" => $playlist,"maxResults" => $count),$params));
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $item) {
				$results[] = new BigTreeYouTubePlaylistItem($item,$this);
			}
			return new BigTreeGoogleResultSet($this,"getPlaylistItems",array($playlist,$count,$params),$response,$results);
		}

		/*
			Function: getSubscribers
				Returns a list of channel IDs that are subscribed to the authenticated user.

			Parameters:
				count - The number of results to return per page (defaults to 50, max 50)
				order - Sort order (options are "alphabetical", "relevance", "unread" — defaults to "relevance")
				params - Additional parameters to pass to the subscriptions API call.

			Returns:
				A BigTreeGoogleResultSet of channel IDs.
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
			return new BigTreeGoogleResultSet($this,"getSubscribers",array($count,$order,$params),$response,$results);
		}

		/*
			Function: getSubscriptions
				Returns a list of channels the authenticated user is subscribed to.

			Parameters:
				count - The number of results to return per page (defaults to 50, max 50)
				order - Sort order (options are "alphabetical", "relevance", "unread" — defaults to "relevance")
				params - Additional parameters to pass to the videos API call.

			Returns:
				A BigTreeGoogleResultSet of BigTreeYouTubeSubscription objects.
		*/

		function getSubscriptions($count = 50,$order = "relevance",$params = array()) {
			$response = $this->call("subscriptions",array_merge(array("part" => "id,snippet,contentDetails","mine" => "true","maxResults" => $count,"order" => $order),$params));
			if (!isset($response->items)) {
				return false;
			}
			$results = array();
			foreach ($response->items as $item) {
				$results[] = new BigTreeYouTubeSubscription($item,$this);
			}
			return new BigTreeGoogleResultSet($this,"getSubscriptions",array($count,$order,$params),$response,$results);
		}

		/*
			Function: getVideo
				Gets information about a given video ID.

			Parameters:
				id - The video ID to retrieve information for.
				params - Additional parameters to pass to the videos API call.

			Returns:
				A BigTreeYouTubeVideo object.
		*/

		function getVideo($id,$params = array()) {
			$response = $this->call("videos",array_merge(array(
				"part" => "id,snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails",
				"id" => $id
			),$params));
			if (isset($response->items) && count($response->items)) {
				return new BigTreeYouTubeVideo($response->items[0],$this);
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
				$results[] = new BigTreeYouTubeChannel($channel,$this);
			}
			return new BigTreeGoogleResultSet($this,"searchChannels",array($query,$order,$count,$params),$response,$results);
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
				$results[] = new BigTreeYouTubeVideo($video,$this);
			}
			return new BigTreeGoogleResultSet($this,"searchVideos",array($query,$order,$count,$params),$response,$results);
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

		protected function timeJoin($time) {
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

		protected function timeSplit($time) {
			$t = new stdClass;
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
				A BigTreeYouTubePlaylistItem object on success.
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
			return new BigTreeYouTubePlaylistItem($response,$this);
		}
	}

	/*
		Class: BigTreeYouTubeActivity
			A YouTube object that contains information about and methods you can perform on an activity.
	*/

	class BigTreeYouTubeActivity {
		protected $API;

		function __construct($activity,&$api) {
			$type = $activity->snippet->type;

			$this->API = $api;
			isset($activity->snippet->channelId) ? $this->ChannelID = $activity->snippet->channelId : false;
			isset($activity->snippet->channelTitle) ? $this->ChannelTitle = $activity->snippet->channelTitle : false;
			if ($type == "comment") {
				$this->Comment = new stdClass;
				$this->Comment->ChannelID = $activity->contentDetails->comment->resourceId->channelId;
				$this->Comment->VideoID = $activity->contentDetails->comment->resourceId->videoId;
			}
			isset($activity->snippet->description) ? $this->Description = $activity->snippet->description : false;
			if ($type == "favorite") {
				$this->Favorite = new stdClass;
				$this->Favorite->VideoID = $activity->contentDetails->favorite->resourceId->videoId;
			}
			isset($activity->snippet->groupId) ? $this->GroupID = $activity->snippet->groupId : false;
			$this->ID = $activity->id;
			if (isset($activity->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($activity->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			if ($type == "like") {
				$this->Like = new stdClass;
				$this->Like->VideoID = $activity->contentDetails->like->resourceId->videoId;
			}
			if ($type == "playlistItem") {
				$this->PlaylistItem = new stdClass;
				$this->PlaylistItem->ID = $activity->contentDetails->playlistItem->playlistItemId;
				$this->PlaylistItem->PlaylistID = $activity->contentDetails->playlistItem->playlistId;
				$this->PlaylistItem->VideoID = $activity->contentDetails->playlistItem->resourceId->videoId;
			}
			if ($type == "recommendation") {
				$this->Recommendation = new stdClass;
				isset($activity->contentDetails->recommendation->resourceId->channelId) ? $this->Recommendation->ChannelID = $activity->contentDetails->recommendation->resourceId->channelId : false;
				$this->Recommendation->Reason = new stdClass;
				$this->Recommendation->Reason->Action = $activity->contentDetails->recommendation->reason;
				isset($activity->contentDetails->recommendation->seedResourceId->channelId) ? $this->Recommendation->Reason->ChannelID = $activity->contentDetails->recommendation->seedResourceId->channelId : false;
				isset($activity->contentDetails->recommendation->seedResourceId->videoId) ? $this->Recommendation->Reason->VideoID = $activity->contentDetails->recommendation->seedResourceId->videoId : false;
				isset($activity->contentDetails->recommendation->resourceId->videoId) ? $this->Recommendation->VideoID = $activity->contentDetails->recommendation->resourceId->videoId : false;
			}
			if ($type == "social") {
				$this->Social = new stdClass;
				isset($activity->contentDetails->social->author) ? $this->Social->Author = $activity->contentDetails->social->author : false;
				isset($activity->contentDetails->social->resourceId->channelId) ? $this->Social->ChannelID = $activity->contentDetails->social->resourceId->channelId : false;
				isset($activity->contentDetails->social->imageUrl) ? $this->Social->ImageURL = $activity->contentDetails->social->imageUrl : false;
				isset($activity->contentDetails->social->resourceId->playlistId) ? $this->Social->PlaylistID = $activity->contentDetails->social->resourceId->playlistId : false;
				isset($activity->contentDetails->social->referenceUrl) ? $this->Social->ReferenceURL = $activity->contentDetails->social->referenceUrl : false;
				$this->Social->Type = $activity->contentDetails->social->type;
				isset($activity->contentDetails->social->resourceId->videoId) ? $this->Social->VideoID = $activity->contentDetails->social->resourceId->videoId : false;					
			}
			if ($type == "subscription") {
				$this->Subscription = new stdClass;
				$this->Subscription->ChannelID = $activity->contentDetails->subscription->channelId;
			}
			isset($activity->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($activity->snippet->publishedAt)) : false;
			isset($activity->snippet->title) ? $this->Title = $activity->snippet->title : false;
			$this->Type = $activity->snippet->type;
			if ($type == "upload") {
				$this->Upload = new stdClass;
				$this->Upload->VideoID = $activity->contentDetails->upload->videoId;
			}
		}
	}

	/*
		Class: BigTreeYouTubeChannel
			A YouTube object that contains information about and methods you can perform on a channel.
	*/

	class BigTreeYouTubeChannel {
		protected $API;

		function __construct($channel,&$api) {
			$this->API = $api;
			isset($channel->statistics->commentCount) ? $this->CommentCount = $channel->statistics->commentCount : false;
			isset($channel->snippet->description) ? $this->Description = $channel->snippet->description : false;
			$this->ID = is_object($channel->id) ? $channel->id->channelId : $channel->id;
			if (isset($channel->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($channel->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			isset($channel->statistics->subscriberCount) ? $this->SubscriberCount = $channel->statistics->subscriberCount : false;
			isset($channel->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($channel->snippet->publishedAt)) : false;
			isset($channel->snippet->title) ? $this->Title = $channel->snippet->title : false;
			isset($channel->statistics->videoCount) ? $this->VideoCount = $channel->statistics->videoCount : false;
			isset($channel->statistics->viewCount) ? $this->ViewCount = $channel->statistics->viewCount : false;
		}

		/*
			Function: getVideos
				Returns the videos for this channel.

			Parameters:
				order - The order to sort by (options are date, rating, relevance, title, viewCount) — defaults to date.
				count - Number of videos to return (defaults to 10).

			Returns:
				A BigTreeGoogleResultSet of BigTreeYouTubeVideo objects.
		*/

		function getVideos($count = 10,$order = "date") {
			return $this->API->getChannelVideos($this->ID,$order,$count);
		}

		/*
			Function: subscribe
				Subscribes the authenticated user to the channel.
		*/

		function subscribe() {
			return $this->API->subscribe($this->ID);
		}

		/*
			Function: unsubscribe
				Unsubscribes the authenticated user from the channel.
		*/

		function unsubscribe() {
			return $this->API->unsubscribe($this->ID);
		}
	}

	/*
		Class: BigTreeYouTubePlaylist
			A YouTube object that contains information about and methods you can perform on a playlist.
	*/

	class BigTreeYouTubePlaylist {
		protected $API;

		function __construct($playlist,&$api) {
			$this->API = $api;
			isset($playlist->snippet->channelId) ? $this->ChannelID = $playlist->snippet->channelId : false;
			isset($playlist->snippet->channelTitle) ? $this->ChannelTitle = $playlist->snippet->channelTitle : false;
			isset($playlist->snippet->description) ? $this->Description = $playlist->snippet->description : false;
			$this->ID = $playlist->id;
			if (isset($playlist->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($playlist->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			isset($playlist->status->privacyStatus) ? $this->Privacy = $playlist->status->privacyStatus : false;
			isset($playlist->snippet->tags) ? $this->Tags = $playlist->snippet->tags : false;
			isset($playlist->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($playlist->snippet->publishedAt)) : false;
			isset($playlist->snippet->title) ? $this->Title = $playlist->snippet->title : false;
		}

		/*
			Function: save
				Saves the changes made to this playlist (Title, Description, Privacy, Tags)
				Playlist must be owned by the authenticated user.

			Returns:
				true if successful.
		*/

		function save() {
			return $this->API->updatePlaylist($this->ID,$this->Title,$this->Description,$this->Privacy,$this->Tags);
		}

		/*
			Function: delete
				Deletes the playlist.
				Playlist must be owned by the authenticated user.
		*/

		function delete() {
			return $this->API->deletePlaylist($this->ID);
		}
	}

	/*
		Class: BigTreeYouTubePlaylistItem
			A YouTube object that contains information about and methods you can perform on a playlist item.
	*/

	class BigTreeYouTubePlaylistItem {
		protected $API;

		function __construct($item,&$api) {
			$this->API = $api;
			isset($item->snippet->channelId) ? $this->ChannelID = $item->snippet->channelId : false;
			isset($item->snippet->channelTitle) ? $this->ChannelTitle = $item->snippet->channelTitle : false;
			isset($item->snippet->description) ? $this->Description = $item->snippet->description : false;
			$this->ID = $item->id;
			if (isset($item->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($item->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			isset($item->contentDetails->note) ? $this->Note = $item->contentDetails->note : false;
			isset($item->snippet->playlistId) ? $this->PlaylistID = $item->snippet->playlistId : false;
			isset($item->snippet->position) ? $this->Position = $item->snippet->position : false;
			isset($item->status->privacyStatus) ? $this->Privacy = $item->status->privacyStatus : false;
			isset($item->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($item->snippet->publishedAt)) : false;
			isset($item->snippet->title) ? $this->Title = $item->snippet->title : false;
			isset($item->snippet->resourceId->videoId) ? $this->VideoID = $item->snippet->resourceId->videoId : false;
			isset($item->contentDetails->endAtMs) ? $this->VideoEndAt = $api->timeSplit($item->contentDetails->endAtMs) : false;
			isset($item->contentDetails->startAtMs) ? $this->VideoStartAt = $api->timeSplit($item->contentDetails->startAtMs) : false;
		}

		/*
			Function: delete
				Deletes this item from the playlist.
				Authenticated user must be the owner of the playlist.
		*/

		function delete() {
			return $this->API->deletePlaylistItem($this->ID);
		}

		/*
			Function: save
				Saves changed information (Note, VideoStartAt, VideoEndAt, Position)
				Authenticated user must be the owner of the playlist.
		*/

		function save() {
			return $this->API->updatePlaylistItem($this->ID,$this->PlaylistID,$this->VideoID,$this->Position,$this->Note,$this->API->timeJoin($this->VideoStartAt),$this->API->timeJoin($this->VideoEndAt));
		}

		/*
			Function: video
				Returns more information about this item's video.

			Returns:
				A BigTreeYouTubeVideo object.
		*/

		function video() {
			return $this->API->getVideo($this->VideoID);
		}
	}

	/*
		Class: BigTreeYouTubeSubscription
			A YouTube object that contains information about and methods you can perform on a subscription.
	*/

	class BigTreeYouTubeSubscription {
		protected $API;

		function __construct($subscription,&$api) {
			$this->API = $api;

			$channel = new stdClass;
			$channel->snippet = $subscription->snippet;
 			// Not correct info for the channel so we move it
 			$created_at = $channel->snippet->publishedAt;
 			unset($channel->snippet->publishedAt);
			$channel->id = $channel->snippet->resourceId->channelId;
			$this->Channel = new BigTreeYouTubeChannel($channel,$api);

			$this->ID = $subscription->id;
			$this->Timestamp = date("Y-m-d H:i:s",strtotime($created_at));
		}

		/*
			Function: delete
				Removes this subscription from the authenticated user's subscribed channels.
		*/

		function delete() {
			$this->API->call("subscriptions?id=".$this->ID,false,"DELETE");
		}
	}

	/*
		Class: BigTreeYouTubeVideo
			A YouTube object that contains information about and methods you can perform on a video.
	*/

	class BigTreeYouTubeVideo {
		protected $API;

		function __construct($video,&$api) {
			$this->API = $api;
			isset($video->contentDetails->caption) ? $this->Captioned = $video->contentDetails->caption : false;
			isset($video->snippet->categoryId) ? $this->CategoryID = $video->snippet->categoryId : false;
			isset($video->snippet->channelTitle) ? $this->ChannelTitle = $video->snippet->channelTitle : false;
			isset($video->statistics->commentCount) ? $this->CommentCount = $video->statistics->commentCount : false;
			isset($video->contentDetails->contentRating) ? $this->ContentRatings = $video->contentDetails->contentRating : false;
			isset($video->contentDetails->definition) ? $this->Definition = $video->contentDetails->definition : false;
			isset($video->snippet->description) ? $this->Description = $video->snippet->description : false;
			isset($video->contentDetails->dimension) ? $this->Dimension = $video->contentDetails->dimension : false;
			isset($video->statistics->dislikeCount) ? $this->DislikeCount = $video->statistics->dislikeCount : false;
			isset($video->contentDetails->duration) ? $this->Duration = $api->timeSplit($video->contentDetails->duration) : false;
			isset($video->player->embedHtml) ? $this->Embed = $video->player->embedHtml : false;
			isset($video->status->embeddable) ? $this->Embeddable = $video->status->embeddable : false;
			isset($video->statistics->favoriteCount) ? $this->FavoriteCount = $video->statistics->favoriteCount : false;
			$this->ID = is_string($video->id) ? $video->id : $video->id->videoId;
			if (isset($video->snippet->thumbnails)) {
				$this->Images = new stdClass;
				foreach ($video->snippet->thumbnails as $key => $val) {
					$key = ucwords($key);
					$this->Images->$key = $val->url;
				}
			}
			isset($video->status->license) ? $this->License = $video->status->license : false;
			isset($video->contentDetails->licensedContent) ? $this->LicensedContent = $video->contentDetails->licensedContent : false;
			isset($video->statistics->likeCount) ? $this->LikeCount = $video->statistics->likeCount : false;
			if (isset($video->recordingDetails->location)) {
				$this->Location = new stdClass;
				$this->Location->Latitude = $video->recordingDetails->location->latitude;
				$this->Location->Longitude = $video->recordingDetails->location->longitude;
				$this->Location->Elevation = $video->recordingDetails->location->elevation;
				$this->Location->Description = $video->recordingDetails->locationDescription;
			}
			isset($video->status->privacyStatus) ? $this->Privacy = $video->status->privacyStatus : false;
			isset($video->recordingDetails->recordingDate) ? $this->RecordedTimestamp = $video->recordingDetails->recordingDate : false;
			isset($video->snippet->tags) ? $this->Tags = $video->snippet->tags : false;
			isset($video->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($video->snippet->publishedAt)) : false;
			isset($video->snippet->title) ? $this->Title = $video->snippet->title : false;
			isset($video->status->failureReason) ? $this->UploadFailureReason = $video->status->failureReason : false;
			isset($video->status->rejectionReason) ? $this->UploadRejectionReason = $video->status->rejectionReason : false;
			isset($video->status->uploadStatus) ? $this->UploadStatus = $video->status->uploadStatus : false;
			isset($video->statistics->viewCount) ? $this->ViewCount = $video->statistics->viewCount : false;
		}

		/*
			Function: delete
				Deletes the video (must be owned by the authenticated user).

			Returns:
				true on success.
		*/

		function delete() {
			return $this->API->deleteVideo($this->ID);
		}

		/*
			Function: getDetails
				Looks up more details on this video.
				Calls other than BigTreeYouTubeAPI::getVideo will return partial video information, this call supplements the partial responses with a full response.

			Returns:
				A new BigTreeYouTubeVideo object with more details.
		*/

		function getDetails() {
			return $this->API->getVideo($this->ID);
		}

		/*
			Function: rate
				Causes the authenticated user to set/clear a rating on a video.

			Parameters:
				rating - "like", "dislike", or "none" (for clearing an existing rating)
		*/

		function rate($rating) {
			return $this->API->rateVideo($this->ID,$rating);
		}

		/*
			Function: save
				Saves changes to "snippet" related properties (video must be owned by the authenticated user)
				Properties that save are: Title, Description, Tags, CategoryID, Privacy, Embeddable, License

			Returns:
				true on success.
		*/

		function save() {
			$object = json_encode(array(
				"id" => $this->ID,
				"snippet" => array(
					"title" => $this->Title,
					"description" => $this->Description,
					"tags" => array_unique($this->Tags),
					"categoryId" => $this->CategoryID,
					"privacyStatus" => $this->Privacy,
					"embeddable" => $this->Embeddable,
					"license" => $this->License
				)
			));
			$response = $this->API->call("videos?part=snippet",$object,"PUT");
			if (isset($response->id)) {
				return true;
			}
			return false;
		}
	}
?>