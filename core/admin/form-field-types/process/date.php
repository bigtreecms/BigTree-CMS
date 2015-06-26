<?php
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"],$field["input"]);
	// Fallback to SQL standards for existing values
	if (!$date) {
		$date = DateTime::createFromFormat("Y-m-d",$field["input"]);
	}

	if ($date) {
		$field["output"] = $date->format("Y-m-d");
	} else {
		$field["output"] = "";
	}