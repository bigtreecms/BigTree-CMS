<?
	$item = $admin->getSetting($_POST["id"]);
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings","Access Denied");
	} else {
		$type = $item["type"];
		$key = $item["id"];
		$data = $_POST;
		$tpath = BigTree::path("admin/form-field-types/process/$type.php");
		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		if (file_exists($tpath)) {
			include $tpath;
		} else {
			$value = htmlspecialchars($data[$key]);
		}
	
		$admin->updateSettingValue($_POST["id"],$value);
		
		$admin->growl("Settings","Updated Setting");
	}
	header("Location: ".$admin_root."settings/");
	die();
?>