<?
	// Loop through all the fields to build the address
	$fields = explode(",",$field["options"]["fields"]);
	$location = array();
	foreach ($fields as $field) {
		$location[] = $bigtree["post_data"][trim($field)];
	}
	
	if (trim($location)) {
		// Geocode
		$geocoder = new BigTreeGeocoding;
		$result = $geocoder->geocode(implode(", ",$location));
		
		// If it's false, we didn't get anything.
		if (!$result) {
			$bigtree["parsed_data"]["latitude"] = false;
			$bigtree["parsed_data"]["longitude"] = false;
		} else {
			$bigtree["parsed_data"]["latitude"] = $result["latitude"];
			$bigtree["parsed_data"]["longitude"] = $result["longitude"];
		}
	}
		
	// This field doesn't have it's own key to process.
	$field["ignore"] = true;
?>