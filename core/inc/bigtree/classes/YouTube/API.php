<?php
	/*
		Class: BigTree\YouTube\API
			YouTube API class that implements most API calls (media posting excluded).
	*/
	
	namespace BigTree\YouTube;
	
	use BigTree\OAuth;
	use BigTree\GoogleResultSet;
	use stdClass;
	
	class API extends OAuth
	{
		
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
		
		public function __construct(bool $cache = true)
		{
			parent::__construct("bigtree-internal-youtube-api", "YouTube API", "org.bigtreecms.api.youtube", $cache);
			
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
		
		public function createActivity(string $bulletin): ?Activity
		{
			$object = json_encode(["snippet" => ["description" => $bulletin]]);
			$response = $this->call("activities?part=snippet", $object, "POST");
			
			if (!$response->id) {
				return null;
			}
			
			return new Activity($response, $this);
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
		
		public function createPlaylist(string $title, string $description = "",string  $privacy = "public",
									   array $tags = []): ?Playlist
		{
			$object = json_encode([
				"snippet" => [
					"title" => $title,
					"description" => $description,
					"tags" => $tags
				],
				"status" => [
					"privacyStatus" => $privacy,
				]
			]);
			$response = $this->call("playlists?part=snippet,status", $object, "POST");
			
			if (!isset($response->id)) {
				return null;
			}
			
			return new Playlist($response, $this);
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
		
		public function createPlaylistItem(string $playlist, string $video, ?int $position = null, ?string $note = null,
										   ?stdClass $start_at = null, ?stdClass $end_at = null): ?PlaylistItem
		{
			$object = [
				"snippet" => [
					"playlistId" => $playlist,
					"resourceId" => ["kind" => "youtube#video", "videoId" => $video]
				],
				"contentDetails" => []
			];
			
			if (!is_null($position)) {
				$object["snippet"]["position"] = $position;
			}
			
			if (!is_null($note)) {
				$object["contentDetails"]["note"] = $note;
			}
			
			if (!is_null($start_at)) {
				$object["contentDetails"]["startAtMs"] = $this->timeJoin($start_at);
			}
			
			if (!is_null($end_at)) {
				$object["contentDetails"]["endAtMs"] = $this->timeJoin($end_at);
			}
			
			$response = $this->call("playlistItems?part=snippet,contentDetails,status", json_encode($object), "POST");
			
			if (!isset($response->id)) {
				return null;
			}
			
			return new PlaylistItem($response, $this);
		}
		
		/*
			Function: deletePlaylist
				Deletes a playlist owned by the authenticated user.

			Parameters:
				id - The ID of the playlist to delete.
		*/
		
		public function deletePlaylist(string $id): void
		{
			$this->call("playlists?id=$id", [], "DELETE");
		}
		
		/*
			Function: deletePlaylistItem
				Deletes a playlist item owned by the authenticated user.

			Parameters:
				id - The ID of the playlist item to delete.
		*/
		
		public function deletePlaylistItem(string $id): void
		{
			$this->call("playlistItems?id=$id", [], "DELETE");
		}
		
		/*
			Function: deleteVideo
				Deletes a video (must be owned by the authenticated user).

			Parameters:
				id - The ID of the video to delete.
		*/
		
		public function deleteVideo(string $id): void
		{
			$this->call("videos?id=$id", [], "DELETE");
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
		
		public function getActivities(string $channel = "home", int $count = 10, array $params = []): ?GoogleResultSet
		{
			$params = array_merge(["part" => "id,snippet,contentDetails", "maxResults" => $count], $params);
			
			if ($channel == "home") {
				$params["home"] = "true";
			} elseif ($channel == "mine") {
				$params["mine"] = "true";
			} else {
				$params["channelId"] = $channel;
			}
			
			$response = $this->call("activities", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $activity) {
				$results[] = new Activity($activity, $this);
			}
			
			return new GoogleResultSet($this, "getActivities", [$channel, $count, $params], $response, $results);
		}
		
		/*
			Function: getCategories
				Returns a list of YouTube categories.

			Parameters:
				region - ISO 3166 country code (defaults to US)

			Returns:
				A key/value array of id => category name.

			See Also:
				https://www.iso.org/iso-3166-country-codes.html

		*/
		
		public function getCategories(string $region = "US"): array
		{
			$response = $this->call("videoCategories", ["part" => "id,snippet", "regionCode" => $region]);
			$categories = [];
			
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
		
		public function getChannel(?string $username = null, ?string $id = null): ?Channel
		{
			$params = ["part" => "id,snippet,statistics,contentDetails"];
			
			if ($id) {
				$params["id"] = $id;
			} elseif ($username) {
				$params["forUsername"] = $username;
			} else {
				$params["mine"] = "true";
			}
			
			$response = $this->call("channels", $params);
			
			if (!isset($response->items[0])) {
				return null;
			}
			
			return new Channel($response->items[0], $this);
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
		
		public function getChannelVideos(string $channel, int $count = 10, string $order = "date",
										 array $params = []): ?GoogleResultSet
		{
			$channel = $this->getChannel(false, $channel);
			
			if (!isset($channel->Playlists->uploads)) {
				return null;
			}
			
			return $this->getPlaylistItems($channel->Playlists->uploads, $count, $params);
		}
		
		/*
			Function: getPlaylist
				Returns a playlist with the given ID.

			Parameters:
				id - The ID of the playlist to return.

			Returns:
				A BigTree\YouTube\Playlist object.
		*/
		
		public function getPlaylist(string $id): ?Playlist
		{
			$response = $this->call("playlists", ["part" => "id,snippet,status", "id" => $id]);
			
			if (!isset($response->items)) {
				return null;
			}
			
			return new Playlist($response->items[0], $this);
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
		
		public function getPlaylists(?string $channel = null, int $count = 50, array $params = []): ?GoogleResultSet
		{
			if ($channel) {
				$params["channelId"] = $channel;
			} else {
				$params["mine"] = "true";
			}
			
			$params["maxResults"] = $count;
			$params["part"] = "id,snippet,status";
			
			$response = $this->call("playlists", $params);
			$results = [];
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $item) {
				$results[] = new Playlist($item, $this);
			}
			
			return new GoogleResultSet($this, "getPlaylists", [$channel, $count, $params], $response, $results);
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
		
		public function getPlaylistItems(string $playlist, int $count = 50, array $params = []): ?GoogleResultSet
		{
			$results = [];
			$response = $this->call("playlistItems", array_merge([
				"part" => "id,snippet,contentDetails,status",
				 "playlistId" => $playlist,
				 "maxResults" => $count
			], $params));
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $item) {
				$results[] = new PlaylistItem($item, $this);
			}
			
			return new GoogleResultSet($this, "getPlaylistItems", [$playlist, $count, $params], $response, $results);
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
		
		public function getSubscribers(int $count = 50, string $order = "relevance",
									   array $params = []): ?GoogleResultSet
		{
			$results = [];
			$response = $this->call("subscriptions", array_merge([
				"part" => "id,snippet,contentDetails",
				 "mySubscribers" => "true",
				 "maxResults" => $count,
				 "order" => $order
			 ], $params));
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $item) {
				$results[] = $item->snippet->channelId;
			}
			
			return new GoogleResultSet($this, "getSubscribers", [$count, $order, $params], $response, $results);
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
		
		public function getSubscriptions(int $count = 50, string $order = "relevance",
										 array $params = []): ?GoogleResultSet
		{
			$results = [];
			$response = $this->call("subscriptions", array_merge([
				"part" => "id,snippet,contentDetails",
				"mine" => "true",
			 	"maxResults" => $count,
			 	"order" => $order
			 ], $params));
	
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $item) {
				$results[] = new Subscription($item, $this);
			}
			
			return new GoogleResultSet($this, "getSubscriptions", [$count, $order, $params], $response, $results);
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
		
		public function getVideo(string $id, array $params = []): ?Video
		{
			$response = $this->call("videos", array_merge([
	  			"part" => "id,snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails", 
	  			"id" => $id
			], $params));
			
			if (isset($response->items) && count($response->items)) {
				return new Video($response->items[0], $this);
			}
			
			return null;
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
		
		public function rateVideo(string $id, string $rating): bool
		{
			$response = $this->call("videos/rate?id=$id&rating=$rating", [], "POST", ["Content-length: 0"]);
			
			if ($response) {
				$this->Errors[] = ["reason" => $response[0]->reason, "message" => $response[0]->message];
				
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
		
			Returns:
				A BigTree\GoogleResultSet object of BigTree\YouTube\Channel objects.
		*/
		
		public function searchChannels(string $query, int $count = 10, string $order = "relevance",
									   array $params = []): ?GoogleResultSet
		{
			$results = [];
			$response = $this->call("search", array_merge([
			  	"part" => "snippet",
			  	"type" => "channel",
			  	"q" => $query,
			  	"order" => $order,
			  	"maxResults" => $count
		  	], $params));
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $channel) {
				$results[] = new Channel($channel, $this);
			}
			
			return new GoogleResultSet($this, "searchChannels", [$query, $order, $count, $params], $response, $results);
		}
		
		/*
			Function: searchVideos
				Searches YouTube for videos

			Parameters:
				query - A string to search for.
				order - The order to sort by (options are date, rating, relevance, title, viewCount) — defaults to relevance.
				count - Number of videos to return (defaults to 10).
				params - Additional parameters to pass to the search API call.
			
			Returns:
				A BigTree\GoogleResultSet object of BigTree\YouTube\Video objects.
		*/
		
		public function searchVideos(string $query, int $count = 10, string $order = "relevance",
									 array $params = []): ?GoogleResultSet
		{
			$results = [];
			$response = $this->call("search", array_merge([
				"part" => "snippet",
				"type" => "video",
				"q" => $query,
				"order" => $order,
				"maxResults" => $count
			], $params));
			
			if (!isset($response->items)) {
				return null;
			}
			
			foreach ($response->items as $video) {
				$results[] = new Video($video, $this);
			}
			
			return new GoogleResultSet($this, "searchVideos", [$query, $order, $count, $params], $response, $results);
		}
		
		/*
			Function: subscribe
				Subscribes the authenticated user to a given channel ID.

			Parameters:
				channel - Channel ID to subscribe to.
		*/
		
		public function subscribe(string $channel): void
		{
			$this->call("subscriptions?part=snippet", json_encode(["snippet" => ["resourceId" => ["channelId" => $channel]]]), "POST");
		}
		
		/*
			Function: timeJoin
				Joins a time object made by timeSplit into one readable by the YouTube API.
		*/
		
		public function timeJoin(stdClass $time): string
		{
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
		
		public function timeSplit(string $time): stdClass
		{
			$t = new stdClass;
			$t->Hours = 0;
			$t->Minutes = 0;
			$t->Seconds = 0;
			
			// Remove PT
			$time = substr($time, 2);
			// See if we have hours
			$h_pos = strpos($time, "H");
			
			if ($h_pos !== false) {
				$t->Hours = substr($time, 0, $h_pos);
				$time = substr($time, $h_pos + 1);
			}
			
			// See if we have minutes
			$m_pos = strpos($time, "M");
			
			if ($m_pos !== false) {
				$t->Minutes = substr($time, 0, $m_pos);
				$time = substr($time, $m_pos + 1);
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
		
		public function unsubscribe(string $channel): bool
		{
			// Get subscription ID
			$response = $this->call("subscriptions", ["part" => "id", "mine" => "true", "forChannelId" => $channel]);
			
			if (!isset($response->items)) {
				return false;
			}
			
			$this->call("subscriptions?id=".$response->items[0]->id, false, "DELETE");
			
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
		
		public function updatePlaylist(string $id, string $title, string $description = "", string $privacy = "public",
									   array $tags = []): bool
		{
			$object = json_encode([
				"id" => $id,
				"snippet" => [
					"title" => $title,
					"description" => $description,
					"tags" => $tags
				],
				"status" => [
					"privacyStatus" => $privacy,
				]
			]);
			$response = $this->call("playlists?part=snippet,status", $object, "PUT");
			
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
		
		public function updatePlaylistItem(string $item, string $playlist, string $video, ?int $position = null,
										   ?string $note = null, ?stdClass $start_at = null,
										   ?stdClass $end_at = null): ?PlaylistItem
		{
			$object = [
				"id" => $item,
				"snippet" => [
					"playlistId" => $playlist,
					"resourceId" => ["kind" => "youtube#video", "videoId" => $video]
				],
				"contentDetails" => []
			];
			
			if (!is_null($position)) {
				$object["snippet"]["position"] = $position;
			}
			
			if (!is_null($note)) {
				$object["contentDetails"]["note"] = $note;
			}
			
			if (!is_null($start_at)) {
				$object["contentDetails"]["startAtMs"] = $this->timeJoin($start_at);
			}
			
			if (!is_null($end_at)) {
				$object["contentDetails"]["endAtMs"] = $this->timeJoin($end_at);
			}
			
			$response = $this->call("playlistItems?part=snippet,contentDetails,status", json_encode($object), "PUT");
			
			if (!isset($response->id)) {
				return null;
			}
			
			return new PlaylistItem($response, $this);
		}
		
	}
	