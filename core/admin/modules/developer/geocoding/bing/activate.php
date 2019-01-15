<?php
	$admin->verifyCSRFToken();
	
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "bing";
	$geocoding_service["bing_key"] = $_POST["bing_key"];
	
	$admin->updateInternalSettingValue("bigtree-internal-geocoding-service", $geocoding_service, true);
	$admin->growl("Developer","Geocoding Service set to Bing");
	
	BigTree::redirect(DEVELOPER_ROOT);
