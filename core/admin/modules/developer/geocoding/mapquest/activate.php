<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "mapquest";
	$geocoding_service["mapquest_key"] = $_POST["mapquest_key"];
	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to MapQuest");
	BigTree::redirect(DEVELOPER_ROOT);
?>