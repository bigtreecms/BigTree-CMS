<?
	// Parse the resources
	$bigtree["resources"] = array();
	$bigtree["template"] = $cms->getTemplate($_POST["template"]);
	// These are left in for backwards compatibility.
	$data = $_POST["resources"];
	$file_data = $_FILES["resources"];
	$upload_service = new BigTreeStorage;

	foreach ($bigtree["template"]["resources"] as $resource) {
		unset($value); // Backwards compat.
		$field = array();
		$field["key"] = $key = $resource["id"];
		$field["options"] = $options = $resource;
		$field["options"]["directory"] = $options["directory"] = "files/pages/";
		$field["ignore"] = false;
		$field["input"] = $_POST["resources"][$resource["id"]];
		// Make sense of file input data
		if (is_array($_FILES["resources"]["name"][$resource["id"]])) {
			// If we have an array of files for the key, we loop through them and set "file_inputs" (multiple)
			$field["file_inputs"] = array();
			foreach ($_FILES["resources"]["name"][$resource["id"]] as $key => $val) {
				$field["file_inputs"][] = array(
					"name" => $_FILES["resources"]["name"][$resource["id"]][$key],
					"type" => $_FILES["resources"]["type"][$resource["id"]][$key],
					"tmp_name" => $_FILES["resources"]["tmp_name"][$resource["id"]][$key],
					"error" => $_FILES["resources"]["error"][$resource["id"]][$key],
					"size" => $_FILES["resources"]["size"][$resource["id"]][$key]
				);
			}
		} else {
			// If we have a single file for the key, we simply set its information in "file_input" (singular)
			$field["file_input"] = array(
				"name" => $_FILES["resources"]["name"][$resource["id"]],
				"type" => $_FILES["resources"]["type"][$resource["id"]],
				"tmp_name" => $_FILES["resources"]["tmp_name"][$resource["id"]],
				"error" => $_FILES["resources"]["error"][$resource["id"]],
				"size" => $_FILES["resources"]["size"][$resource["id"]]
			);
		}

		$field_type_path = BigTree::path("admin/form-field-types/process/".$resource["type"].".php");
		
		// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
		if (file_exists($field_type_path)) {
			include $field_type_path;
		} else {
			if (is_array($_POST["resources"][$field["key"]])) {
				$field["output"] = $_POST["resources"][$field["key"]];
			} else {
				$field["output"] = htmlspecialchars(htmlspecialchars_decode($_POST["resources"][$field["key"]]));
			}
		}

		// Backwards compatibility with older custom field types
		if (!isset($field["output"]) && isset($value)) {
			$field["output"] = $value;
		}
		
		if (!BigTreeForms::validate($field["output"],$field["options"]["validation"])) {
			$error = $field["options"]["error_message"] ? $field["options"]["error_message"] : BigTreeForms::errorMessage($field["output"],$field["options"]["validation"]);
			$bigtree["errors"][] = array(
				"field" => $field["options"]["title"],
				"message" => $error
			);
		}

		if (!$field["ignore"]) {
			// Translate internal link information to relative links.
			if (is_array($field["output"])) {
				$field["output"] = BigTree::translateArray($field["output"]);
			} else {
				$field["output"] = $admin->autoIPL($field["output"]);
			}
			$bigtree["resources"][$field["key"]] = $field["output"];
		}
	}

	// We save it back to the post array because we're just going to feed the whole post array to createPage / updatePage
	$_POST["resources"] = $bigtree["resources"];
?>