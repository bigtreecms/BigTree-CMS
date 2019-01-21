<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$setting = new Setting("bigtree-internal-geocoding-service");
	$setting->Value["service"] = "google";
	$setting->Value["google_key"] = $_POST["google_key"];
	$setting->save();

	Utils::growl("Developer","Geocoding Service set to Google");
	Router::redirect(DEVELOPER_ROOT);
	