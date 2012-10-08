<?
	$admin->requireLevel(1);
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$settings["profile"] = $_POST["profile"];
	$admin->updateSettingValue("bigtree-internal-google-analytics",$settings);
	
	$admin->growl("Analytics","Profile Set");
	BigTree::redirect($mroot."cache/");	
?>