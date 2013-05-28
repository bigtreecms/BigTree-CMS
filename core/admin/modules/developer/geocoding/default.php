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

	if ($gateway["service"] == "google") {
		$currently = "Google";
	} elseif ($gateway["service"] == "bing") {
		$currently = "Bing";
	} elseif ($gateway["service"] == "yahoo") {
		$currently = "Yahoo";
	} elseif ($gateway["service"] == "yahoo-boss") {
		$currently = "Yahoo BOSS";
	} elseif ($gateway["service"] == "mapquest") {
		$currently = "MapQuest";
	} else {
		$currently = "Google";
	}
?>
<div class="table">
	<summary><h2>Currently Using<small><?=$currently?></small></h2></summary>
	<section>
		<p>Choose a service below to configure your geocoder settings.</p>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/yahoo/">
			<span class="yahoo"></span>
			<p>Yahoo</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
		<a class="box_select" href="<?=DEVELOPER_ROOT?>geocoding/yahoo-boss/">
			<span class="yahooboss"></span>
			<p>Yahoo BOSS</p>
		</a>
	</section>
</div>