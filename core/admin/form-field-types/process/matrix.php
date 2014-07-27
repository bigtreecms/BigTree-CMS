<?
	$bigtree["btx-matrix"] = array(
		"data" => array(),
		"field" => $field,
		"saved_entry" => $bigtree["entry"],
		"saved_post_data" => $bigtree["post_data"],
		"saved_file_data" => $bigtree["file_data"]
	);
	
	if (count($bigtree["btx-matrix"]["field"]["input"])) {
		foreach ($bigtree["btx-matrix"]["field"]["input"] as $number => $data) {
			// Make sure something has been entered
			if (array_filter((array)$data) || array_filter((array)$bigtree["btx-matrix"]["field"]["file_input"][$number])) {
				$bigtree["entry"] = array("__internal-title" => $data["__internal-title"],"__internal-subtitle" => $data["__internal-subtitle"]);
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $bigtree["btx-matrix"]["field"]["file_input"][$number];
				
				foreach ($bigtree["btx-matrix"]["field"]["options"]["columns"] as $resource) {
					$options = @json_decode($resource["options"],true);
					$options = is_array($options) ? $options : array();

					$field = array(
						"key" => $resource["id"],
						"options" => $options,
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
					$field["options"]["title"] = $resource["title"];
					$field["options"]["subtitle"] = $resource["subtitle"];
	
					if (empty($field["options"]["directory"])) {
						$field["options"]["directory"] = "files/pages/";
					}
					
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
							$field["output"] = BigTree::safeEncode($bigtree["post_data"][$field["key"]]);
						}
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
				$bigtree["btx-matrix"]["data"][] = $bigtree["entry"];
			}
		}
	}
	
	$bigtree["entry"] = $bigtree["btx-matrix"]["saved_entry"];
	$bigtree["post_data"] = $bigtree["btx-matrix"]["saved_post_data"];
	$bigtree["file_data"] = $bigtree["btx-matrix"]["saved_file_data"];
	$field = $bigtree["btx-matrix"]["field"];
	$field["output"] = $bigtree["btx-matrix"]["data"];
?>