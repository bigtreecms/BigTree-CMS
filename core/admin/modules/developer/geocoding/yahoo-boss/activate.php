<?
	$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
	$geocoding_service["service"] = "yahoo-boss";
	$geocoding_service["yahoo_boss_consumer_secret"] = $_POST["yahoo_boss_consumer_secret"];
	$geocoding_service["yahoo_boss_consumer_key"] = $_POST["yahoo_boss_consumer_key"];
	$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);
	$admin->growl("Developer","Geocoding Service set to Yahoo BOSS");

	// Send them off to OAuth.
	$geocoder = new BigTreeGeocoding;
	$geocoder->OAuthClient->Process();
	die();
?>