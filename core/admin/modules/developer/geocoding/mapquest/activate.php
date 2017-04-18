<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$setting = new Setting("bigtree-internal-geocoding-service");
	$setting->Value["service"] = "google";
	$setting->Value["mapquest_key"] = $_POST["mapquest_key"];
	$setting->save();
	
	Utils::growl("Developer","Geocoding Service set to MapQuest");
	Router::redirect(DEVELOPER_ROOT);
	