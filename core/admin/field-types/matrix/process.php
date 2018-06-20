<?php
	$matrix = array(
		"data" => array(),
		"field" => $field,
		"saved_entry" => $bigtree["entry"],
		"saved_post_data" => $bigtree["post_data"],
		"saved_file_data" => $bigtree["file_data"]
	);

	if (is_array($matrix["field"]["input"]) && count($matrix["field"]["input"])) {
		foreach ($matrix["field"]["input"] as $number => $data) {
			// Make sure something has been entered
			if (array_filter((array)$data) || array_filter((array)$matrix["field"]["file_input"][$number])) {
				$bigtree["entry"] = array("__internal-title" => $data["__internal-title"],"__internal-subtitle" => $data["__internal-subtitle"]);
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $matrix["field"]["file_input"][$number];

				if (empty($matrix["field"]["settings"])) {
					$matrix["field"]["settings"] = $matrix["field"]["options"];
				}
				
				foreach ($matrix["field"]["settings"]["columns"] as $resource) {
					$settings = @json_decode($resource["settings"], true);
					$options = @json_decode($resource["options"], true); // Backwards compat

					if (!is_array($settings)) {
						$settings = $options;
					}
					
					$settings = is_array($settings) ? $settings : array();

					$field = array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"settings" => $settings,
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
	
					if (empty($field["settings"]["directory"])) {
						$field["settings"]["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($field["input"]) && is_array(json_decode($field["input"],true))) {
						$field["input"] = json_decode($field["input"],true);
					}
	
					// Process the input
					$output = BigTreeAdmin::processField($field);

					if (!is_null($output)) {
						$bigtree["entry"][$field["key"]] = $output;
					}
				}
				
				$matrix["data"][] = $bigtree["entry"];
			}
		}
	}
	
	$bigtree["entry"] = $matrix["saved_entry"];
	$bigtree["post_data"] = $matrix["saved_post_data"];
	$bigtree["file_data"] = $matrix["saved_file_data"];
	
	$field = $matrix["field"];
	$field["output"] = $matrix["data"];
