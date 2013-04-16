<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	$admin->requireLevel(1);
	$item = $admin->getSetting($_POST["id"]);
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings","Access Denied");
	} else {
		$upload_service = new BigTreeUploadService;
		// Pretend like we're a normal field
		$type = $item["type"];
		$key = $item["id"];
		$file_data = $_FILES;
		$data = $_POST;
		$options = json_decode($item["options"],true);
		$options["title"] = $item["name"];
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

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"fails" => $fails,
		"crops" => $crops
	);

	if (count($fails)) {
		BigTree::redirect(ADMIN_ROOT."settings/error/");
	} elseif (count($crops)) {
		BigTree::redirect(ADMIN_ROOT."settings/crop/");
	}

	BigTree::redirect(ADMIN_ROOT."settings/");
?>