<?
	$breadcrumb[] = array("title" => "Payment Gateway", "link" => "developer/payment-gateway/");
	if (!$admin->settingExists("bigtree-internal-payment-gateway")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-payment-gateway",
			"system" => "on",
			"encrypted" => "on"
		));
		$admin->updateSettingValue("bigtree-internal-payment-gateway",array("service" => "", "settings" => array()));
	}
	$gateway = $cms->getSetting("bigtree-internal-payment-gateway");

	if ($gateway["service"] == "authorize.net") {
		$currently = "Authorize.Net";
		$class = "icon_developer_payment_authorize";
	} elseif ($gateway["service"] == "paypal") {
		$currently = "PayPal Payments Pro";
		$class = "icon_developer_payment_paypal";
	} elseif ($gateway["service"] == "payflow") {
		$currently = "PayPal Payflow Gateway";
		$class = "icon_developer_payment_payflow";
	} elseif ($gateway["service"] == "linkpoint") {
		$currently = "First Data / LinkPoint";
		$class = "icon_developer_payment_linkpoint";
	} else {
		$currently = "None";
		$class = "icon_developer_payment_gateway";
	}
?>