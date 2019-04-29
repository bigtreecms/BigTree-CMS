<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	$data = Setting::value("bigtree-internal-payment-gateway");
	$data["service"] = "authorize.net";
	$data["settings"]["authorize-api-login"] = $_POST["authorize-api-login"];
	$data["settings"]["authorize-transaction-key"] = $_POST["authorize-transaction-key"];
	$data["settings"]["authorize-environment"] = $_POST["authorize-environment"];
	
	Setting::updateValue("bigtree-internal-payment-gateway", $data, true);
	Utils::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	