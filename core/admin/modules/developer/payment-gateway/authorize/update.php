<?
	$gateway->Service = "authorize.net";
	$gateway->Settings["authorize-api-login"] = $_POST["authorize-api-login"];
	$gateway->Settings["authorize-transaction-key"] = $_POST["authorize-transaction-key"];
	$gateway->Settings["authorize-environment"] = $_POST["authorize-environment"];
	$gateway->saveSettings();

	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect(DEVELOPER_ROOT);
?>