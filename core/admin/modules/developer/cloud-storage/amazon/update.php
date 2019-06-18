<?php
	namespace BigTree;
	
	/**
	 * @global CloudStorage\Amazon $amazon
	 */
	
	CSRF::verify();

	$amazon->Key = trim($_POST["key"]);
	$amazon->Region = $_POST["region"];
	$amazon->Secret = trim($_POST["secret"]);

	// Try to list buckets
	$amazon->setup();
	$amazon->listContainers();
	
	if (count($amazon->Errors)) {
		Admin::growl("Developer","Amazon S3 secret/key are invalid.","error");
		Router::redirect(DEVELOPER_ROOT."cloud-storage/amazon/");
	}

	$amazon->Active = true;

	Setting::updateValue($amazon->SettingID, $amazon->Settings, true);
	Admin::growl("Developer","Enabled Amazon S3");
	Router::redirect(DEVELOPER_ROOT."cloud-storage/");
	