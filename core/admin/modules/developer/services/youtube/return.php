<?
	$youtube = new BigTreeYouTubeAPI;
	$response = json_decode(BigTree::cURL("https://accounts.google.com/o/oauth2/token",array(
		"code" => $_GET["code"],
		"client_id" => $youtube->Settings["key"],
		"client_secret" => $youtube->Settings["secret"],
		"redirect_uri" => ADMIN_ROOT."developer/services/youtube/return/",
		"grant_type" => "authorization_code"
	)));
	if (isset($response->error)) {
		$admin->growl("YouTube API",$response->error,"error");
	} else {
		$setting = array(
			"key" => $youtube->Settings["key"],
			"secret" => $youtube->Settings["secret"],
			"token" => $response->access_token,
			"expires" => strtotime("+".$response->expires_in." seconds"),
			"refresh_token" => $response->refresh_token
		);

		$info = json_decode(BigTree::cURL($youtube->URL."channels?access_token=".$response->access_token."&part=snippet&mine=true"));
		if (isset($info->items)) {
			$setting["user_id"] = $info->items[0]->id;
			$setting["user_name"] = $info->items[0]->snippet->title;
			$setting["user_image"] = $info->items[0]->snippet->thumbnails->default->url;
			$admin->updateSettingValue("bigtree-internal-youtube-api",$setting);
			$admin->growl("YouTube API","Connected");
		} else {
			$admin->growl("YouTube API","Unknown Error","error");
		}
	}
	BigTree::redirect(DEVELOPER_ROOT."services/youtube/");
?>