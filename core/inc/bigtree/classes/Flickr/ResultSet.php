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
				current_page - Current page number
				total_pages - Total number of pages
		*/
		
		function __construct(API &$api, string $last_call, array $params, array $results, int $current_page,
							 int $total_pages) {
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
		
		function nextPage(): ?ResultSet {
			if ($this->CurrentPage < $this->TotalPages) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage + 1;
				
				return call_user_func_array([$this->API, $this->LastCall], $params);
			}
			
			return null;
		}
		
		/*
			Function: previousPage
				Calls the previous method again (with modified parameters)

			Returns:
				A ResultSet with the next page of results.
		*/
		
		function previousPage(): ?ResultSet {
			if ($this->CurrentPage > 1) {
				$params = $this->LastParameters;
				$params["page"] = $this->CurrentPage - 1;
				
				return call_user_func_array([$this->API, $this->LastCall], $params);
			}
			
			return null;
		}
		
	}