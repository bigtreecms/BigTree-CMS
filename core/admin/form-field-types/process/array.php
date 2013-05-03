<?
	$field["output"] = array();
	// We're looping through the options now because we don't want to include _current-value_ things.
	foreach ($field["input"] as $i) {
		$row = array();
		// If it's freshly saved it's JSON encoded
		if (is_string($i)) {
			$i = json_decode($i,true);
		}
		foreach ($field["options"]["fields"] as $f) {
			$row[$f["key"]] = $i[$f["key"]];
		}
		$field["output"][] = $row;
	}
?>