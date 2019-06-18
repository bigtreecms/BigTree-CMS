<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	
	$data = Setting::value("bigtree-internal-payment-gateway");
	$data["service"] = "paypal";
	$data["settings"]["paypal-username"] = $_POST["paypal-username"];
	$data["settings"]["paypal-password"] = $_POST["paypal-password"];
	$data["settings"]["paypal-signature"] = $_POST["paypal-signature"];
	$data["settings"]["paypal-environment"] = $_POST["paypal-environment"];
	
	Setting::updateValue("bigtree-internal-payment-gateway", $data, true);
	Admin::growl("Developer","Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	