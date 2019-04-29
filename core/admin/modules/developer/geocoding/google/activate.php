<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$current = Setting::value("bigtree-internal-geocoding-service");
	$current["service"] = "google";
	$current["google_key"] = $_POST["google_key"];
	
	Setting::updateValue("bigtree-internal-geocoding-service", $current);
	Utils::growl("Developer","Geocoding Service set to Google");
	Router::redirect(DEVELOPER_ROOT);
	