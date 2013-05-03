<?
	$field["output"] = array();
	foreach ($field["input"] as $i) {
		if (is_string($i)) {
			$field["output"][] = json_decode($i,true);
		} else {
			$field["output"][] = $i;
		}
	}
?>