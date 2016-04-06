<?php
	namespace BigTree;

	$cloud->Settings["amazon"] = array(
		"key" => trim($_POST["key"]),
		"secret" => trim($_POST["secret"])
	);

	// Try to list buckets
	$cloud->Service = "amazon";
	$cloud->listContainers();
	if (count($cloud->Errors)) {
		$admin->growl("Developer","Amazon S3 secret/key are invalid.","error");
		Router::redirect(DEVELOPER_ROOT."cloud-storage/amazon/");
	}

	$cloud->Settings["amazon"]["active"] = true;
	$admin->growl("Developer","Enabled Amazon S3");
	
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");