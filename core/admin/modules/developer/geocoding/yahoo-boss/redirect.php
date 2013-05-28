<?
	$geocoder = new BigTreeGeocoding;

	if ($geocoder->OAuthClient->Process()) {
		$geocoder->Settings["yahoo_boss_token"] = $geocoder->OAuthClient->access_token;
		$geocoder->Settings["yahoo_boss_token_secret"] = $geocoder->OAuthClient->access_token_secret;
		$admin->updateSettingValue("bigtree-internal-geocoding-service",$geocoder->Settings);
		BigTree::redirect(DEVELOPER_ROOT);
	} else {
		$admin->growl("Developer","Yahoo BOSS OAuth Failed","error");
		BigTree::redirect(DEVELOPER_ROOT."geocoding/yahoo-boss/");
	}
?>