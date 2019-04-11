<?php
	/*
		Class: BigTree\YouTube\Video
			A YouTube object that contains information about and methods you can perform on a video.
	*/

	namespace BigTree\YouTube;

	use stdClass;

	class Video
	{

		/** @var API */
		protected $API;

		public $Captioned;
		public $CategoryID;
		public $ChannelID;
		public $ChannelTitle;
		public $CommentCount;
		public $ContentRatings;
		public $Definition;
		public $Description;
		public $Dimension;
		public $DislikeCount;
		public $Duration;
		public $Embed;
		public $Embeddable;
		public $FavoriteCount;
		public $ID;
		public $Images;
		public $License;
		public $LicensedContent;
		public $LikeCount;
		public $Location;
		public $Privacy;
		public $RecordedTimestamp;
		public $Tags;
		public $Timestamp;
		public $Title;
		public $UploadFailureReason;
		public $UploadRejectionReason;
		public $UploadStatus;
		public $ViewCount;

		public function __construct($video,&$api)
		{
			$this->API = $api;
			isset($video->contentDetails->caption) ? $this->Captioned = $video->contentDetails->caption : false;
			isset($video->snippet->categoryId) ? $this->CategoryID = $video->snippet->categoryId : false;
			isset($video->snippet->channelId) ? $this->ChannelID = $video->snippet->channelId : false;
			isset($video->snippet->channelTitle) ? $this->ChannelTitle = $video->snippet->channelTitle : false;
			isset($video->statistics->commentCount) ? $this->CommentCount = $video->statistics->commentCount : false;
			isset($video->contentDetails->contentRating) ? $this->ContentRatings = $video->contentDetails->contentRating : false;
			isset($video->contentDetails->definition) ? $this->Definition = $video->contentDetails->definition : false;
			isset($video->snippet->description) ? $this->Description = $video->snippet->description : false;
			isset($video->contentDetails->dimension) ? $this->Dimension = $video->contentDetails->dimension : false;
			isset($video->statistics->dislikeCount) ? $this->DislikeCount = $video->statistics->dislikeCount : false;
			isset($video->contentDetails->duration) ? $this->Duration = $this->API->timeSplit($video->contentDetails->duration) : false;
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

		public function delete(): void
		{
			$this->API->deleteVideo($this->ID);
		}

		/*
			Function: getDetails
				Looks up more details on this video.
				Calls other than BigTree\YouTube\API::getVideo will return partial video information, this call supplements the partial responses with a full response.

			Returns:
				A new Video object with more details.
		*/

		public function getDetails(): ?Video
		{
			return $this->API->getVideo($this->ID);
		}

		/*
			Function: rate
				Causes the authenticated user to set/clear a rating on a video.

			Parameters:
				rating - "like", "dislike", or "none" (for clearing an existing rating)
		*/

		public function rate(string $rating): bool
		{
			return $this->API->rateVideo($this->ID,$rating);
		}

		/*
			Function: save
				Saves changes to "snippet" related properties (video must be owned by the authenticated user)
				Properties that save are: Title, Description, Tags, CategoryID, Privacy, Embeddable, License

			Returns:
				true on success.
		*/

		public function save(): bool
		{
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
