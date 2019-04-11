<?php
	/*
		Class: BigTree\GoogleResultSet
			Common result set class for Google+, YouTube, and Google Analytics.
	*/
	
	namespace BigTree;
	
	use ArrayAccess;
	use BigTree\Disqus\ResultSet;
	use stdClass;
	
	class GoogleResultSet implements ArrayAccess
	{
		
		public $API;
		public $LastCall = "";
		public $LastParameters = [];
		public $NextPageToken;
		public $PreviousPageToken;
		public $Results = [];
		
		/*
			Constructor:
				Creates a result set of Google data.

			Parameters:
				api - An instance of your Google-related API class.
				last_call - Method called on the API class.
				params - The parameters sent to last call
				data - Result from the API call
				results - Results to store
		*/
		
		public function __construct(&$api, string $last_call, array $params, stdClass $data, array $results)
		{
			$this->API = $api;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->NextPageToken = $data->nextPageToken;
			$this->PreviousPageToken = $data->prevPageToken;
			$this->Results = $results;
		}
		
		/*
			Function: nextPage
				Calls the previous method and gets the next page of results.

			Returns:
				A BigTree\GoogleResultSet or false if there is not another page.
		*/
		
		public function nextPage(): ?ResultSet
		{
			if ($this->NextPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->NextPageToken;
				
				return call_user_func_array([$this->API, $this->LastCall], $params);
			}
			
			return null;
		}
		
		// Array iterator implementation
		public function offsetSet($index, $value)
		{
			if (is_null($index)) {
				$this->Results[] = $value;
			} else {
				$this->Results[$index] = $value;
			}
		}
		
		public function offsetExists($index)
		{
			return isset($this->Results[$index]);
		}
		
		public function offsetUnset($index)
		{
			unset($this->Results[$index]);
		}
		
		public function offsetGet($index)
		{
			return isset($this->Results[$index]) ? $this->Results[$index] : null;
		}
		
		/*
			Function: previousPage
				Calls the previous method and gets the previous page of results.

			Returns:
				A BigTree\GoogleResultSet or false if there is not a previous page.
		*/
		
		public function previousPage(): ?ResultSet
		{
			if ($this->PreviousPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->PreviousPageToken;
				
				return call_user_func_array([$this->API, $this->LastCall], $this->LastParameters);
			}
			
			return null;
		}
		
	}
	