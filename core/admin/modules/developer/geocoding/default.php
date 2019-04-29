<?php
	namespace BigTree;
	
	$setting = Setting::value("bigtree-internal-geocoding-service");
	
	if (empty($setting["service"])) {
		$setting = ["service" => ""];
	}
?>
<div class="container">
	<div class="container_summary"><h2><?=Text::translate("Configure")?></h2></div>
	<section>
		<a class="box_select<?php if ($setting["service"] == "google" || !$setting["service"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select<?php if ($setting["service"] == "bing") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select<?php if ($setting["service"] == "mapquest") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
	</section>
</div>