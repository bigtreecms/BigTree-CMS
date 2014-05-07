<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	$admin->requireLevel(1);
	$item = $admin->getSetting($_POST["id"]);
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings","Access Denied","error");
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
				$field["output"] = BigTree::safeEncode($bigtree["post_data"][$field["key"]]);
			}
		}

		// Backwards compatibility with older custom field types
		if (!isset($field["output"]) && isset($value)) {
			$field["output"] = $value;
		}

		// Translate internal link information to relative links.
		if (is_array($field["output"])) {
			$field["output"] = BigTree::translateArray($field["output"]);
		} else {
			$field["output"] = $admin->autoIPL($field["output"]);
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

	// Track resource allocation
	$admin->allocateResources("settings",$_POST["id"]);

	if (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."settings/error/");
	} elseif (count($bigtree["crops"])) {
		BigTree::redirect(ADMIN_ROOT."settings/crop/");
	}

	BigTree::redirect(ADMIN_ROOT."settings/");
?>