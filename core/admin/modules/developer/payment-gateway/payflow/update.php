<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	$data = Setting::value("bigtree-internal-payment-gateway");
	$data["service"] = "payflow";
	$data["settings"]["payflow-vendor"] = $_POST["payflow-vendor"];
	$data["settings"]["payflow-partner"] = $_POST["payflow-partner"];
	$data["settings"]["payflow-username"] = $_POST["payflow-username"];
	$data["settings"]["payflow-password"] = $_POST["payflow-password"];
	$data["settings"]["payflow-environment"] = $_POST["payflow-environment"];
	
	Setting::updateValue("bigtree-internal-payment-gateway", $data, true);
	Utils::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	