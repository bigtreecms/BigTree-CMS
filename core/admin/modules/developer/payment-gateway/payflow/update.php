<?
	$gateway = $cms->getSetting("bigtree-internal-payment-gateway");
	$gateway["service"] = "payflow";
	$gateway["settings"]["payflow-vendor"] = $_POST["payflow-vendor"];
	$gateway["settings"]["payflow-partner"] = $_POST["payflow-partner"];
	$gateway["settings"]["payflow-username"] = $_POST["payflow-username"];
	$gateway["settings"]["payflow-password"] = $_POST["payflow-password"];
	$gateway["settings"]["payflow-environment"] = $_POST["payflow-environment"];
	
	$admin->updateSettingValue("bigtree-internal-payment-gateway",$gateway);
	
	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect($developer_root);
?>