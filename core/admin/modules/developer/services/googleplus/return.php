<?
	$googleplus = new BigTreeGooglePlusAPI;
	$response = json_decode(BigTree::cURL("https://accounts.google.com/o/oauth2/token",array(
		"code" => $_GET["code"],
		"client_id" => $googleplus->Settings["key"],
		"client_secret" => $googleplus->Settings["secret"],
		"redirect_uri" => ADMIN_ROOT."developer/services/googleplus/return/",
		"grant_type" => "authorization_code"
	)));
	if (isset($response->error)) {
		$admin->growl("Google+ API",$response->error,"error");
	} else {
		$setting = array(
			"key" => $googleplus->Settings["key"],
			"secret" => $googleplus->Settings["secret"],
			"token" => $response->access_token,
			"expires" => strtotime("+".$response->expires_in." seconds"),
			"refresh_token" => $response->refresh_token
		);

		$googleplus->Settings["token"] = $response->access_token;
		$googleplus->Connected = true;
		$info = $googleplus->getPerson();
		if (isset($info->ID)) {
			$setting["user_id"] = $info->ID;
			$setting["user_name"] = $info->DisplayName;
			$setting["user_image"] = $info->Image;
			$admin->updateSettingValue("bigtree-internal-googleplus-api",$setting);
			$admin->growl("Google+ API","Connected");
		} else {
			$admin->growl("Google+ API","Unknown Error","error");
		}
	}
	BigTree::redirect(DEVELOPER_ROOT."services/googleplus/");
?>