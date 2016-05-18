<?php
	/*
		Class: BigTree\Flickr\ResultSet
			An object that contains multiple results from a Flickr API query.
	*/

	namespace BigTree\Flickr;

	class ResultSet {
		
		/** @var \BigTree\Flickr\API */
		protected $API;
		protected $LastCall;
		protected $LastParameters;

		public $CurrentPage;
		public $Results;
		public $TotalPages;

		/*
			Constructor:
				Creates a result set of Flickr data.

			Parameters:
				api - An instance of BigTree\Flickr\API
				last_call - Method called on BigTree\Flickr\API
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$results,$current_page,$total_pages) {
			$this->API = $api;
			$this->CurrentPage = $current_page;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Results = $results;
			$this->TotalPages = $total_pages;
		}

		/*
			Function: nextPage
				Calls the previous method again (with modified parameters)

			Returns:
				A ResultSet with the next page of results.
		*/

		function nextPage() {
			if ($this->CurrentPage < $this->TotalPages) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage + 1;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}

		/*
			Function: previousPage
				Calls the previous method again (with modified parameters)

			Returns:
				A ResultSet with the next page of results.
		*/

		function previousPage() {
			if ($this->CurrentPage > 1) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage - 1;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}
		
	}