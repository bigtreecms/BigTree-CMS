<?
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
	
	if ($_POST["access_key_id"] && $_POST["secret_access_key"]) {
		$ups["service"] = "s3";
		$ups["s3"]["keys"] = array("access_key_id" => $_POST["access_key_id"], "secret_access_key" => $_POST["secret_access_key"]);
	} else {
		$ups["service"] = "";
	}

	$admin->updateSettingValue("bigtree-internal-upload-service",$ups);	

	$admin->growl("Developer","Updated Amazon S3 Keys");
	BigTree::redirect($developer_root);
?>