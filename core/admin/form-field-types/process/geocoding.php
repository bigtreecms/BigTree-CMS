<?
	// Loop through all the fields to build the address
	$fields = explode(",",$options["fields"]);
	$location = array();
	foreach ($fields as $field) {
		$location[] = $data[trim($field)];
	}
	
	// Geocode
	$geo = BigTree::geocodeAddress(implode(", ",$location));
	
	// If it's false, we didn't get anything.
	if (!$geo) {
		$item["latitude"] = false;
		$item["longitude"] = false;
	} else {
		$item["latitude"] = $geo["latitude"];
		$item["longitude"] = $geo["longitude"];
	}
	
	// This field doesn't have it's own key to process.
	$no_process = true;
?>