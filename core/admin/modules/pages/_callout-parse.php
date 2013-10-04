<?
	$bigtree["parsed_callouts"] = array();
	$bigtree["callout_file_data"] = BigTree::parsedFilesArray("callouts");
	// Some backwards compat stuff.
	$upload_service = new BigTreeUploadService;

	if (count($_POST["callouts"])) {
		foreach ($_POST["callouts"] as $number => $data) {
			// Make sure there's a callout here...
			if ($data["type"]) {
				// Backwards compatibility hacks...
				if (!is_array($file_data)) {
					$file_data = array();
				}
				if (is_array($_FILES["callouts"]["name"][$number])) {
					foreach ($_FILES["callouts"]["name"][$number] as $key => $val) {
						$file_data["name"][$key] = $val;
					}
				}
				if (is_array($_FILES["callouts"]["tmp_name"][$number])) {
					foreach ($_FILES["callouts"]["tmp_name"][$number] as $key => $val) {
						$file_data["tmp_name"][$key] = $val;
					}
				}
				
				// Setup the new callout for fun-ness.
				$bigtree["entry"] = array("type" => $data["type"],"display_title" => $data["display_title"]);
				$bigtree["callout"] = $admin->getCallout($data["type"]);
				$bigtree["post_data"] = $_POST["callouts"][$number];
				$bigtree["file_data"] = $bigtree["callout_file_data"][$number];
				
				foreach ($bigtree["callout"]["resources"] as $resource) {
					unset($value); // Backwards compat.
					$field = array();
					$field["key"] = $key = $resource["id"];
					$field["options"] = $options = $resource;
					$field["options"]["directory"] = $options["directory"] = "files/pages/";
					$field["ignore"] = false;
					$field["input"] = $bigtree["post_data"][$resource["id"]];
					$field["file_input"] = $bigtree["file_data"][$resource["id"]];
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($field["input"]) && is_array(json_decode($field["input"],true))) {
						$field["input"] = json_decode($field["input"],true);
					}

					// If we have a customized handler for this data type, run it, otherwise, it's simply the post value.
					$field_type_path = BigTree::path("admin/form-field-types/process/".$resource["type"].".php");
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
					
					if (!BigTreeAutoModule::validate($field["output"],$field["options"]["validation"])) {
						$error = $field["options"]["error_message"] ? $field["options"]["error_message"] : BigTreeAutoModule::validationErrorMessage($field["output"],$field["options"]["validation"]);
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
						$bigtree["entry"][$field["key"]] = $field["output"];
					}
				}
				$bigtree["parsed_callouts"][] = $bigtree["entry"];
			}
		}
	}
		
	$_POST["callouts"] = $bigtree["parsed_callouts"];
?>