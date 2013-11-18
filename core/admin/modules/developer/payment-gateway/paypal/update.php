<?
	$gateway->Service = "paypal";
	$gateway->Settings["paypal-username"] = $_POST["paypal-username"];
	$gateway->Settings["paypal-password"] = $_POST["paypal-password"];
	$gateway->Settings["paypal-signature"] = $_POST["paypal-signature"];
	$gateway->Settings["paypal-environment"] = $_POST["paypal-environment"];
	$gateway->saveSettings();
	
	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect(DEVELOPER_ROOT);
?>