<?
	header("Content-type: text/json");
	$analytics = new BigTreeGoogleAnalytics;
		
	$profiles = $analytics->API->management_profiles->listManagementProfiles($_POST["account"], $_POST["property"]);
	$response = array();
	
	if (is_array($profiles->items)) {
		foreach ($profiles->items as $item) {
			$response[] = array("name" => $item->name, "id" => $item->id);
		}
	}
	echo json_encode($response);
?>