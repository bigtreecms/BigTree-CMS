<?php
	/*
		Class: BigTree\GooglePlus\Person
			A Google+ object that contains information about and methods you can perform on a person.
	*/

	namespace BigTree\GooglePlus;

	use stdClass;

	class Person {

		/** @var \BigTree\GooglePlus\API */
		protected $API;

		function __construct($person,&$api) {
			$this->API = $api;
			isset($person->ageRange->min) ? $this->AgeRangeMin = $person->ageRange->min : false;
			isset($person->ageRange->max) ? $this->AgeRangeMax = $person->ageRange->max : false;
			isset($person->birthday) ? $this->Birthday = $person->birthday : false;
			isset($person->braggingRights) ? $this->BraggingRights = $person->braggingRights : false;
			isset($person->circledByCount) ? $this->CircledByCount = $person->circledByCount : false;
			if (isset($person->cover)) {
				$this->Cover = new stdClass;
				isset($person->cover->coverPhoto->height) ? $this->Cover->Height = $person->cover->coverPhoto->height : false;
				isset($person->cover->layout) ? $this->Cover->Layout = $person->cover->layout : false;
				isset($person->cover->coverInfo->leftImageOffset) ? $this->Cover->OffsetLeft = $person->cover->coverInfo->leftImageOffset : false;
				isset($person->cover->coverInfo->topImageOffset) ? $this->Cover->OffsetTop = $person->cover->coverInfo->topImageOffset : false;
				isset($person->cover->coverPhoto->url) ? $this->Cover->Photo = $person->cover->coverPhoto->url : false;
				isset($person->cover->coverPhoto->width) ? $this->Cover->Width = $person->cover->coverPhoto->width : false;
			}
			isset($person->currentLocation) ? $this->CurrentLocation = $person->currentLocation : false;
			isset($person->aboutMe) ? $this->Description = $person->aboutMe : false;
			isset($person->displayName) ? $this->DisplayName = $person->displayName : false;
			if (is_array($person->organizations)) {
				$this->Education = array();
				$this->Employment = array();
				foreach ($person->organizations as $org) {
					$o = new stdClass;
					isset($org->name) ? $o->Name = $org->name : false;
					isset($org->title) ? $o->Title = $org->title : false;
					isset($org->startDate) ? $o->StartDate = date("Y-m-d",strtotime($org->startDate)) : false;
					isset($org->endDate) ? $o->EndDate = date("Y-m-d",strtotime($org->endDate)) : false;
					isset($org->primary) ? $o->Primary = $org->primary : false;
					if ($org->type == "school") {
						$this->Education[] = $o;
					} elseif ($org->type == "work") {
						$this->Employment[] = $o;
					}
				}
			}
			if (is_array($person->emails)) {
				$this->Emails = array();
				foreach ($person->emails as $e) {
					$email = new stdClass;
					isset($e->value) ? $email->Address = $e->value : false;
					isset($e->primary) ? $email->Primary = $e->primary : false;
					isset($e->type) ? $email->Type = $e->type : false;
					$this->Emails[] = $email;
				}
			}
			isset($person->gender) ? $this->Gender = $person->gender : false;
			isset($person->id) ? $this->ID = $person->id : false;
			isset($person->hasApp) ? $this->HasApp = $person->hasApp : false;
			isset($person->image->url) ? $this->Image = $person->image->url : false;
			if (is_array($person->urls)) {
				$this->Links = array();
				foreach ($person->urls as $url) {
					$link = new stdClass;
					isset($url->label) ? $link->Name = $url->label : false;
					isset($url->primary) ? $link->Primary = $url->primary : false;
					isset($url->type) ? $link->Type = $url->type : false;
					isset($url->value) ? $link->URL = $url->value : false;
					$this->Links[] = $link;
				}
			}
			isset($person->isPlusUser) ? $this->IsPlusUser = $person->isPlusUser : false;
			isset($person->language) ? $this->Language = $person->language : false;
			isset($person->name) ? $this->Name = new stdClass : false;
			isset($person->name->formatted) ? $this->Name->Formatted = $person->name->formatted : false;
			isset($person->name->honorificPrefix) ? $this->Name->Prefix = $person->name->honorificPrefix : false;
			isset($person->name->givenName) ? $this->Name->First = $person->name->givenName : false;
			isset($person->name->middleName) ? $this->Name->Middle = $person->name->middleName : false;
			isset($person->name->familyName) ? $this->Name->Last = $person->name->familyName : false;
			isset($person->name->honorificSuffix) ? $this->Name->Suffix = $person->name->honorificSuffix : false;
			isset($person->nickname) ? $this->Name->Preferred = $person->nickname : false;
			if (is_array($person->placesLived)) {
				$this->Places = array();
				foreach ($person->placesLived as $pl) {
					$loc = new stdClass;
					isset($pl->value) ? $loc->Location = $pl->value : false;
					isset($pl->primary) ? $loc->Primary = $pl->primary : false;
					$this->Places[] = $loc;
				}
			}
			isset($person->plusOneCount) ? $this->PlusOneCount = $person->plusOneCount : false;
			isset($person->relationshipStatus) ? $this->RelationshipStatus = $person->relationshipStatus : false;
			isset($person->tagline) ? $this->Tagline = $person->tagline : false;
			isset($person->objectType) ? $this->Type = $person->objectType : false;
			isset($person->url) ? $this->URL = $person->url : false;
			isset($person->verified) ? $this->Verified = $person->verified : false;
		}

		/*
			Function: getActivities
				Returns a list of public activities made by this person.

			Parameters:
				count - The number of results to return, (defaults to 100, max 100)
				params - Additional parameters to pass to the people/{userId}/activities API call.

			Returns:
				A BigTreeGoogleResultSet of BigTree\GooglePlus\Activity objects.
		*/

		function getActivities($count = 100,$params = array()) {
			return $this->API->getActivities($this->ID,$count,$params);
		}

		/*
			Function: getCircledPeople
				Returns a list of people this user has in one or more circles.

			Parameters:
				count - The number of results to return, (defaults to 100, max 100)
				order - The sort order for the results (options are "best" and "alphabetical", defaults to "best")
				params - Additional parameters to pass to the people/{userId}/people API call.

			Returns:
				A BigTreeGoogleResultSet of BigTree\GooglePlus\People objects.
		*/

		function getCircledPeople($count = 100,$order = "best",$params = array()) {
			return $this->API->getCircledPeople($this->ID,$count,$order,$params);
		}
		
	}
