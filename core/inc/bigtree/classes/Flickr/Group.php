<?php
	/*
		Class: BigTree\Flickr\Group
			A Flickr object that contains information about and methods you can perform on a group.
	*/
	
	namespace BigTree\Flickr;
	
	use stdClass;
	
	class Group
	{
		
		/** @var API */
		protected $API;
		
		public $Description;
		public $ID;
		public $Image;
		public $MemberCount;
		public $Name;
		public $PhotoCount;
		public $Rules;
		public $TopicCount;
		
		function __construct(stdClass $group, API &$api)
		{
			$this->API = $api;
			isset($group->description->_content) ? $this->Description = $group->description->_content : false;
			$this->ID = isset($group->nsid) ? $group->nsid : $group->id;
			$this->Image = "http://farm".$group->iconfarm.".staticflickr.com/".$group->iconserver."/buddyicons/".$this->ID.".jpg";
			$this->MemberCount = isset($group->members->_content) ? $group->members->_content : $group->members;
			$this->Name = isset($group->name->_content) ? $group->name->_content : $group->name;
			$this->PhotoCount = isset($group->pool_count->_content) ? $group->pool_count->_content : $group->pool_count;
			isset($group->rules->_content) ? $this->Rules = $group->rules->_content : false;
			isset($group->topic_count->_content) ? $this->TopicCount = $group->topic_count->_content : false;
		}
		
	}
	