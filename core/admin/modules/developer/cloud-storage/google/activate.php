<?
	$api->Settings["key"] = $_POST["key"];
	$api->Settings["secret"] = $_POST["secret"];
	$api->Settings["project"] = $_POST["project"];
	$api->oAuthRedirect();
?>