<?
	$admin->requireLevel(1);
	
	$ga = new BigTreeGoogleAnalytics($_POST["email"],$_POST["password"]);
	if (!$ga->AuthToken) {
		header("Location: ".$mroot."setup/error/");
		die();
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
	header("Location: ".$mroot."choose-profile/");	
	die();
?>
