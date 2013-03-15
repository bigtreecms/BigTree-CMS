<?
	
	$admin->requireLevel(1);
	
	if (!$settings) {
		$admin->createSetting(array("id" => "bigtree-internal-twitter-api", "name" => "Twitter API", "description" => "", "type" => "", "locked" => "on", "module" => "", "encrypted" => "", "system" => "on"));
	}
	$settings = array(
		"key" => $_POST["key"],
		"secret" => $_POST["secret"]
	);
	
	$admin->updateSettingValue("bigtree-internal-twitter-api", $settings);
	
	$admin->growl("Twitter API","Consumer Values Updated");
	BigTree::redirect($mroot . "connect/");
	
?>