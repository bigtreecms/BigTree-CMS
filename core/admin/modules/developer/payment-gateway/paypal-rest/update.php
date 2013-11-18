<?
	$gateway->Service = "paypal-rest";
	$gateway->Settings["paypal-rest-client-id"] = $_POST["paypal-rest-client-id"];
	$gateway->Settings["paypal-rest-client-secret"] = $_POST["paypal-rest-client-secret"];
	$gateway->Settings["paypal-rest-environment"] = $_POST["paypal-rest-environment"];
	$gateway->saveSettings();

	if (!$gateway->paypalRESTTokenRequest()) {
		$admin->growl("PayPal REST API",$gateway->Error,"error");
		BigTree::redirect(DEVELOPER_ROOT."payment-gateway/paypal-rest/");
	}

	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect(DEVELOPER_ROOT);
?>