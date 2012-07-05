<?
	$keys = array("api_key" => $_POST["api_key"], "username" => $_POST["username"]);
	// If we've never used S3 before, setup our settings for it.
	if (!$admin->settingExists("bigtree-internal-rackspace-keys")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-rackspace-keys",
			"system" => "on",
			"encrypted" => "on"
		));
	}
	if (!$admin->settingExists("bigtree-internal-rackspace-containers")) {
		$admin->createSetting(array(
			"id" => "bigtree-internal-rackspace-containers",
			"system" => "on"
		));
	}
	
	$admin->updateSettingValue("bigtree-internal-rackspace-keys",$keys);
	
	$ups = $cms->getSetting("bigtree-internal-upload-service");
	
	
	// Check if we have optipng installed.
	if (file_exists("/usr/bin/optipng")) {
		$ups["optipng"] = "/usr/bin/optipng";
	} elseif (file_exists("/usr/local/bin/optipng")) {
		$ups["optipng"] = "/usr/local/bin/optipng";
	}

	// Check if we have jpegtran installed.
	if (file_exists("/usr/bin/jpegtran")) {
		$ups["jpegtran"] = "/usr/bin/jpegtran";
	} elseif (file_exists("/usr/local/bin/jpegtran")) {
		$ups["jpegtran"] = "/usr/local/bin/jpegtran";
	}
	
	if ($_POST["api_key"] && $_POST["username"]) {
		$ups["service"] = "rackspace";
	} else {
		$ups["service"] = "";
	}

	$admin->updateSettingValue("bigtree-internal-upload-service",$ups);
	
	$admin->growl("Developer","Updated Rackspace Keys");
	BigTree::redirect($developer_root);
?>