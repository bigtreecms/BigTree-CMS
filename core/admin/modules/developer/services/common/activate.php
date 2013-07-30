<?
	$api->Settings["key"] = $_POST["key"];
	$api->Settings["secret"] = $_POST["secret"];
	$api->oAuthRedirect();
?>