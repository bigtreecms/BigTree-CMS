<?
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a",$field["input"]);
	// Fallback to SQL standards for existing values
	if (!$date) {
		$date = DateTime::createFromFormat("Y-m-d H:i:s",$field["input"]);
	}

	if ($date) {
		$field["output"] = $date->format("Y-m-d H:i:s");
	} else {
		$field["output"] = "";
	}
?>