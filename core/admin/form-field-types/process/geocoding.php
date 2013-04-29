<?
	// Loop through all the fields to build the address
	$fields = explode(",",$options["fields"]);
	$location = array();
	foreach ($fields as $field) {
		$location[] = $data[trim($field)];
	}
	
	if (trim($location)) {
		// Geocode
		$geocoder = new BigTreeGeocoding;
		$result = $geocoder->geocode(implode(", ",$location));
		
		// If it's false, we didn't get anything.
		if (!$result) {
			$item["latitude"] = false;
			$item["longitude"] = false;
		} else {
			$item["latitude"] = $result["latitude"];
			$item["longitude"] = $result["longitude"];
		}
	}
		
	// This field doesn't have it's own key to process.
	$no_process = true;
?>