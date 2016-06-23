<?php
	/*
		Class: BigTree\Facebook\Album
			Facebook API class for a picture album.
	*/

	namespace BigTree\Facebook;

	class Album {

		/** @var \BigTree\Facebook\API */
		protected $API;

		public $Pictures;

		function __construct($album, &$api) {
			$this->API = $api;

			$response = $this->API->call($album->cover_photo->id."?fields=source,created_time,images");
			$this->CoverPhoto = new Picture($response, $this->API);

			$this->CreatedTime = $album->created_time;
			$this->Description = $album->description;
			$this->ID = $album->id;
			$this->Link = $album->link;
			$this->Name = $album->name;
			$this->PhotoCount = $album->count;
			$this->Place = new Location($album->place, $api);
			$this->Type = $album->type;
		}

		/*
			Function: getPictures
				Gets all the pictures in this album.

			Returns:
				Returns an array of BigTree\Facebook\Picture objects or false on failure.
		*/

		function getPictures() {
			if (isset($this->Pictures)) {
				return $this->Pictures;
			}

			$response = $this->API->call($this->ID."/photos?fields=source,created_time,images");

			if (isset($response->data)) {
				$this->Pictures = array();

				foreach ($response->data as $picture) {
					$this->Pictures[] = new Picture($picture, $this->API);
				}

				return $this->Pictures;
			}

			return false;
		}
	}
	