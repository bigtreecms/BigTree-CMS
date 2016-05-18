<?php
	/*
		Class: BigTree\Flickr\Tag
			A Flickr object that contains information about and methods you can perform on a tag.
	*/

	namespace BigTree\Flickr;

	class Tag {

		/** @var \BigTree\Flickr\API */
		protected $API;

		public $Author;
		public $ID;
		public $Name;

		function __construct($tag,&$api) {
			if (!is_string($tag)) {
				$this->API = $api;
				$this->Author = $tag->author;
				$this->ID = $tag->id;
				$this->Name = $tag->raw;
			} else {
				$this->Name = $tag;
			}
		}

		function __toString() {
			return $this->Name;
		}

		/*
			Function: remove
				Removes this tag from the associated photo.

			Returns:
				true on success
		*/

		function remove() {
			return $this->API->removeTagFromPhoto($this->ID);
		}

	}
	