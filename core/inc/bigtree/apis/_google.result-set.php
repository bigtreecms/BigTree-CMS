<?
	/*
		Class: BigTreeGoogleResultSet
			Common result set class for Google+, YouTube, and Google Analytics.
	*/

	class BigTreeGoogleResultSet {

		/*
			Constructor:
				Creates a result set of Google data.

			Parameters:
				api - An instance of your Google-related API class.
				last_call - Method called on the API class.
				params - The parameters sent to last call
				results - Results to store
		*/

		function __construct(&$api,$last_call,$params,$data,$results) {
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
				A BigTreeGoogleResultSet or false if there is not another page.
		*/

		function nextPage() {
			if ($this->NextPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->NextPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$params);
			}
			return false;
		}

		/*
			Function: previousPage
				Calls the previous method and gets the previous page of results.

			Returns:
				A BigTreeGoogleResultSet or false if there is not a previous page.
		*/

		function previousPage() {
			if ($this->PreviousPageToken) {
				$params = $this->LastParameters;
				$params[count($params) - 1]["pageToken"] = $this->PreviousPageToken;
				return call_user_func_array(array($this->API,$this->LastCall),$this->LastParameters);
			}
			return false;
		}
	}
?>