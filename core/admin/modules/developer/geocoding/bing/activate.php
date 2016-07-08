<?php
	namespace BigTree;
	
	$geocoding_setting = new Setting("bigtree-internal-geocoding-service");
	$geocoding_setting->Value["service"] = "bing";
	$geocoding_setting->Value["bing_key"] = $_POST["bing_key"];
	$geocoding_setting->save();

	Utils::growl("Developer","Geocoding Service set to Bing");
	Router::redirect(DEVELOPER_ROOT);
	