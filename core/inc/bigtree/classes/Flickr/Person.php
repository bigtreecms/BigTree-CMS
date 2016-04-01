<?php
	/*
		Class: BigTree\Flickr\Person
			A Flickr object that contains information about and methods you can perform on a person.
	*/

	namespace BigTree\Flickr;

	class Person {

		protected $API;

		function __construct($person,&$api) {
			$this->API = $api;
			isset($person->description->_content) ? $this->Description = $person->description->_content : false;
			$this->ID = isset($person->nsid) ? $person->nsid : $person->id;
			isset($person->iconfarm) ? $this->Image = "http://farm".$person->iconfarm.".staticflickr.com/".$person->iconserver."/buddyicons/".$person->nsid.".jpg" : false;
			isset($person->location) ? $this->Location = isset($person->location->_content) ? $person->location->_content : $person->location : false;
			isset($person->mobileurl->_content) ? $this->MobileURL = $person->mobileurl->_content : false;
			isset($person->realname) ? $this->Name = isset($person->realname->_content) ? $person->realname->_content : $person->realname : false;
			isset($person->photos->count->_content) ? $this->PhotoCount = $person->photos->count->_content : false;
			isset($person->photosurl->_content) ? $this->PhotosURL = $person->photosurl->_content : false;
			isset($person->photos->views->_content) ? $this->PhotoViews = $person->photos->views->_content : false;
			isset($person->ispro) ? $this->ProAccount = $person->ispro : false;
			isset($person->profileurl->_content) ? $this->ProfileURL = $person->profileurl->_content : false;
			isset($person->username) ? $this->Username = isset($person->username->_content) ? $person->username->_content : $person->username : false;
		}

		/*
			Function: getGroups
				Returns the groups this person is a member of.

			Returns:
				An array of BigTree\Flickr\Group objects or false if the call fails.
		*/

		function getGroups() {
			$response = $this->API->call("flickr.people.getGroups",array("user_id" => $this->ID));

			if (!isset($response->groups)) {
				return false;
			}

			$groups = array();
			foreach ($response->groups->group as $group) {
				$groups[] = new Group($group,$this->API);
			}

			return $groups;
		}

		/*
			Function: getPhotos
				Returns the photos this person has uploaded.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getPhotos($per_page = 100,$params = array()) {
			return $this->API->getPhotosForPerson($this->ID,$per_page,$params);
		}

		/*
			Function: getPhotosOf
				Returns photos of this person.

			Parameters:
				per_page - Number of photos per page, defaults to 100, max of 500.
				params - Additional parameters to pass to the flickr.people.getPhotos API call

			Returns:
				A ResultSet of Photo objects or false if the call fails.
		*/

		function getPhotosOf($per_page = 100,$params = array()) {
			return $this->API->getPhotosOfPerson($this->ID,$per_page,$params);
		}

	}