<?php
	namespace BigTree;
	
	$setting = new Setting("bigtree-internal-geocoding-service");
	
	if (empty($setting->Value)) {
		$setting->Encrypted = true;
		$setting->System = true;
		$setting->Value = array(
			"service" => "google", 
			"settings" => array()
		);
		
		$setting->save();
	}
?>
<div class="container">
	<summary><h2><?=Text::translate("Configure")?></h2></summary>
	<section>
		<a class="box_select<?php if ($setting->Value["service"] == "google" || !$setting->Value["service"]) { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/google/">
			<span class="google"></span>
			<p>Google</p>
		</a>
		<a class="box_select<?php if ($setting->Value["service"] == "bing") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/bing/">
			<span class="bing"></span>
			<p>Bing</p>
		</a>
		<a class="box_select<?php if ($setting->Value["service"] == "mapquest") { ?> connected<?php } ?>" href="<?=DEVELOPER_ROOT?>geocoding/mapquest/">
			<span class="mapquest"></span>
			<p>MapQuest</p>
		</a>
	</section>
</div>