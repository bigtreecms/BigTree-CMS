<?
	$gateway = $cms->getSetting("bigtree-internal-payment-gateway");
	$gateway["service"] = "paypal";
	$gateway["settings"]["paypal-username"] = $_POST["paypal-username"];
	$gateway["settings"]["paypal-password"] = $_POST["paypal-password"];
	$gateway["settings"]["paypal-signature"] = $_POST["paypal-signature"];
	$gateway["settings"]["paypal-environment"] = $_POST["paypal-environment"];
	
	$admin->updateSettingValue("bigtree-internal-payment-gateway",$gateway);
	
	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect($developer_root);
?>