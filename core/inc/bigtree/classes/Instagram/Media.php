<?php
	/*
		Class: BigTree\Instagram\Media
			An Instagram object that contains information about and methods you can perform on media.
	*/
	
	namespace BigTree\Instagram;
	
	use stdClass;
	
	class Media
	{
		
		/** @var API */
		protected $API;
		
		public $Caption;
		public $Filter;
		public $ID;
		public $Image;
		public $Liked;
		public $LikesCount;
		public $Likes = [];
		public $Location;
		public $SmallImage;
		public $Tags = [];
		public $ThumbnailImage;
		public $Timestamp;
		public $Type;
		public $URL;
		public $User;
		public $UsersInPhoto;
		public $Videos;
		
		/*
			Constructor:
				Creates a media object from Instagram data.

			Parameters:
				media - Instagram data
				api - Reference to the BigTree\Instagram\API class instance
		*/
		
		public function __construct(stdClass $media, API &$api)
		{
			$this->API = $api;
			isset($media->caption) ? $this->Caption = $media->caption->text : false;
			isset($media->filter) ? $this->Filter = $media->filter : false;
			isset($media->id) ? $this->ID = $media->id : false;
			isset($media->images->standard_resolution->url) ? $this->Image = $media->images->standard_resolution->url : false;
			isset($media->user_has_liked) ? $this->Liked = $media->user_has_liked : false;
			
			if (isset($media->likes)) {
				$this->LikesCount = $media->likes->count;
				
				if (!empty($media->likes->data)) {
					$this->Likes = array();
					
					foreach ($media->likes->data as $user) {
						$this->Likes[] = new User($user,$api);
					}
				}
			}
			
			if (isset($media->location)) {
				$this->Location = new Location($media->location, $api);
			}
			
			isset($media->images->low_resolution->url) ? $this->SmallImage = $media->images->low_resolution->url : false;
			
			if (isset($media->tags)) {
				foreach ($media->tags as $tag_name) {
					$tag = new Tag(false, $api);
					$tag->Name = $tag_name;
					$this->Tags[] = $tag;
				}
			}
			
			isset($media->images->thumbnail->url) ? $this->ThumbnailImage = $media->images->thumbnail->url : false;
			isset($media->created_time) ? $this->Timestamp = date("Y-m-d H:i:s", $media->created_time) : false;
			isset($media->type) ? $this->Type = $media->type : false;
			isset($media->link) ? $this->URL = $media->link : false;
			isset($media->user) ? $this->User = new User($media->user, $api) : false;
			isset($media->users_in_photo) ? $this->UsersInPhoto = $media->users_in_photo : false;
			
			if (isset($media->videos)) {
				$this->Videos = new stdClass;
				$this->Videos->Standard = new stdClass;
				$this->Videos->Standard->URL = $media->videos->standard_resolution->url;
				$this->Videos->Standard->Height = $media->videos->standard_resolution->height;
				$this->Videos->Standard->Width = $media->videos->standard_resolution->width;
				$this->Videos->LowRes = new stdClass;
				$this->Videos->LowRes->URL = $media->videos->low_resolution->url;
				$this->Videos->LowRes->Height = $media->videos->low_resolution->height;
				$this->Videos->LowRes->Width = $media->videos->low_resolution->width;
			}
		}
		
		/*
			Function: comment
				Alias for BigTree\Instagram\API::comment
		*/
		
		public function comment(string $comment): bool
		{
			return $this->API->comment($this->ID, $comment);
		}
		
		/*
			Function: getComments
				Alias for BigTree\Instagram\API::getComments
		*/
		
		public function getComments(): ?array
		{
			return $this->API->getComments($this->ID);
		}
		
		/*
			Function: getLikes
				Alias for BigTree\Instagram\API::getLikes
		*/
		
		public function getLikes(): ?array
		{
			return $this->API->getLikes($this->ID);
		}
		
		/*
			Function: getLocation
				Alias for BigTree\Instagram\API::getLocation
		*/
		
		public function getLocation(): ?Location
		{
			return $this->API->getLocation($this->Location->ID);
		}
		
		/*
			Function: getUser
				Alias for BigTree\Instagram\API::getUser
		*/
		
		public function getUser(): ?User
		{
			return $this->API->getUser($this->User->ID);
		}
		
		/*
			Function: like
				Alias for BigTree\Instagram\API::like
		*/
		
		public function like(): bool
		{
			return $this->API->like($this->ID);
		}
		
		/*
			Function: unlike
				Alias for BigTree\Instagram\API::unlike
		*/
		
		public function unlike(): bool
		{
			return $this->API->unlike($this->ID);
		}
		
	}
	