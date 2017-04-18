<?php
	namespace BigTree;
	
	/**
	 * @global PaymentGateway\Provider $gateway
	 */
	
	CSRF::verify();
	
	$gateway->Service = "authorize.net";
	$gateway->Settings["authorize-api-login"] = $_POST["authorize-api-login"];
	$gateway->Settings["authorize-transaction-key"] = $_POST["authorize-transaction-key"];
	$gateway->Settings["authorize-environment"] = $_POST["authorize-environment"];
	$gateway->Setting->save();
	
	Utils::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	