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
?>
<div class="table">
	<section>
		<p>Choose a service below to configure your payment gateway settings.</p>
		<a class="box_select<? if ($gateway["service"] == "authorize.net") { ?> connected<? } ?>" href="authorize/">
			<span class="authorize"></span>
			<p>Authorize.Net</p>
		</a>
		<a class="box_select<? if ($gateway["service"] == "paypal") { ?> connected<? } ?>" href="paypal/">
			<span class="paypal"></span>
			<p>PayPal Payments Pro</p>
		</a>
		<a class="box_select<? if ($gateway["service"] == "payflow") { ?> connected<? } ?>" href="payflow/">
			<span class="payflow"></span>
			<p>PayPal Payflow Gateway</p>
		</a>
		<a class="box_select<? if ($gateway["service"] == "linkpoint") { ?> connected<? } ?>" href="linkpoint/">
			<span class="linkpoint"></span>
			<p>First Data / LinkPoint</p>
		</a>
	</section>
</div>