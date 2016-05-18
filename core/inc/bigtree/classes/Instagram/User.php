<?php
	/*
		Class: BigTree\Instagram\User
			An Instagram object that contains information about and methods you can perform on a user.
	*/

	namespace BigTree\Instagram;

	class User {

		/** @var \BigTree\Instagram\API */
		protected $API;

		public $Description;
		public $FollowersCount;
		public $FriendsCount;
		public $ID;
		public $Image;
		public $MediaCount;
		public $Name;
		public $URL;
		public $Username;

		/*
			Constructor:
				Creates a user object from Instagram data.

			Parameters:
				user - Instagram data
				api - Reference to the BigTree\Instagram\API class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			isset($user->bio) ? $this->Description = $user->bio : false;
			isset($user->counts->followed_by) ? $this->FollowersCount = $user->counts->followed_by : false;
			isset($user->counts->follows) ? $this->FriendsCount = $user->counts->follows : false;
			isset($user->id) ? $this->ID = $user->id : false;
			isset($user->profile_picture) ? $this->Image = $user->profile_picture : false;
			isset($user->counts->media) ? $this->MediaCount = $user->counts->media : false;
			isset($user->full_name) ? $this->Name = $user->full_name : false;
			isset($user->website) ? $this->URL = $user->website : false;
			isset($user->username) ? $this->Username = $user->username : false;
		}

		/*
			Function: getMedia
				Alias for BigTree\Instagram\API::getUserMedia
		*/

		function getMedia() {
			return $this->API->getUserMedia($this->ID);
		}

		/*
			Function: getFriends
				Alias for BigTree\Instagram\API::getFriends
		*/

		function getFriends() {
			return $this->API->getFriends($this->ID);
		}

		/*
			Function: getFollowers
				Alias for BigTree\Instagram\API::getFollowers
		*/

		function getFollowers() {
			return $this->API->getFollowers($this->ID);
		}

		/*
			Function: getRelationship
				Alias for BigTree\Instagram\API::getRelationship
		*/

		function getRelationship() {
			return $this->API->getRelationship($this->ID);
		}

		/*
			Function: setRelationship
				Alias for BigTree\Instagram\API::setRelationship
		*/

		function setRelationship($action) {
			return $this->API->setRelationship($this->ID,$action);
		}

	}
	