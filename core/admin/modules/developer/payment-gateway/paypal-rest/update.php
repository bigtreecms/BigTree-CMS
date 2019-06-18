<?php
	namespace BigTree;
	
	CSRF::verify();
	
	
	$data = Setting::value("bigtree-internal-payment-gateway");
	$data["service"] = "paypal-rest";
	$data["settings"]["paypal-rest-client-id"] = trim($_POST["paypal-rest-client-id"]);
	$data["settings"]["paypal-rest-client-secret"] = trim($_POST["paypal-rest-client-secret"]);
	$data["settings"]["paypal-rest-environment"] = $_POST["paypal-rest-environment"];
	
	Setting::updateValue("bigtree-internal-payment-gateway", $data, true);
	
	$gateway = new PaymentGateway\PayPalREST;
	
	if (!$gateway->getToken()) {
		Admin::growl("PayPal REST API", $gateway->Errors[0], "error");
		Router::redirect(DEVELOPER_ROOT."payment-gateway/paypal-rest/");
	}
	
	Admin::growl("Developer", "Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	