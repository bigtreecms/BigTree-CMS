<?php
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"],$field["input"]);
	if ($date) {
		$field["output"] = $date->format("Y-m-d");
	} else {
		$field["output"] = "";
	}