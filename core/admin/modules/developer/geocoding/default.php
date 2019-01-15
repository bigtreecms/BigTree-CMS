<?php
	$gateway = $cms->getSetting("bigtree-internal-geocoding-service");

	if (empty($gateway["service"])) {
		$gateway = ["service" => ""];
	}
?>
<div class="table">
	<summary><h2>Configure</h2></summary>
	<section>
		<a class="box_select<?php if ($gateway["service"] == "google" || !$gateway["service"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select<?php if ($gateway["service"] == "bing") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select<?php if ($gateway["service"] == "mapquest") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
	</section>
</div>