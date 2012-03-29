<?
	$admin->requireLevel(1);
	
	$ga = new BigTreeGoogleAnalytics($_POST["email"],$_POST["password"]);
	if (!$ga->AuthToken) {
		header("Location: ".$mroot."setup/error/");
		die();
	}
	
	$admin->deleteSetting("bigtree-internal-google-analytics-email");
	$setting = array(
		"id" => "bigtree-internal-google-analytics-email",
		"title" => "Google Analytics Email Address",
		"type" => "text",
		"encrypted" => "on",
		"system" => "on"
	);
	$admin->createSetting($setting);
	$admin->updateSettingValue("bigtree-internal-google-analytics-email",$_POST["email"]);
	
	$admin->deleteSetting("bigtree-internal-google-analytics-password");
	$setting = array(
		"id" => "bigtree-internal-google-analytics-password",
		"title" => "Google Analytics Password",
		"type" => "text",
		"encrypted" => "on",
		"system" => "on"
	);
	$admin->createSetting($setting);
	$admin->updateSettingValue("bigtree-internal-google-analytics-password",$_POST["password"]);
	
	$admin->growl("Analytics","Account Authenticated");
	header("Location: ".$mroot."choose-profile/");	
	die();
?>
