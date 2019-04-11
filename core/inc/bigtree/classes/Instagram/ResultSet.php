<?php
	/*
		Class: BigTree\Instagram\ResultSet
			An object that contains multiple results from an Instagram API query.
	*/
	
	namespace BigTree\Instagram;
	
	use ArrayAccess;
	
	class ResultSet implements ArrayAccess
	{
		
		/** @var API */
		protected $API;
		protected $LastCall;
		protected $LastParameters;
		
		public $Results;
		
		/*
			Constructor:
				Creates a result set of Instagram data.

			Parameters:
				api - An instance of BigTree\Instagram\API
				last_call - Method called on BigTree\Instagram\API
				params - The parameters sent to last call
				results - Results to store
		*/
		
		function __construct(API &$api, string $last_call, array $params, array $results)
		{
			$this->API = $api;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Results = $results;
		}
		
		/*
			Function: nextPage
				Calls the previous method again (with modified parameters)

			Returns:
				A BigTree\Instagram\ResultSet with the next page of results.
		*/
		
		function nextPage(): ?ResultSet
		{
			return call_user_func_array([$this->API, $this->LastCall], $this->LastParameters);
		}
		
		// Array iterator implementation
		function offsetSet($index, $value): void
		{
			if (is_null($index)) {
				$this->Results[] = $value;
			} else {
				$this->Results[$index] = $value;
			}
		}
		
		function offsetExists($index): bool
		{
			return isset($this->Results[$index]);
		}
		
		function offsetUnset($index): void
		{
			unset($this->Results[$index]);
		}
		
		function offsetGet($index)
		{
			return isset($this->Results[$index]) ? $this->Results[$index] : null;
		}
		
	}
	