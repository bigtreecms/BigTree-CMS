<?php
	/*
		Class: BigTree\YouTube\Activity
			A YouTube object that contains information about and methods you can perform on an activity.
	*/
	
	namespace BigTree\YouTube;
	
	use stdClass;
	
	class Activity
	{
		
		/** @var API */
		protected $API;
		
		public $ChannelID;
		public $ChannelTitle;
		public $Comment;
		public $Description;
		public $Favorite;
		public $GroupID;
		public $ID;
		public $Images;
		public $Like;
		public $PlaylistItem;
		public $Recommendation;
		public $Social;
		public $Subscription;
		public $Timestamp;
		public $Title;
		public $Type;
		public $Upload;
		
		function __construct(stdClass $activity, API &$api)
		{
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
			
			isset($activity->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($activity->snippet->publishedAt)) : false;
			isset($activity->snippet->title) ? $this->Title = $activity->snippet->title : false;
			$this->Type = $activity->snippet->type;
			
			if ($type == "upload") {
				$this->Upload = new stdClass;
				$this->Upload->VideoID = $activity->contentDetails->upload->videoId;
			}
		}
		
	}
	