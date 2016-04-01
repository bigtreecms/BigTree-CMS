<?php
	/*
		Class: BigTree\Instagram\ResultSet
			An object that contains multiple results from an Instagram API query.
	*/

	namespace BigTree\Instagram;

	class ResultSet {

		/*
			Constructor:
				Creates a result set of Instagram data.

			Parameters:
				api - An instance of BigTree\Instagram\API
				last_call - Method called on BigTree\Instagram\API
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$results) {
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

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
		
	}
	