<?php
	$admin->verifyCSRFToken();

	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "google";
	$geocoding_service["google_key"] = $_POST["google_key"];

	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to Google");
	BigTree::redirect(DEVELOPER_ROOT);
