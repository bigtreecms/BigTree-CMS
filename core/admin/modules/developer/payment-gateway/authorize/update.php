<?
	$gateway = $cms->getSetting("bigtree-internal-payment-gateway");
	$gateway["service"] = "authorize.net";
	$gateway["settings"]["authorize-api-login"] = $_POST["authorize-api-login"];
	$gateway["settings"]["authorize-transaction-key"] = $_POST["authorize-transaction-key"];
	$gateway["settings"]["authorize-environment"] = $_POST["authorize-environment"];
	
	$admin->updateSettingValue("bigtree-internal-payment-gateway",$gateway);
	
	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect($developer_root);
?>