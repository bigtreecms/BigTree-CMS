<?php
	/*
		Class: BigTree\Flickr\Album
			A Flickr object that contains album information and methods you can perform on the album.
	*/
	
	namespace BigTree\Flickr;
	
	use stdClass;
	
	class Album
	{
		
		/** @var API */
		protected $API;
		
		function __construct(stdClass $album, API &$api)
		{
			$this->API = $api;
			
			if (isset($album->primary_photo_extras->url_sq)) {
				$this->Cover = new stdClass;
				$this->Cover->Type = $album->primary_photo_extras->media;
				$this->Cover->Images = new stdClass;
				$this->Cover->Images->Square = $album->primary_photo_extras->url_sq;
				$this->Cover->Images->Thumbnail = $album->primary_photo_extras->url_t;
				$this->Cover->Images->Small = $album->primary_photo_extras->url_s;
				$this->Cover->Images->Medium = $album->primary_photo_extras->url_m;
				$this->Cover->Images->Original = $album->primary_photo_extras->url_o;
			}
			
			$this->CreatedAt = date("Y-m-d H:i:s", $album->date_create);
			isset($album->farm) ? $this->Farm = $album->farm : false;
			$this->ID = $album->id;
			$this->PhotoCount = $album->photos;
			isset($album->primary) ? $this->Primary = $album->primary : false;
			isset($album->secret) ? $this->Secret = $album->secret : false;
			isset($album->title->_content) ? $this->Title = $album->title->_content : false;
			$this->UpdatedAt = date("Y-m-d H:i:s", $album->date_update);
			$this->VideoCount = $album->videos;
		}
		
		/*
			Function: getPhotos
				Returns the photos in this album.

			Parameters:
				privacy - Privacy level of photos to return (defaults to PRIVACY_PUBLIC / 1)
				info - A comma separated list of additional information to retrieve (defaults to license, date_upload, date_taken, owner_name, icon_server, original_format, last_update)

			Returns:
				A BigTree\Flickr\ResultSet of BigTree\Flickr\Photo objects or null if the call fails.
		*/
		
		function getPhotos($privacy = 1, $info = ""): ?ResultSet
		{
			return $this->API->getAlbumPhotos($this->ID, $privacy, $info);
		}
		
	}
	