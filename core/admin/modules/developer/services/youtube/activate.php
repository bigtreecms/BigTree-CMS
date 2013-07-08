<?
	// Save our client key/secret
	$admin->updateSettingValue("bigtree-internal-youtube-api",array("key" => $_POST["key"],"secret" => $_POST["secret"]));
	// OAuth redirect
	BigTree::redirect("https://accounts.google.com/o/oauth2/auth".
		"?client_id=".urlencode($_POST["key"]).
		"&redirect_uri=".urlencode(ADMIN_ROOT."developer/services/youtube/return/").
		"&response_type=code".
		"&scope=".urlencode("https://www.googleapis.com/auth/youtube").
		"&approval_prompt=force".
		"&access_type=offline");
?>