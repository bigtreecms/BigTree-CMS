<?php
	/*
		Class: BigTree\Disqus\WhitelistEntry
			A Disqus object that contains information about and methods you can perform on a whitelist entry.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class WhitelistEntry {
		
		/** @var \BigTree\Disqus\API */
		protected $API;
		
		public $ForumID;
		public $ID;
		public $Notes;
		public $Timestamp;
		public $Type;
		public $Value;
		
		function __construct(stdClass $item, API &$api) {
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
				Removes this whitelist entry.
		*/
		
		function remove(): bool {
			$response = $this->API->call("whitelists/remove.json", ["forum" => $this->ForumID, $this->Type => $this->Value], "POST");
			
			if ($response !== false) {
				$this->API->cacheBust("whitelisted".$this->ForumID);
				$this->API->cacheBust("whitelist".$this->ID);
				
				return true;
			}
			
			return false;
		}
		
	}