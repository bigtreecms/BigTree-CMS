<?
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
<div class="table">
	<summary><h2>Currently Using<small><?=$currently?></small></h2></summary>
	<section>
		<p>Choose a service below to configure your payment gateway settings.</p>
		<a class="box_select" href="authorize/">
			<span class="authorize"></span>
			<p>Authorize.Net</p>
		</a>
		<a class="box_select" href="paypal/">
			<span class="paypal"></span>
			<p>PayPal Payments Pro</p>
		</a>
		<a class="box_select" href="payflow/">
			<span class="payflow"></span>
			<p>PayPal Payflow Gateway</p>
		</a>
		<a class="box_select" href="linkpoint/">
			<span class="linkpoint"></span>
			<p>First Data / LinkPoint</p>
		</a>
	</section>
</div>