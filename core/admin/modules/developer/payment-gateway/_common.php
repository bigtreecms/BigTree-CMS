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
	} elseif ($gateway["service"] == "paypal") {
		$currently = "PayPal Payments Pro";
	} elseif ($gateway["service"] == "payflow") {
		$currently = "PayPal Payflow Gateway";
	} elseif ($gateway["service"] == "linkpoint") {
		$currently = "First Data / LinkPoint";
	} else {
		$currently = "None";
	}
?>