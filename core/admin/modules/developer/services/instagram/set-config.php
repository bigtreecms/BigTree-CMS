<?
	
	$admin->requireLevel(1);
	
	if (!$settings) {
		$admin->createSetting(array("id" => "bigtree-internal-instagram-api", "name" => "Instagram API", "description" => "", "type" => "", "locked" => "on", "module" => "", "encrypted" => "", "system" => "on"));
	}
	$settings = array(
		"id" => $_POST["id"],
		"secret" => $_POST["secret"]
	);
	
	$admin->updateSettingValue("bigtree-internal-instagram-api", $settings);
	
	$admin->growl("Instagram API","Client Values Updated");
	BigTree::redirect($mroot . "connect/");
	
?>