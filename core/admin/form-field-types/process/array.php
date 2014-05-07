<?
	$field["output"] = array();
	foreach ($field["input"] as $i) {
		$row = array();
		// Callouts may have the data already decoded.
		if (is_string($i)) {
			$i = json_decode($i,true);
		}
		// Run through the fields and htmlspecialchar the non-HTML ones.
		foreach ($field["options"]["fields"] as $array_field) {
			if ($array_field["type"] == "html") {
				$row[$array_field["key"]] = $i[$array_field["key"]];
			} else {
				$row[$array_field["key"]] = BigTree::safeEncode($i[$array_field["key"]]);
			}
		}
		$field["output"][] = $row;
	}
?>