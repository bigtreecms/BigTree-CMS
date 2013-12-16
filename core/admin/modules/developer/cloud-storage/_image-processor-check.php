<?
	// Check if we have optipng installed.
	if (file_exists("/usr/bin/optipng")) {
		$storage_settings["optipng"] = "/usr/bin/optipng";
	} elseif (file_exists("/usr/local/bin/optipng")) {
		$storage_settings["optipng"] = "/usr/local/bin/optipng";
	}

	// Check if we have jpegtran installed.
	if (file_exists("/usr/bin/jpegtran")) {
		$storage_settings["jpegtran"] = "/usr/bin/jpegtran";
	} elseif (file_exists("/usr/local/bin/jpegtran")) {
		$storage_settings["jpegtran"] = "/usr/local/bin/jpegtran";
	}
?>