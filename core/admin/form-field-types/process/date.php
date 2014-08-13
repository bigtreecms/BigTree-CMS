<?
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"],$field["input"]);
	$field["output"] = $date->format("Y-m-d");
?>