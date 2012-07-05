<?
	$ups = array();
	
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
	
	$ups["service"] = "local";
	
	$admin->updateSettingValue("bigtree-internal-upload-service",$ups);	
	
	$admin->growl("Developer","Updated Upload Service");
	BigTree::redirect($developer_root);
?>