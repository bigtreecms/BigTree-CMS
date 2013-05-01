<?
	$field["output"] = array();
	// New data, normal module / page data.
	if (is_array($field["input"])) {
		foreach ($field["input"] as $i) {
			if (is_string($i)) {
				$field["output"][] = json_decode($i,true);
			} else {
				$field["output"][] = $i;
			}
		}
	// Callouts are going to keep this as a string.
	} elseif (is_string($field["input"])) {
		$field["output"] = json_decode($field["input"],true);
	}
?>