<?
	$api->Settings["key"] = $_POST["key"];
	$api->Settings["secret"] = $_POST["secret"];
	$api->Settings["test_environment"] = $_POST["test_environment"];
	$api->oAuthRedirect();
?>