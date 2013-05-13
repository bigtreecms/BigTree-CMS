<?
	$field["output"] = array();
	foreach ($field["input"] as $i) {
		$row = array();
		// If it's freshly saved it's JSON encoded
		if (is_string($i)) {
			$i = json_decode($i,true);
		}
		$field["output"][] = $i;
	}
?>