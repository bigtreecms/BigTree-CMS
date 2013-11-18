<?
	$gateway->Service = "linkpoint";
	$gateway->Settings["linkpoint-store"] = $_POST["linkpoint-store"];
	$gateway->Settings["linkpoint-environment"] = $_POST["linkpoint-environment"];
	if ($_FILES["linkpoint-certificate"]["tmp_name"]) {
		$filename = BigTree::getAvailableFileName(SERVER_ROOT."custom/certificates/",$_FILES["linkpoint-certificate"]["name"]);
		BigTree::moveFile($_FILES["linkpoint-certificate"]["tmp_name"],SERVER_ROOT."custom/certificates/".$filename);
		$gateway->Settings["linkpoint-certificate"] = $filename;
	}
	$gateway->saveSettings();
	
	$admin->growl("Developer","Updated Payment Gateway");
	BigTree::redirect(DEVELOPER_ROOT);
?>