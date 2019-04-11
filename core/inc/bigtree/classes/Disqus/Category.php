<?php
	/*
		Class: BigTree\Disqus\Category
			A Disqus object that contains information about and methods you can perform on a category.
	*/
	
	namespace BigTree\Disqus;
	
	use stdClass;
	
	class Category
	{
		
		/** @var API */
		protected $API;
		
		public $Default;
		public $ForumID;
		public $ID;
		public $Name;
		public $Order;
		
		function __construct(stdClass $category, API &$api)
		{
			$this->API = $api;
			isset($category->isDefault) ? $this->Default = $category->isDefault : false;
			isset($category->forum) ? $this->ForumID = $category->forum : false;
			isset($category->id) ? $this->ID = $category->id : false;
			isset($category->title) ? $this->Name = $category->title : false;
			isset($category->order) ? $this->Order = $category->order : false;
		}
		
	}
	