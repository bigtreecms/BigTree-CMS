<?php
	/*
		Class: BigTree\Flickr\Photo
			A Flickr object that contains information about and methods you can perform on a photo.
	*/

	namespace BigTree\Flickr;

	use stdClass;

	class Photo {

		/** @var \BigTree\Flickr\API */
		protected $API;

		public $CanComment;
		public $CanAddMeta;
		public $CommentCount;
		public $Dates;
		public $Description;
		public $Favorited;
		public $ID;
		public $Image100;
		public $Image240;
		public $Image320;
		public $Image500;
		public $Image640;
		public $Image800;
		public $Image1024;
		public $ImageSquare75;
		public $ImageSquare150;
		public $License;
		public $Location;
		public $NextPhoto;
		public $Notes;
		public $OriginalImage;
		public $PreviousPhoto;
		public $Rotation;
		public $SafetyLevel;
		public $Secret;
		public $Tags = array();
		public $Title;
		public $URLs;
		public $User;
		public $VisibleToFamily;
		public $VisibleToFriends;
		public $VisibleToPublic;

		function __construct($photo,&$api) {
			$image_base = "http://farm".$photo->farm.".staticflickr.com/".$photo->server."/".$photo->id."_".$photo->secret;

			$this->API = $api;
			if (isset($photo->editability)) {
				$this->CanComment = $photo->editability->cancomment;
				$this->CanAddMeta = $photo->editability->canaddmeta;
			}
			isset($photo->comments->_content) ? $this->CommentCount = $photo->comments->_content : false;
			if (isset($photo->dates) || isset($photo->dateupload)) {
				$this->Dates = new stdClass;
				$this->Dates->Posted = date("Y-m-d H:i:s",isset($photo->dates->posted) ? $photo->dates->posted : $photo->dateupload);
				isset($photo->dates->taken) ? $this->Dates->Taken = $photo->dates->taken : false;
				isset($photo->datetaken) ? $this->Dates->Taken = $photo->datetaken : false;
				isset($photo->dates->lastupdate) ? $this->Dates->Updated = date("Y-m-d H:i:s",$photo->dates->lastupdate) : false;
			}
			isset($photo->description->_content) ? $this->Description = $photo->description->_content : false;
			isset($photo->isfavorite) ? $this->Favorited = $photo->isfavorite : false;
			$this->ID = $photo->id;
			$this->Image100 = $image_base."_t.jpg";
			$this->Image240 = $image_base."_m.jpg";
			$this->Image320 = $image_base."_n.jpg";
			$this->Image500 = $image_base.".jpg";
			$this->Image640 = $image_base."_z.jpg";
			$this->Image800 = $image_base."_c.jpg";
			$this->Image1024 = $image_base."_b.jpg";
			$this->ImageSquare75 = $image_base."_s.jpg";
			$this->ImageSquare150 = $image_base."_q.jpg";
			isset($photo->license) ? $this->License = $photo->license : false;
			if (isset($photo->latitude)) {
				$this->Location = new stdClass;
				$this->Location->Accuracy = $photo->accuracy;
				$this->Location->Latitude = $photo->latitude;
				$this->Location->Longitude = $photo->longitude;
			}
			isset($photo->notes->note) ? $this->Notes = $photo->notes->note : false;
			isset($photo->originalsecret) ? $this->OriginalImage = "http://farm".$photo->farm.".staticflickr.com/".$photo->server."/".$photo->id."_".$photo->originalsecret."_o.".$photo->originalformat : false;
			isset($photo->rotation) ? $this->Rotation = $photo->rotation : false;
			isset($photo->safety_level) ? $this->SafetyLevel = $photo->safety_level : false;
			isset($photo->secret) ? $this->Secret = $photo->secret : false;
			if (isset($photo->tags->tag)) {
				foreach ($photo->tags->tag as $tag) {
					$this->Tags[] = new Tag($tag,$api);
				}
			} elseif (isset($photo->tags)) {
				$this->Tags = array();
				$tags = explode(" ",$photo->tags);
				foreach ($tags as $t) {
					$this->Tags[] = new Tag($t,$api);
				}
			}
			$this->Title = isset($photo->title->_content) ? $photo->title->_content : $photo->title;
			isset($photo->media) ? $this->Type = $photo->media : false;
			if (isset($photo->urls->url)) {
				$this->URLs = new stdClass;
				foreach ($photo->urls->url as $u) {
					$k = ucwords($u->type);
					$this->URLs->$k = $u->_content;
				}
			}
			isset($photo->owner) ? $this->User = new Person($photo->owner,$api) : false;
			$this->VisibleToFamily = isset($photo->visibility->isfamily) ? $photo->visibility->isfamily : $photo->isfamily;
			$this->VisibleToFriends = isset($photo->visibility->isfriend) ? $photo->visibility->isfriend : $photo->isfriend;
			$this->VisibleToPublic = isset($photo->visibility->ispublic) ? $photo->visibility->ispublic : $photo->ispublic;
		}

		/*
			Function: addTags
				Adds tags to this photo.

			Parameters:
				tags - A single tag as a string or an array of tags.

			Returns:
				true if successful
		*/

		function addTags($tags) {
			return $this->API->addTagsToPhoto($this->ID,$tags);
		}

		/*
			Function: getContext
				Gets information about the next and previous photos in the photo stream.
		*/

		function getContext() {
			$response = $this->API->call("flickr.photos.getContext",array("photo_id" => $this->ID));

			if (isset($response->nextphoto)) {
				$this->NextPhoto = new Photo($response->nextphoto,$this->API);
			}

			if (isset($response->prevphoto)) {
				$this->PreviousPhoto = new Photo($response->prevphoto,$this->API);
			}
		}

		/*
			Function: getExif
				Gets EXIF/TIFF/GPS information about this photo.
		*/

		function getExif() {
			$response = $this->API->call("flickr.photos.getExif",array("photo_id" => $this->ID,"secret" => $this->Secret));

			if (!isset($response->photo)) {
				return false;
			}

			$tags = array();
			foreach ($response->photo->exif as $item) {
				$tag = new stdClass;
				$tag->Label = $item->label;
				$tag->Name = $item->tag;
				isset($item->clean) ? $tag->RawValue = $item->raw->_content : false;
				$tag->Type = $item->tagspace;
				$tag->TypeID = $item->tagspaceid;
				$tag->Value = isset($item->clean) ? $item->clean->_content : $item->raw->_content;
				$tags[] = $tag;
			}

			return $tags;
		}

		/*
			Function: getFavorites
				Returns the users who have favorited this photo.

			Parameters:
				per_page - Number of results per page (defaults to 50, max 50)
				params - Additional parameters to pass to the flickr.photos.getFavorites API call

			Returns:
				A ResultSet of BigTree\Flickr\Person objects.
		*/

		function getFavorites($per_page = 50,$params = array()) {
			$params["photo_id"] = $this->ID;
			$params["per_page"] = $per_page;
			$response = $this->API->call("flickr.photos.getFavorites",$params);

			if (!$response->photo) {
				return false;
			}

			$people = array();
			foreach ($response->photo->person as $person) {
				$people[] = new Person($person,$this->API);
			}

			return new ResultSet($this,"getFavorites",array($per_page,$params),$people,$response->photo->page,$response->photo->pages);
		}

		/*
			Function: getInfo
				Returns additional information on this photo.
				Useful if another call returned limited information about a photo.

			Returns:
				A new Photo object or false if the call fails.
		*/

		function getInfo() {
			$response = $this->API->call("flickr.photos.getInfo",array("photo_id" => $this->ID,"secret" => $this->Secret));

			if (!isset($response->photo)) {
				return false;
			}

			return new Photo($response->photo,$this->API);
		}

		/*
			Function: delete
				Deletes this photo.

			Returns:
				true if successful
		*/

		function delete() {
			return $this->API->deletePhoto($this->ID);
		}

		/*
			Function: next
				Returns the next photo in the photo stream.
		*/

		function next() {
			if ($this->NextPhoto) {
				return $this->NextPhoto;
			}

			$this->getContext();

			return $this->NextPhoto;
		}

		/*
			Function: previous
				Returns the previous photo in the photo stream.
		*/

		function previous() {
			if ($this->PreviousPhoto) {
				return $this->PreviousPhoto;
			}

			$this->getContext();

			return $this->PreviousPhoto;
		}

		/*
			Function: setContentType
				Sets the content type of the image.

			Parameters:
				type - 1 (Photo), 2 (Screenshot), 3 (Other)

			Returns:
				true if successful
		*/

		function setContentType($type) {
			$response = $this->API->call("flickr.photos.setContentType",array("photo_id" => $this->ID,"content_type" => $type),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

		/*
			Function: setDateTaken
				Sets the date taken of the image.

			Parameters:
				date - Date in a format understood by strtotime

			Returns:
				true if successful
		*/

		function setDateTaken($date) {
			$date = date("Y-m-d H:i:s",strtotime($date));
			$response = $this->API->call("flickr.photos.setDates",array("photo_id" => $this->ID,"date_taken" => $date),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

		/*
			Function: setPermissions
				Sets the permissions of the image.

			Parameters:
				public - Visible to public (defaults to true)
				friends - Visible to friends (defaults to true)
				family - Visible to family (defaults to true)
				comments - Who can comment on this image (0 = none, 1 = friends & family, 2 = contacts, 3 = everyone - default)
				metadata - Who can add metadata (tags & notes) to this image (0 = none/owner - default, 1 = friends & family, 2 = contacts, 3 = everyone)

			Returns:
				true if successful
		*/

		function setPermissions($public = true,$friends = true,$family = true,$comments = 3,$metadata = 0) {
			$response = $this->API->call("flickr.photos.setPerms",array("photo_id" => $this->ID,"is_public" => $public,"is_friend" => $friends,"is_family" => $family,"perm_comment" => $comments,"perm_addmeta" => $metadata),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

		/*
			Function: setSafetyLevel
				Sets the safety level of the image.

			Parameters:
				level - 1 (safe, default), 2 (moderate), 3 (restricted)

			Returns:
				true if successful
		*/

		function setSafetyLevel($level) {
			$response = $this->API->call("flickr.photos.setSafetyLevel",array("photo_id" => $this->ID,"safety_level" => $level),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

		/*
			Function: setTags
				Sets the tags of the image.

			Parameters:
				tags - An array of tags or a comma separated string of tags

			Returns:
				true if successful
		*/

		function setTags($tags) {
			if (is_array($tags)) {
				$tags = implode(",",$tags);
			}
			$response = $this->API->call("flickr.photos.setTags",array("photo_id" => $this->ID,"tags" => $tags),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

		/*
			Function: setTitleAndDescription
				Sets the title and description of the image.

			Parameters:
				title - Title to set
				description - Description to set

			Returns:
				true if successful
		*/

		function setTitleAndDescription($title,$description) {
			$response = $this->API->call("flickr.photos.setMeta",array("photo_id" => $this->ID,"title" => $title,"description" => $description),"POST");

			if ($response !== false) {
				return true;
			}

			return false;
		}

	}
