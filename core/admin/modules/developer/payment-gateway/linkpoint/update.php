<?php
	namespace BigTree;
	
	$gateway->Service = "linkpoint";
	$gateway->Settings["linkpoint-store"] = $_POST["linkpoint-store"];
	$gateway->Settings["linkpoint-environment"] = $_POST["linkpoint-environment"];
	if ($_FILES["linkpoint-certificate"]["tmp_name"]) {
		$filename = FileSystem::getAvailableFileName(SERVER_ROOT."custom/certificates/",$_FILES["linkpoint-certificate"]["name"]);
		FileSystem::moveFile($_FILES["linkpoint-certificate"]["tmp_name"],SERVER_ROOT."custom/certificates/".$filename);
		$gateway->Settings["linkpoint-certificate"] = $filename;
	}
	$gateway->saveSettings();
	
	$admin->growl("Developer","Updated Payment Gateway");
	Router::redirect(DEVELOPER_ROOT);
	