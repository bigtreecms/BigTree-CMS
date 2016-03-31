<?php
	/*
		Class: BigTree\Twitter\ResultSet
			An object that contains multiple results from a Twitter API query.
	*/

	namespace BigTree\Twitter;

	class ResultSet {

		/*
			Constructor:
				Creates a result set of Twitter data.

			Parameters:
				api - An instance of BigTreeTwitterAPI
				last_call - Method called on BigTreeTwitterAPI
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$results) {
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
				A BigTreeTwitterResultSet with the next page of results.
		*/

		function nextPage() {
			return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
		}
		
	}
