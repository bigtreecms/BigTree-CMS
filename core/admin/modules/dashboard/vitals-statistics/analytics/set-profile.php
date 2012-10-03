<?
	$admin->requireLevel(1);
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$settings["profile"] = $_POST["profile"];
	$admin->updateSettingValue("bigtree-internal-google-analytics",$settings);
		
	$ga = new BigTreeGoogleAnalytics;
	if ($ga->AuthToken) {
		$ga->cacheInformation();
	}
	
	$admin->growl("Analytics","Profile Set");
	BigTree::redirect($mroot."cache/");	
?>