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
		// Init as $upload_service for backwards compat.
		$upload_service = new BigTreeUploadService;

		// Some backwards compatibility vars thrown in.
		$bigtree["crops"] = array();
		$bigtree["errors"] = array();
		$bigtree["post_data"] = $data = $_POST;
		$bigtree["file_data"] = BigTree::parsedFilesArray();
		$file_data = $_FILES;
		$key = $_POST["id"];

		// Pretend like we're a normal field
		unset($value); // Backwards compat.
		$field = array();
		$field["key"] = $key;
		$field["options"] = $options = json_decode($item["options"],true);
		$field["ignore"] = false;
		$field["input"] = $bigtree["post_data"][$key];
		$field["file_input"] = $bigtree["file_data"][$key];

		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		$field_type_path = BigTree::path("admin/form-field-types/process/".$item["type"].".php");
		if (file_exists($field_type_path)) {
			include $field_type_path;
		} else {
			if (is_array($bigtree["post_data"][$field["key"]])) {
				$field["output"] = $bigtree["post_data"][$field["key"]];
			} else {
				$field["output"] = htmlspecialchars(htmlspecialchars_decode($bigtree["post_data"][$field["key"]]));
			}
		}

		// Backwards compatibility with older custom field types
		if (!isset($field["output"]) && isset($value)) {
			$field["output"] = $value;
		}
		
		if (!$field["ignore"]) {
			$admin->updateSettingValue($_POST["id"],$field["output"]);		
		}

		$admin->growl("Settings","Updated Setting");
	}

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"errors" => $bigtree["errors"],
		"crops" => $bigtree["crops"]
	);

	if (count($fails)) {
		BigTree::redirect(ADMIN_ROOT."settings/error/");
	} elseif (count($crops)) {
		BigTree::redirect(ADMIN_ROOT."settings/crop/");
	}

	BigTree::redirect(ADMIN_ROOT."settings/");
?>