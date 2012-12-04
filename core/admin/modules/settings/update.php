<?
	$admin->requireLevel(1);
	$item = $admin->getSetting($_POST["id"]);
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings","Access Denied");
	} else {
		$type = $item["type"];
		$key = $item["id"];
		$data = $_POST;
		$options = json_decode($item["options"],true);
		$tpath = BigTree::path("admin/form-field-types/process/$type.php");
		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		if (file_exists($tpath)) {
			include $tpath;
		} else {
			$value = htmlspecialchars($data[$key]);
		}
		
		// Form Field Types are probably going to already encode their stuff so we need it back.
		if (is_array(json_decode($value,true))) {
			$value = json_decode($value,true);
		}
	
		$admin->updateSettingValue($_POST["id"],$value);
		
		$admin->growl("Settings","Updated Setting");
	}
	BigTree::redirect(ADMIN_ROOT."settings/");
?>