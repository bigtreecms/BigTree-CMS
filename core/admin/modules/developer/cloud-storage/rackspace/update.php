<?php
	namespace BigTree;

	$rackspace->Username = trim($_POST["username"]);
	$rackspace->Key = trim($_POST["api_key"]);
	$rackspace->Region = trim($_POST["region"]);

	if (!$rackspace->getToken()) {
		$admin->growl("Developer","Rackspace Cloud Files Login Failed","error");
		
		Router::redirect(DEVELOPER_ROOT."cloud-storage/rackspace/");
	}

	$rackspace->Active = true;
	$rackspace->Setting->save();

	$admin->growl("Developer","Enabled Rackspace Cloud Files");

	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	