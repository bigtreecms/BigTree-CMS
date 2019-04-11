<?php
	/*
		Class: BigTree\YouTube\Subscription
			A YouTube object that contains information about and methods you can perform on a subscription.
	*/
	
	namespace BigTree\YouTube;
	
	use stdClass;
	
	class Subscription
	{
		
		/** @var API */
		protected $API;
		
		public $Channel;
		public $ID;
		public $Timestamp;
		
		function __construct(stdClass $subscription, API &$api)
		{
			$this->API = $api;
			
			$channel = new stdClass;
			$channel->snippet = $subscription->snippet;
			// Not correct info for the channel so we move it
			$created_at = $channel->snippet->publishedAt;
			unset($channel->snippet->publishedAt);
			$channel->id = $channel->snippet->resourceId->channelId;
			$this->Channel = new Channel($channel, $api);
			
			$this->ID = $subscription->id;
			$this->Timestamp = date("Y-m-d H:i:s", strtotime($created_at));
		}
		
		/*
			Function: delete
				Removes this subscription from the authenticated user's subscribed channels.
		*/
		
		function delete(): void
		{
			$this->API->call("subscriptions?id=".$this->ID, false, "DELETE");
		}
		
	}
