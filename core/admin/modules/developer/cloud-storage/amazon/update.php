<?php
	$admin->verifyCSRFToken();
	
	$cloud->Settings["amazon"] = array(
		"key" => trim($_POST["key"]),
		"secret" => trim($_POST["secret"]),
		"region" => $_POST["region"]
	);

	// Try to list buckets
	$cloud->Service = "amazon";
	$cloud->setupAmazon();
	$cloud->listContainers();

	if (count($cloud->Errors)) {
		$admin->growl("Developer","Amazon S3 secret/key are invalid.","error");
		BigTree::redirect(DEVELOPER_ROOT."cloud-storage/amazon/");
	}

	$cloud->Settings["amazon"]["active"] = true;
	$admin->growl("Developer","Enabled Amazon S3");

	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
