<?php
	namespace BigTree;
	
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "google";

	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	Utils::growl("Developer","Geocoding Service set to Google");

	Router::redirect(DEVELOPER_ROOT);
	