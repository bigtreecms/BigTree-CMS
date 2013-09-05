<?
	$ups = $cms->getSetting("bigtree-internal-storage");

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
		$ups["rackspace"]["keys"] = array("api_key" => $_POST["api_key"], "username" => $_POST["username"]);
	} else {
		$ups["service"] = "";
	}

	$admin->updateSettingValue("bigtree-internal-storage",$ups);
	
	$admin->growl("Developer","Updated Rackspace Keys");
	BigTree::redirect($developer_root);
?>