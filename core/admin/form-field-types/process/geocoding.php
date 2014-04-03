<?
	// Loop through all the fields to build the address
	$source_fields = explode(",",$field["options"]["fields"]);
	$location = array();
	foreach ($source_fields as $source_field) {
		$data = isset($bigtree["post_data"][trim($source_field)]) ? $bigtree["post_data"][trim($source_field)] : false;
		if (is_array($data)) {
			$location = array_merge($location,$data);
		} elseif ($data) {
			$location[] = $data;
		}
	}
	
	if (count($location)) {
		// Geocode
		$geocoder = new BigTreeGeocoding;
		$result = $geocoder->geocode(implode(", ",$location));
		
		// If it's false, we didn't get anything.
		if (!$result) {
			$bigtree["entry"]["latitude"] = false;
			$bigtree["entry"]["longitude"] = false;
		} else {
			$bigtree["entry"]["latitude"] = $result["latitude"];
			$bigtree["entry"]["longitude"] = $result["longitude"];
		}
	}
		
	// This field doesn't have it's own key to process.
	$field["ignore"] = true;
?>