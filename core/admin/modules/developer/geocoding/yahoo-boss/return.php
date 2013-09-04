<?
	$api = new BigTreeYahooBOSSAPI;
	$token = $api->oAuthSetToken($_GET["code"]);
	if ($api->OAuthError) {
		$admin->growl("Yahoo BOSS API",$api->OAuthError,"error");
	} else {
		$geocoding_service = $cms->getSetting("bigtree-internal-geocoding-service");
		$geocoding_service["service"] = "yahoo-boss";
		$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoding_service);

		$admin->growl("Yahoo BOSS API","Connected");
	}
	BigTree::redirect(DEVELOPER_ROOT."geocoding/yahoo-boss/");
?>