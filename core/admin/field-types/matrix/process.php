<?php
	if (!is_array($field["input"])) {
		$field["input"] = [];
	}

	$field["output"] = [];

	// Make sure file-only entries are represented
	foreach ($field["file_input"] as $index => $data) {
		if (!isset($field["input"][$index])) {
			$field["input"][$index] = [];
		}
	}

	foreach ($field["input"] as $index => $data) {
		// Make sure something has been entered
		if (!array_filter((array) $data) && !array_filter((array) $field["file_input"][$index])) {
			continue;
		}

		$entry = [
			"__internal-title" => $data["__internal-title"],
			"__internal-subtitle" => $data["__internal-subtitle"]
		];
		
		foreach ($field["settings"]["columns"] as $resource) {
			// Sanitize field settings
			$settings = @json_decode($resource["settings"], true);
			$options = @json_decode($resource["options"], true); // Backwards compat

			if (empty($settings) || !is_array($settings)) {
				$settings = $options;
			}
			
			$settings = is_array($settings) ? $settings : [];

			if (empty($settings["directory"])) {
				$settings["directory"] = "files/pages/";
			}

			// Sanitize user input
			$input = $data[$resource["id"]];

			if (is_string($input) && is_array(json_decode($input, true))) {
				$input = json_decode($input, true);
			}

			// Process the sub-field
			$output = BigTreeAdmin::processField([
				"type" => $resource["type"],
				"title" => $resource["title"],
				"key" => $resource["id"],
				"settings" => $settings,
				"input" => $input,
				"file_input" => $field["file_input"][$index][$resource["id"]]
			]);

			if (!is_null($output)) {
				$entry[$resource["id"]] = $output;
			}
		}
		
		$field["output"][] = $entry;
	}
