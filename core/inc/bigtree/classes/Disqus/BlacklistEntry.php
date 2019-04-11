<?php
	/*
		Class: BigTree\Disqus\BlacklistEntry
			A Disqus object that contains information about and methods you can perform on a blacklist entry.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class BlacklistEntry
	{
		
		/** @var API */
		protected $API;
		
		public $ForumID;
		public $ID;
		public $Notes;
		public $Timestamp;
		public $Type;
		public $Value;
		
		public function __construct(stdClass $item, API &$api)
		{
			$this->API = $api;
			$this->ForumID = $item->forum;
			$this->ID = $item->id;
			$this->Notes = $item->notes;
			$this->Timestamp = date("Y-m-d H:i:s", strtotime($item->createdAt));
			$this->Type = $item->type;
			$this->Value = $item->value;
		}
		
		/*
			Function: remove
				Removes this blacklist entry.
		*/
		
		public function remove(): bool
		{
			$response = $this->API->call("blacklists/remove.json", ["forum" => $this->ForumID, $this->Type => $this->Value], "POST");
		
			if (!is_null($response)) {
				$this->API->cacheBust("blacklist".$this->ID);
				$this->API->cacheBust("blacklisted".$this->ForumID);
				
				return true;
			}
			
			return false;
		}
		
	}
