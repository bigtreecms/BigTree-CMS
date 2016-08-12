<?php
	/*
		Class: BigTree\GooglePlus\Activity
			A Google+ object that contains information about and methods you can perform on an activity.
	*/

	namespace BigTree\GooglePlus;

	use stdClass;

	class Activity {

		/** @var \BigTree\GooglePlus\API */
		protected $API;

		public $Access;
		public $Content;
		public $ContentPlainText;
		public $ContentURL;
		public $CreatedAt;
		public $CrosspostSource;
		public $ID;
		public $Location;
		public $Media;
		public $Note;
		public $PlusOneCount;
		public $ReplyCount;
		public $Reshare = false;
		public $ResharedID;
		public $ResharedUser;
		public $ReshareCount;
		public $Service;
		public $Title;
		public $Type;
		public $UpdatedAt;
		public $URL;
		public $User;

		function __construct($activity,&$api) {
			if (is_array($activity->access->items)) {
				$this->Access = new stdClass;
				isset($activity->access->description) ? $this->Access->Description = $activity->access->description : false;
				$this->Access->Items = array();
				foreach ($activity->access->items as $item) {
					$i = new stdClass;
					isset($item->type) ? $i->Type = $item->type : false;
					isset($item->id) ? $i->ID = $item->id : false;
					$this->Access->Items[] = $i;
				}
			}
			$this->API = $api;
			isset($activity->object->content) ? $this->Content = $activity->object->content : false;
			isset($activity->object->originalContent) ? $this->ContentPlainText = $activity->object->originalContent : false;
			isset($activity->object->url) ? $this->ContentURL = $activity->object->url : false;
			isset($activity->published) ? $this->CreatedAt = date("Y-m-d H:i:s",strtotime($activity->published)) : false;
			isset($activity->crosspostSource) ? $this->CrosspostSource = $activity->crosspostSource : false;
			isset($activity->id) ? $this->ID = $activity->id : false;
			isset($activity->location) ? $this->Location = new Location($activity->location,$api) : false;
			if (is_array($activity->object->attachments)) {
				$this->Media = array();
				foreach ($activity->object->attachments as $item) {
					$m = new stdClass;
					isset($item->content) ? $m->Content = $item->content : false;
					isset($item->embed->type) ? $m->EmbedType = $item->embed->type : false;
					isset($item->embed->url) ? $m->EmbedURL = $item->embed->url : false;
					isset($item->id) ? $m->ID = $item->id : false;
					if (isset($item->fullImage)) {
						isset($item->fullImage->url) ? $m->Image = $item->fullImage->url : false;
						isset($item->fullImage->type) ? $m->ImageType = $item->fullImage->type : false;
						isset($item->fullImage->height) ? $m->ImageHeight = $item->fullImage->height : false;
						isset($item->fullImage->width) ? $m->ImageWidth = $item->fullImage->width : false;
					}
					if (isset($item->image)) {
						isset($item->image->url) ? $m->Thumbnail = $item->image->url : false;
						isset($item->image->type) ? $m->ThumbnailType = $item->image->type : false;
						isset($item->image->height) ? $m->ThumbnailHeight = $item->image->height : false;
						isset($item->image->width) ? $m->ThumbnailWidth = $item->image->width : false;
					}
					isset($item->displayName) ? $m->Title = $item->displayName : false;
					isset($item->objectType) ? $m->Type = $item->objectType : false;
					isset($item->url) ? $m->URL = $item->url : false;
					$this->Media[] = $m;
				}
			}
			isset($activity->annotation) ? $this->Note = $activity->annotation : false;
			isset($activity->plusoners->totalItems) ? $this->PlusOneCount = $activity->object->plusoners->totalItems : false;
			isset($activity->replies->totalItems) ? $this->ReplyCount = $activity->object->replies->totalItems : false;
			if ($activity->verb == "share") {
				$this->Reshare = true;
				isset($activity->object->id) ? $this->ResharedID = $activity->object->id : false;
				isset($activity->object->actor) ? $this->ResharedUser = new Person($activity->object->actor,$api) : false;
			}
			isset($activity->resharers->totalItems) ? $this->ReshareCount = $activity->object->resharers->totalItems : false;
			isset($activity->provider->title) ? $this->Service = $activity->provider->title : false;
			isset($activity->title) ? $this->Title = $activity->title : false;
			isset($activity->object->objectType) ? $this->Type = $activity->object->objectType : false;
			isset($activity->updated) ? $this->UpdatedAt = date("Y-m-d H:i:s",strtotime($activity->updated)) : false;
			isset($activity->url) ? $this->URL = $activity->url : false;
			isset($activity->actor) ? $this->User = new Person($activity->actor,$api) : false;
		}

		/*
			Function: getComments
				Returns comments for this activity.

			Parameters:
				count - The number of comments to return (defaults to 500, max 500)
				order - The sort order for the results (options are "ascending" and "descending", defaults to "ascending" or oldest first)
				params - Additional parameters to pass to the activities/{activityId}/comments API call.

			Returns:
				A BigTree\GoogleResultSet of BigTree\GooglePlus\Comment objects.
		*/

		function getComments($count = 500,$order = "ascending",$params = array()) {
			return $this->API->getComments($this->ID,$count,$order,$params);
		}

	}
