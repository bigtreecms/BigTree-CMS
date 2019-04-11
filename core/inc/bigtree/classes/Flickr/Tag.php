<?php
	/*
		Class: BigTree\Flickr\Tag
			A Flickr object that contains information about and methods you can perform on a tag.
	*/

	namespace BigTree\Flickr;
	
	class Tag
	{

		/** @var API */
		protected $API;

		public $Author;
		public $ID;
		public $Name;

		public function __construct($tag, API &$api)
		{
			if (!is_string($tag)) {
				$this->API = $api;
				$this->Author = $tag->author;
				$this->ID = $tag->id;
				$this->Name = $tag->raw;
			} else {
				$this->Name = $tag;
			}
		}

		public function __toString()
		{
			return $this->Name;
		}

		/*
			Function: remove
				Removes this tag from the associated photo.

			Returns:
				true on success
		*/

		public function remove(): bool
		{
			return $this->API->removeTagFromPhoto($this->ID);
		}

	}
	