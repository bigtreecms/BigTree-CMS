<?
	if (!$admin->settingExists("bigtree-internal-geocoding-service")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-geocoding-service",
			"system" => "on",
			"encrypted" => "on"
		));
		$admin->updateSettingValue("bigtree-internal-geocoding-service",array("service" => "google", "settings" => array()));
	}
	$gateway = $cms->getSetting("bigtree-internal-geocoding-service");
	$gateway["service"] = isset($gateway["service"]) ? $gateway["service"] : "";
?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<? if ($gateway["service"] == "google" || !$gateway["service"]) { ?> connected<? } ?>" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select<? if ($gateway["service"] == "bing") { ?> connected<? } ?>" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select<? if ($gateway["service"] == "mapquest") { ?> connected<? } ?>" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
	</section>
</div>