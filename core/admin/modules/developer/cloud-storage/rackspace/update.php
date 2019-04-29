<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Rackspace $rackspace
	 */
	
	CSRF::verify();

	$rackspace->Username = trim($_POST["username"]);
	$rackspace->Key = trim($_POST["api_key"]);
	$rackspace->Region = trim($_POST["region"]);

	if (!$rackspace->getToken()) {
		Utils::growl("Developer","Rackspace Cloud Files Login Failed","error");
		
		Router::redirect(DEVELOPER_ROOT."cloud-storage/rackspace/");
	}

	$rackspace->Active = true;

	Setting::updateValue($rackspace->SettingID, $rackspace->Settings, true);
	Utils::growl("Developer","Enabled Rackspace Cloud Files");
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	