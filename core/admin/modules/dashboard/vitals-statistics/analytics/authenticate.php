<?
	$admin->requireLevel(1);
	
	$ga = new BigTreeGoogleAnalytics($_POST["email"],$_POST["password"]);
	if (!$ga->AuthToken) {
		BigTree::redirect($mroot."setup/error/");
	}
	
	if (!$admin->settingExists("bigtree-internal-google-analytics")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-google-analytics",
			"system" => "on",
			"encrypted" => "on"
		));
	}
	
	$admin->updateSettingValue("bigtree-internal-google-analytics",array(
		"email" => $_POST["email"],
		"password" => $_POST["password"]
	));
	
	$admin->growl("Analytics","Account Authenticated");
	BigTree::redirect($mroot."choose-profile/");	
?>
