<?php
	namespace BigTree;
	
	CSRF::verify();

	$current = Setting::value("bigtree-internal-geocoding-service");
	$current["service"] = "bing";
	$current["bing_key"] = $_POST["bing_key"];
	
	Setting::updateValue("bigtree-internal-geocoding-service", $current);
	Admin::growl("Developer","Geocoding Service set to Bing");
	Router::redirect(DEVELOPER_ROOT);
	