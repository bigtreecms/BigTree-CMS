<?
	$settings = $cms->getSetting("btx-dogwood-settings");
	if (!$settings) {
		$admin->createSetting(array(
			"id" => "btx-dogwood-settings",
			"name" => "Blog Settings",
			"system" => true
		));
	}
	
	$admin->updateSettingValue("btx-dogwood-settings",array(
		"title" => htmlspecialchars($_POST["title"]),
		"tagline" => htmlspecialchars($_POST["tagline"]),
		"disqus" => htmlspecialchars($_POST["disqus"])
	));
	
	$admin->growl("Blog","Updated Settings");
	BigTree::redirect("../");
?>