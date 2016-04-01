<?php
	/*
		Class: BigTree\Disqus\ResultSet
			An object that contains multiple results from a Disqus API query.
	*/

	namespace BigTree\Disqus;

	class ResultSet {

		/*
			Constructor:
				Creates a result set of Disqus data.

			Parameters:
				object - An instance of an object that is creating this result set.
				last_call - Method called on the object.
				params - The parameters sent to last call.
				cursor - Disqus cursor data.
				results - Results to store.
		*/

		function __construct(&$object,$last_call,$params,$cursor,$results) {
			$this->Cursor = $cursor;
			$this->LastCall = $last_call;
			$this->LastParameters = $params;
			$this->Object = $object;
			$this->Results = $results;
		}

		/*
			Function: nextPage
				Returns the next page in the result set.

			Returns:
				A BigTreeDisqusResultSet with the next page of results or false if there isn't another page.
		*/

		function nextPage() {
			if (!$this->Cursor->Next) {
				return false;
			}
			$params = $this->LastParameters;
			$params[count($params) - 1]["cursor"] = $this->Cursor->Next;
			return call_user_func_array(array($this->Object,$this->LastCall),$params);
		}

		/*
			Function: previousPage
				Returns the previous page in the result set.

			Returns:
				A BigTreeDisqusResultSet with the next page of results or false if there isn't a previous page.
		*/

		function previousPage() {
			if (!$this->Cursor->Previous) {
				return false;
			}
			$params = $this->LastParameters;
			$params[count($params) - 1]["cursor"] = $this->Cursor->Previous;
			return call_user_func_array(array($this->Object,$this->LastCall),$params);
		}
		
	}
