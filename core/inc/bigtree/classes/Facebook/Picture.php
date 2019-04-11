<?php
	/*
		Class: BigTree\Facebook\Picture
			Facebook API class for a picture.
	*/
	
	namespace BigTree\Facebook;
	
	use stdClass;
	
	class Picture {
		
		/** @var API */
		protected $API;
		
		function __construct(stdClass $picture, API &$api)
		{
			$this->API = $api;
			
			$this->CreatedTime = $picture->created_time;
			$this->ID = $picture->id;
			$this->Images = [];
			$this->Images["default"] = $picture->source;
			
			foreach ($picture->images as $image) {
				$this->Images[$image->width."x".$image->height] = $image->source;
			}
		}
		
		/*
			Function: getSize
				Facebook has several sizes of your image. This functions returns the one you want.

			Parameters:
				dimensions - e.g. "300x225" (limited set available)

			Returns:
				Returns the url of the requested image or the default image.
		*/
		
		function getSize(string $dimensions): string
		{
			if (isset($this->Images[$dimensions])) {
				return $this->Images[$dimensions];
			}
			
			return $this->Images["default"];
		}
	}
	