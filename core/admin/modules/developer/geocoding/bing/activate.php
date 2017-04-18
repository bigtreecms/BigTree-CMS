<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$setting = new Setting("bigtree-internal-geocoding-service");
	$setting->Value["service"] = "bing";
	$setting->Value["bing_key"] = $_POST["bing_key"];
	$setting->save();

	Utils::growl("Developer","Geocoding Service set to Bing");
	Router::redirect(DEVELOPER_ROOT);
	