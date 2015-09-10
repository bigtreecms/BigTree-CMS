<?
	$api->Settings["key"] = trim($_POST["key"]);
	$api->Settings["secret"] = trim($_POST["secret"]);
	$api->Settings["test_environment"] = $_POST["test_environment"];
	$api->oAuthRedirect();
?>