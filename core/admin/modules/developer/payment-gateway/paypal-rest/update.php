<?php
	namespace BigTree;
	
	$gateway->Service = "paypal-rest";
	$gateway->Settings["paypal-rest-client-id"] = trim($_POST["paypal-rest-client-id"]);
	$gateway->Settings["paypal-rest-client-secret"] = trim($_POST["paypal-rest-client-secret"]);
	$gateway->Settings["paypal-rest-environment"] = $_POST["paypal-rest-environment"];
	$gateway->saveSettings();

	if (!$gateway->paypalRESTTokenRequest()) {
		Utils::growl("PayPal REST API",$gateway->Errors[0],"error");
		Router::redirect(DEVELOPER_ROOT."payment-gateway/paypal-rest/");
	}

	Utils::growl("Developer","Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	