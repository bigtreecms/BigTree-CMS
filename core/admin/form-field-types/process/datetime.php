<?
	$date = DateTime::createFromFormat($bigtree["config"]["date_format"]." h:i a",$field["input"]);
	$field["output"] = $date->format("Y-m-d H:i:s");
?>