<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// Loop through all the fields to build the address
	if (is_array($this->Settings["fields"])) {
		$source_fields = $this->Settings["fields"];
	} else {
		$source_fields = explode(",", $this->Settings["fields"]);
	}
	
	$location = [];
	
	foreach ($source_fields as $source_field) {
		$data = isset($bigtree["post_data"][trim($source_field)]) ? $bigtree["post_data"][trim($source_field)] : false;
		
		if (is_array($data)) {
			$location = array_merge($location, $data);
		} elseif ($data) {
			$location[] = $data;
		}
	}
	
	if (count($location)) {
		$result = new Geocode(implode(", ", $location));
		
		// If it's false, we didn't get anything.
		if (!strval($result)) {
			$bigtree["entry"]["latitude"] = false;
			$bigtree["entry"]["longitude"] = false;
			$bigtree["errors"][] = ["field" => "Geocoding", "error" => $result->Error];
		} else {
			$bigtree["entry"]["latitude"] = $result->Latitude;
			$bigtree["entry"]["longitude"] = $result->Longitude;
		}
	}
	
	// This field doesn't have it's own key to process.
	$this->Ignore = true;
	