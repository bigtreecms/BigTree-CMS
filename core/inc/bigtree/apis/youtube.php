<?
	/*
		Class: BigTreeYouTubeAPI
	*/
	
	class BigTreeYouTubeAPI {
		
		var $Connected = false;
		var $URL = "https://www.googleapis.com/youtube/v3/";
		var $Settings = array();
		var $Cache = true;

		/*
			Constructor:
				Sets up the YouTube API connections.

			Parameters:
				cache - Whether to use cached information (15 minute cache, defaults to true)
		*/

		function __construct($cache = true) {
			global $cms;
			$this->Cache = $cache;

			// If we don't have the setting for the YouTube API, create it.
			$this->Settings = $cms->getSetting("bigtree-internal-youtube-api");
			if (!$this->Settings) {
				$admin = new BigTreeAdmin;
				$admin->createSetting(array(
					"id" => "bigtree-internal-youtube-api", 
					"name" => "YouTube API", 
					"encrypted" => "on", 
					"system" => "on"
				));
			}
			
			// Check if we're conected
			if ($this->Settings["key"] && $this->Settings["secret"] && $this->Settings["token"]) {
				$this->Connected = true;
			}

			// If our token is going to expire in the next 30 minutes, refresh it.
			if ($this->Settings["expires"] < time() + 1800) {
				$response = json_decode(BigTree::cURL("https://accounts.google.com/o/oauth2/token",array(
					"client_id" => $this->Settings["key"],
					"client_secret" => $this->Settings["secret"],
					"refresh_token" => $this->Settings["refresh_token"],
					"grant_type" => "refresh_token"
				)));
				if ($response->access_token) {
					$this->Settings["token"] = $response->access_token;
					$this->Settings["expires"] = strtotime("+".$response->expires_in." seconds");
					$admin = new BigTreeAdmin;
					$admin->updateSettingValue("bigtree-internal-youtube-api",$this->Settings);
				}
			}
		}
		
		/*
			Function: call
				Calls the YouTube API directly with the given API endpoint and parameters.
				Caches information unless caching is explicitly disabled on class instantiation or method is not GET.

			Parameters:
				endpoint - The YouTube API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				headers - Additional headers to send.

			Returns:
				Information directly from the API or the cache.
		*/

		function call($endpoint = false,$params = array(),$method = "GET",$headers = array()) {
			global $cms;
			
			if ($this->Cache) {
				$cache_key = md5($endpoint.json_encode($params));
				$record = $cms->cacheGet("org.bigtreecms.api.youtube",$cache_key,900);
				if ($record) {
					// We re-decode it as an object since that's what we're expecting from YouTube normally.
					return json_decode(json_encode($record));
				}
			}
			// Check again in the cache for this record's ETag

			
			$response = $this->callUncached($endpoint,$params,$method,$headers);
			if ($response !== false) {
				if ($this->Cache) {
					$cms->cachePut("org.bigtreecms.api.youtube",$cache_key,$response);
				}
			}
			return $response;
		}

		/*
			Function: callUncached
				Calls the YouTube API directly with the given API endpoint and parameters.
				Does not cache information.

			Parameters:
				endpoint - The YouTube API endpoint to hit.
				params - The parameters to send to the API (key/value array).
				method - HTTP method to call (defaults to GET).
				headers - Additional headers to send.

			Returns:
				Information directly from the API.
		*/

		function callUncached($endpoint,$params = array(),$method = "GET",$headers = array()) {
			if (!$this->Connected) {
				throw new Exception("The YouTube API is not connected.");
			}

			$endpoint .= (strpos($endpoint,"?") !== false) ? "&access_token=".urlencode($this->Settings["token"]) : "?access_token=".urlencode($this->Settings["token"]);
		
			// Build out GET vars if we're using GET.
			if ($method == "GET" && count($params)) {
				foreach ($params as $key => $val) {
					$endpoint .= "&$key=".urlencode($val);
				}
				// Don't send them as POST content
				$params = array();
			}
			$response = json_decode(BigTree::cURL($this->URL.$endpoint,$params,array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers)));
			if (isset($response->error)) {
				foreach ($response->error->errors as $error) {
					$this->Errors[] = $error;
				}
				return false;
			} else {
				return $response;
			}
		}

		/*
			Function: deleteVideo
				Deletes a video (must be owned by the authenticated user).

			Parameters:
				id - The video ID to delete.

			Returns:
				true on success.
		*/

		function deleteVideo($id) {
			$response = $this->call("videos?id=$id",array(),"DELETE");
			if ($response) {
				return true;
			}
			return false;
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
				"part" => "snippet,contentDetails,player,statistics,status,topicDetails,recordingDetails",
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
			Function: searchVideos
				Searches YouTube for videos

			Parameters:
				query - A string to search for.
				order - The order to sort by (options are date, rating, relevance, title, viewCount) — defaults to relevance.
				count - Number of videos to return (defaults to 10).
				params - Additional parameters to pass to the search API call.
		*/

		function searchVideos($query,$order = "relevance",$count = 10,$params = array()) {
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
			return new BigTreeYouTubeResultSet($this,"searchVideos",array($query,$order,$count,$params),$response,$results);
		}
	}

	/*
		Class: BigTreeYouTubeResultSet
	*/

	class BigTreeYouTubeResultSet {

		/*
			Constructor:
				Creates a result set of YouTube data.

			Parameters:
				api - An instance of BigTreeYouTubeAPI
				last_call - Method called on BigTreeYouTubeAPI
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$data,$results) {
			$this->API = $api;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->NextPageToken = $data->nextPageToken;
			$this->PreviousPageToken = $data->prevPageToken;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Calls the previous method and gets the next page of results.

			Returns:
				A BigTreeYouTubeResultSet or false if there is not another page.
		*/

		function nextPage() {
			if ($this->NextPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->NextPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}

		/*
			Function: previousPage
				Calls the previous method and gets the previous page of results.

			Returns:
				A BigTreeYouTubeResultSet or false if there is not a previous page.
		*/

		function previousPage() {
			if ($this->PreviousPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->PreviousPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
			}
			return false;
		}
	}

	/*
		Class: BigTreeYouTubeVideo
	*/

	class BigTreeYouTubeVideo {

		function __construct($video,&$api) {
			$duration = explode("M",substr($video->contentDetails->duration,2));

			$this->Captioned = $video->contentDetails->caption;
			$this->CategoryID = $video->snippet->categoryId;
			$this->ChannelTitle = $video->snippet->channelTitle;
			$this->CommentCount = $video->statistics->commentCount;
			$this->ContentRatings = $video->contentDetails->contentRating;
			$this->Definition = $video->contentDetails->definition;
			$this->Description = $video->snippet->description;
			$this->Dimension = $video->contentDetails->dimension;
			$this->DislikeCount = $video->statistics->dislikeCount;
			$this->Duration = intval($duration[0]) * 60 + intval($duration[1]);
			$this->DurationMinutes = intval($duration[0]);
			$this->DurationSeconds = intval($duration[1]);
			$this->Embed = $video->player->embedHtml;
			$this->Embeddable = $video->status->embeddable;
			$this->FavoriteCount = $video->statistics->favoriteCount;
			$this->ID = $video->id->videoId;
			$this->Image = $video->snippet->thumbnails->high->url;
			$this->License = $video->status->license;
			$this->LicensedContent = $video->contentDetails->licensedContent;
			$this->LikeCount = $video->statistics->likeCount;
			$this->Location->Latitude = $video->recordingDetails->location->latitude;
			$this->Location->Longitude = $video->recordingDetails->location->longitude;
			$this->Location->Elevation = $video->recordingDetails->location->elevation;
			$this->Location->Description = $video->recordingDetails->locationDescription;
			$this->Privacy = $video->status->privacyStatus;
			$this->RecordedTimestamp = $video->recordingDetails->recordingDate ? date("Y-m-d H:i:s",strtotime($video->recordingDetails->recordingDate)) : "";
			$this->SmallImage = $video->snippet->thumbnails->medium->url;
			$this->ThumbnailImage = $video->snippet->thumbnails->default->url;
			$this->Timestamp = date("Y-m-d H:i:s",strtotime($video->snippet->publishedAt));
			$this->Title = $video->snippet->title;
			$this->UploadFailureReason = $video->status->failureReason;
			$this->UploadRejectionReason = $video->status->rejectionReason;
			$this->UploadStatus = $video->status->uploadStatus;
			$this->ViewCount = $video->statistics->viewCount;
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
			Function: rate
				Causes the authenticated user to set/clear a rating on a video.

			Parameters:
				rating - "like", "dislike", or "none" (for clearing an existing rating)

			Returns:
				true on success.
		*/

		function rate($rating) {
			return $this->API->rateVideo($this->ID,$rating);
		}
	}
?>