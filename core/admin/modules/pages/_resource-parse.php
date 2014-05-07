<?
	// Parse the resources
	$bigtree["entry"] = array();
	$bigtree["template"] = $cms->getTemplate($_POST["template"]);
	$bigtree["file_data"] = BigTree::parsedFilesArray("resources");
	$bigtree["post_data"] = $_POST["resources"];
	// Duplicate vars and $upload_service in for backwards compat.
	$data = $_POST["resources"];
	$file_data = $_FILES["resources"];
	$upload_service = new BigTreeUploadService;

	foreach ((array)$bigtree["template"]["resources"] as $resource) {
		unset($value); // Backwards compat.
		$field = array();
		$field["key"] = $key = $resource["id"];
		$field["options"] = $options = $resource;
		if (empty($field["options"]["directory"])) {
			$field["options"]["directory"] = $options["directory"] = "files/pages/";
		}
		$field["ignore"] = false;
		$field["input"] = $bigtree["post_data"][$resource["id"]];
		$field["file_input"] = $bigtree["file_data"][$resource["id"]];

		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		$field_type_path = BigTree::path("admin/form-field-types/process/".$resource["type"].".php");
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
		
		if (!BigTreeAutoModule::validate($field["output"],$field["options"]["validation"])) {
			$error = $field["options"]["error_message"] ? $field["options"]["error_message"] : BigTreeAutoModule::validationErrorMessage($field["output"],$field["options"]["validation"]);
			$bigtree["errors"][] = array(
				"field" => $field["options"]["title"],
				"error" => $error
			);
		}

		if (!$field["ignore"]) {
			// Translate internal link information to relative links.
			if (is_array($field["output"])) {
				$field["output"] = BigTree::translateArray($field["output"]);
			} else {
				$field["output"] = $admin->autoIPL($field["output"]);
			}
			$bigtree["entry"][$field["key"]] = $field["output"];
		}
	}

	// We save it back to the post array because we're just going to feed the whole post array to createPage / updatePage
	$_POST["resources"] = $bigtree["entry"];
?>