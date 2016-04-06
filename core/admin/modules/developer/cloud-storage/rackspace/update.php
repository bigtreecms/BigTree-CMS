<?php
	namespace BigTree;

	$cloud->Settings["rackspace"] = array(
		"username" => trim($_POST["username"]),
		"api_key" => trim($_POST["api_key"]),
		"region" => trim($_POST["region"])
	);

	if (!$cloud->getToken()) {
		$admin->growl("Developer","Rackspace Cloud Files Login Failed","error");
		Router::redirect(DEVELOPER_ROOT."cloud-storage/rackspace/");
	}

	$cloud->Settings["rackspace"]["active"] = true;
	$admin->growl("Developer","Enabled Rackspace Cloud Files");

	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	