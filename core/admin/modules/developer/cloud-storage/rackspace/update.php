<?
	$cloud->Settings["rackspace"] = array(
		"username" => $_POST["username"],
		"api_key" => $_POST["api_key"],
		"region" => $_POST["region"]
	);
	if (!$cloud->_getRackspaceToken()) {
		$admin->growl("Developer","Rackspace Cloud Files Login Failed","error");
		BigTree::redirect(DEVELOPER_ROOT."cloud-storage/rackspace/");
	}

	$cloud->Settings["rackspace"]["active"] = true;
	$admin->growl("Developer","Enabled Rackspace Cloud Files");
	BigTree::redirect(DEVELOPER_ROOT."cloud-storage/");
?>