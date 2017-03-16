<?
	$admin->verifyCSRFToken();
	
	// Get existing presets
	$settings = $cms->getSetting("bigtree-internal-media-settings");

	// Delete one of them
	unset($settings["presets"][$_POST["id"]]);
	$admin->updateSettingValue("bigtree-internal-media-settings",$settings);
?>