<?php
	namespace BigTree;

	$amazon->Key = trim($_POST["key"]);
	$amazon->Secret = trim($_POST["secret"]);

	// Try to list buckets
	$amazon->listContainers();
	
	if (count($amazon->Errors)) {
		$admin->growl("Developer","Amazon S3 secret/key are invalid.","error");
		Router::redirect(DEVELOPER_ROOT."cloud-storage/amazon/");
	}

	$amazon->Active = true;
	$amazon->Setting->save();

	$admin->growl("Developer","Enabled Amazon S3");
	
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");