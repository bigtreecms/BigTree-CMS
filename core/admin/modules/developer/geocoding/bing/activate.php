<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "bing";
	$geocoding_service["bing_key"] = $_POST["bing_key"];
	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to Bing");
	BigTree::redirect(DEVELOPER_ROOT);
?>