<?php
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a",$field["input"]);
	if ($date) {
		$field["output"] = $date->format("Y-m-d H:i:s");
	} else {
		$field["output"] = "";
	}