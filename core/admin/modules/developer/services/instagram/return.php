<?
	
	//http://dev.fastspot.com/clients/concordia/admin/developer/services/instagram/return/
	
/*
	Client ID 	ae5092387fb94365a87e186790fdb360
	Client Secret 	218f5f6983be422790b7711ccd54ab9e
	Website URL 	http://dev.fastspot.com/clients/concordia/
	Redirect URI 	http://dev.fastspot.com/clients/concordia/admin/developer/services/instagram/return/
*/
		
	$admin->requireLevel(1);
	
	$connection = new Instagram(array(
		"apiKey" => $settings["id"],
		"apiSecret" => $settings["secret"],
		"apiCallback" => $mroot . "return/"
	));
	
	$code = $_GET['code'];
	
	if (true === isset($code)) {
		$access_token = $connection->getOAuthToken($code);
		
		$settings["token"] = $access_token->access_token;
		$settings["user_id"] = $access_token->user->id;
		$settings["user_name"] = $access_token->user->username;
		
		$admin->updateSettingValue("bigtree-internal-instagram-api", $settings);
		
		$admin->growl("Instagram API","Token Updated");
		BigTree::redirect($mroot);
	} else {
		BigTree::redirect($mroot);
	}

?>