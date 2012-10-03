<?
	$admin->requireLevel(1);
	
	// Check this code with Google.
	$token = $client->authenticate();
	
	$settings = $cms->getSetting("bigtree-internal-google-analytics");
	$settings["token"] = $token;
	$admin->updateSettingValue("bigtree-internal-google-analytics",$settings);
		
	$admin->growl("Analytics","Successfully Authenticated");
	BigTree::redirect($mroot);	
?>