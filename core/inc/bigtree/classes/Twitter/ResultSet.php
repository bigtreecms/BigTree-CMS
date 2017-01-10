<?php
	/*
		Class: BigTree\Twitter\ResultSet
			An object that contains multiple results from a Twitter API query.
	*/
	
	namespace BigTree\Twitter;
	
	use ArrayAccess;
	
	class ResultSet implements ArrayAccess{
		
		/** @var \BigTree\Twitter\API */
		protected $API;
		protected $LastCall;
		protected $LastParameters;
		
		public $Results;
		
		/*
			Constructor:
				Creates a result set of Twitter data.

			Parameters:
				api - An instance of BigTree\Twitter\API
				last_call - Method called on BigTree\Twitter\API
				params - The parameters sent to last call
				results - Results to store
		*/
		
		function __construct(API &$api, string $last_call, array $params, array $results) {
			$this->API = $api;
			$this->LastCall = $last_call;
			$last = end($results);
			// Set the max_id field on what would be the $params array sent to any call (since it's always last)
			$params[count($params) - 1]["max_id"] = $last->ID - 1;
			$this->LastParameters = $params;
			$this->Results = $results;
		}
		
		/*
			Function: nextPage
				Calls the previous method with a max_id of the last received ID.

			Returns:
				A BigTree\Twitter\ResultSet with the next page of results.
		*/
		
		function nextPage(): ?ResultSet {
			return call_user_func_array([$this->API, $this->LastCall], $this->LastParameters);
		}
		
		// Array iterator implementation
		function offsetSet($index, $value) {
			if (is_null($index)) {
				$this->Results[] = $value;
			} else {
				$this->Results[$index] = $value;
			}
		}
		
		function offsetExists($index) {
			return isset($this->Results[$index]);
		}
		
		function offsetUnset($index) {
			unset($this->Results[$index]);
		}
		
		function offsetGet($index) {
			return isset($this->Results[$index]) ? $this->Results[$index] : null;
		}
		
	}
