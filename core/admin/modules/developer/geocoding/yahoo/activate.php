<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "yahoo";
	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to Yahoo");
	BigTree::redirect(DEVELOPER_ROOT);
?>