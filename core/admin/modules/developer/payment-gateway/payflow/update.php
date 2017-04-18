<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	$gateway->Service = "payflow";
	$gateway->Settings["payflow-vendor"] = $_POST["payflow-vendor"];
	$gateway->Settings["payflow-partner"] = $_POST["payflow-partner"];
	$gateway->Settings["payflow-username"] = $_POST["payflow-username"];
	$gateway->Settings["payflow-password"] = $_POST["payflow-password"];
	$gateway->Settings["payflow-environment"] = $_POST["payflow-environment"];
	$gateway->Setting->save();
	
	Utils::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	