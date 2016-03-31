<?php
	/*
		Class: BigTreeTwitterUser
			A Twitter object that contains information about and methods you can perform on a user.
	*/

	namespace BigTree\Twitter;

	class User {

		protected $API;

		/*
			Constructor:
				Creates a user object from Twitter data.

			Parameters:
				user - Twitter data
				api - Reference to the BigTreeTwiterAPI class instance
		*/

		function __construct($user,&$api) {
			$this->API = $api;
			isset($user->description) ? $this->Description = $user->description : false;
			isset($user->favourites_count) ? $this->Favorites = $user->favourites_count : false;
			isset($user->followers_count) ? $this->FollowersCount = $user->followers_count : false;
			isset($user->following) ? $this->Following = $user->following : false;
			isset($user->friends_count) ? $this->FriendsCount = $user->friends_count : false;
			isset($user->geo_enabled) ? $this->GeoEnabled = $user->geo_enabled : false;
			isset($user->id) ? $this->ID = $user->id : false;
			isset($user->profile_image_url) ? $this->Image = $user->profile_image_url : false;
			isset($user->profile_image_url_https) ? $this->ImageHTTPS = $user->profile_image_url_https : false;
			isset($user->lang) ? $this->Language = $user->lang : false;
			isset($user->listed_count) ? $this->ListedCount = $user->listed_count : false;
			isset($user->location) ? $this->Location = $user->location : false;
			isset($user->name) ? $this->Name = $user->name : false;
			isset($user->protected) ? $this->Protected = $user->protected : false;
			isset($user->created_at) ? $this->Timestamp = date("Y-m-d H:i:s",strtotime($user->created_at)) : false;
			isset($user->time_zone) ? $this->Timezone = $user->time_zone : false;
			isset($user->utc_offset) ? $this->TimezoneOffset = $user->utc_offset : false;
			isset($user->statuses_count) ? $this->TweetCount = $user->statuses_count : false;
			isset($user->screen_name) ? $this->Username = $user->screen_name : false;
			isset($user->url) ? $this->URL = $user->url : false;
			isset($user->verified) ? $this->Verified = $user->verified : false;
		}

		/*
			Function: __toString
				Returns the User's username when this object is treated as a string.
		*/

		function __toString() {
			return $this->Username;
		}

		/*
			Function: block
				Blocks the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function block() {
			return $this->API->blockUser($this->ID);
		}

		/*
			Function: follow / friend
				Friends/follows the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function follow() {
			return $this->API->followUser($this->ID);
		}
		function friend() {
			return $this->follow();
		}

		/*
			Function: unblock
				Unblocks the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function unblock() {
			return $this->API->unblockUser($this->ID);
		}

		/*
			Function: unfollow / unfriend
				Unfriends/unfollows the user.

			Returns:
				A BigTreeTwitterUser object on success.
		*/

		function unfollow() {
			return $this->API->unfollowUser($this->ID);
		}
		function unfriend() {
			return $this->unfollow();
		}
		
	}
