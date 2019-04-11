<?php
	/*
		Class: BigTree\YouTube\PlaylistItem
			A YouTube object that contains information about and methods you can perform on a playlist item.
	*/
	
	namespace BigTree\YouTube;
	
	use stdClass;
	
	class PlaylistItem
	{
		
		/** @var API */
		protected $API;
		
		public $ChannelID;
		public $ChannelTitle;
		public $Description;
		public $ID;
		public $Images;
		public $Note;
		public $PlaylistID;
		public $Position;
		public $Privacy;
		public $Timestamp;
		public $Title;
		public $VideoID;
		public $VideoEndAt;
		public $VideoStartAt;
		
		public function __construct(stdClass $item, API &$api)
		{
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
			isset($item->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($item->snippet->publishedAt)) : false;
			isset($item->snippet->title) ? $this->Title = $item->snippet->title : false;
			isset($item->snippet->resourceId->videoId) ? $this->VideoID = $item->snippet->resourceId->videoId : false;
			isset($item->contentDetails->endAtMs) ? $this->VideoEndAt = $this->API->timeSplit($item->contentDetails->endAtMs) : false;
			isset($item->contentDetails->startAtMs) ? $this->VideoStartAt = $this->API->timeSplit($item->contentDetails->startAtMs) : false;
		}
		
		/*
			Function: delete
				Deletes this item from the playlist.
				Authenticated user must be the owner of the playlist.
		*/
		
		public function delete(): void
		{
			$this->API->deletePlaylistItem($this->ID);
		}
		
		/*
			Function: save
				Saves changed information (Note, VideoStartAt, VideoEndAt, Position)
				Authenticated user must be the owner of the playlist.
		*/
		
		public function save(): ?PlaylistItem
		{
			return $this->API->updatePlaylistItem($this->ID, $this->PlaylistID, $this->VideoID, $this->Position, $this->Note, $this->API->timeJoin($this->VideoStartAt), $this->API->timeJoin($this->VideoEndAt));
		}
		
		/*
			Function: video
				Returns more information about this item's video.

			Returns:
				A BigTree\YouTube\Video object.
		*/
		
		public function video(): ?Video
		{
			return $this->API->getVideo($this->VideoID);
		}
		
	}
	