<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "google";
	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to Google");
	BigTree::redirect(DEVELOPER_ROOT);
?>