<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$current = Setting::value("bigtree-internal-geocoding-service");
	$current["service"] = "mapquest";
	$current["mapquest_key"] = $_POST["mapquest_key"];
	
	Setting::updateValue("bigtree-internal-geocoding-service", $current);
	Utils::growl("Developer","Geocoding Service set to MapQuest");
	Router::redirect(DEVELOPER_ROOT);
	