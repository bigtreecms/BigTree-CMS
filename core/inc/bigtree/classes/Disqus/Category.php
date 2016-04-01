<?php
	/*
		Class: BigTree\Disqus\Category
			A Disqus object that contains information about and methods you can perform on a category.
	*/

	namespace BigTree\Disqus;

	class Category {

		/** @var \BigTree\Disqus\API */
		protected $API;

		function __construct($category,&$api) {
			$this->API = $api;
			isset($category->isDefault) ? $this->Default = $category->isDefault : false;
			isset($category->forum) ? $this->Forum = $category->forum : false;
			isset($category->id) ? $this->ID = $category->id : false;
			isset($category->title) ? $this->Name = $category->title : false;
			isset($category->order) ? $this->Order = $category->order : false;
		}

	}
	