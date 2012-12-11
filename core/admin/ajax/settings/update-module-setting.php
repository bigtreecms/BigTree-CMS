<?
	header("Content-type: text/javascript");
	$data = $_POST;
	$key = $_POST["setting-id"];
	$value = $_POST[$_POST["setting-id"]];
	$setting = $admin->getSetting($key);
	if ($setting["locked"]) {
		$admin->requireLevel(2);
	} else {
		$admin->requireLevel(1);
	}
	
	$tpath = BigTree::path("admin/form-field-types/process/$type.php");
	// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
	if (file_exists($tpath)) {
		include $tpath;
	} else {
		$value = htmlspecialchars($data[$key]);
	}
	$admin->updateSettingValue($key,$value);
?>
BigTree.Growl("Settings","Setting Updated");