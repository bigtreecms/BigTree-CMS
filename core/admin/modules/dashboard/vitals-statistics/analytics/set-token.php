<?
	$admin->requireLevel(1);
	
	// Check this code with Google.
	try {
		$token = $analytics->Client->authenticate();
		$settings = $cms->getSetting("bigtree-internal-google-analytics");
		
		// If we don't have the setting yet, create it.
		if (!$settings) {
			$admin->createSetting(array("id" => "bigtree-internal-google-analytics", "name" => "Google Analytics Information", "description" => "", "type" => "", "locked" => "on", "module" => "", "encrypted" => "on", "system" => "on"));
			$settings = array("token" => $token);			
		} else {
			$settings["token"] = $token;
		}
		
		$admin->updateSettingValue("bigtree-internal-google-analytics",$settings);
			
		$admin->growl("Analytics","Successfully Authenticated");
		BigTree::redirect($mroot);
	// Failed to authenticate, so go back.
	} catch (Exception $e) {
		$admin->growl("Analytics","Authentication Failed","error");
		BigTree::redirect($mroot."configure/");	
	}
?>