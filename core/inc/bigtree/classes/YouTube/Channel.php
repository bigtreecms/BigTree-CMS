<?php
	/*
		Class: BigTree\YouTube\Channel
			A YouTube object that contains information about and methods you can perform on a channel.
	*/
	
	namespace BigTree\YouTube;
	
	use BigTree\GoogleResultSet;
	use stdClass;
	
	class Channel
	{
		
		/** @var API */
		protected $API;
		
		public $CommentCount;
		public $Description;
		public $ID;
		public $Images;
		public $SubscriberCount;
		public $Timestamp;
		public $Title;
		public $VideoCount;
		public $ViewCount;
		
		function __construct(stdClass $channel, API &$api)
		{
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
			isset($channel->snippet->publishedAt) ? $this->Timestamp = date("Y-m-d H:i:s", strtotime($channel->snippet->publishedAt)) : false;
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
				A BigTree\GoogleResultSet of BigTree\YouTube\Video objects.
		*/
		
		function getVideos(int $count = 10, string $order = "date"): ?GoogleResultSet
		{
			return $this->API->getChannelVideos($this->ID, $order, $count);
		}
		
		/*
			Function: subscribe
				Subscribes the authenticated user to the channel.
		*/
		
		function subscribe(): void
		{
			$this->API->subscribe($this->ID);
		}
		
		/*
			Function: unsubscribe
				Unsubscribes the authenticated user from the channel.
		*/
		
		function unsubscribe(): bool
		{
			return $this->API->unsubscribe($this->ID);
		}
		
	}
