<?php
	// We're going to change these $bigtree entries, so save them to revert back.
	$saved = array(
		"entry" => $bigtree["entry"],
		"post_data" => $bigtree["post_data"],
		"file_data" => $bigtree["file_data"]
	);

	if (!is_array($field["input"])) {
		$field["input"] = [];
	}

	$field["output"] = [];

	// Make sure file-only entries are represented
	if (is_array($field["file_input"])) {
		foreach ($field["file_input"] as $index => $data) {
			if (!isset($field["input"][$index])) {
				$field["input"][$index] = [];
			}
		}
	}

	foreach ($field["input"] as $index => $data) {
		// Make sure something has been entered
		if (!array_filter((array) $data) && !array_filter((array) $field["file_input"][$index])) {
			continue;
		}

		// Setup the new callout to emulate a normal field processing environment
		$bigtree["post_data"] = $data;
		$bigtree["file_data"] = $field["file_input"][$index];
		$bigtree["entry"] = $entry = [
			"__internal-title" => "",
			"__internal-subtitle" => ""
		];
		
		foreach ($field["settings"]["columns"] as $resource) {
			if (is_array($resource["settings"])) {
				$settings = $resource["settings"];
			} else {
				// Sanitize field settings
				$settings = @json_decode($resource["settings"], true);
				$options = @json_decode($resource["options"], true); // Backwards compat
				
				if (empty($settings) || !is_array($settings)) {
					$settings = $options;
				}
				
				$settings = is_array($settings) ? $settings : [];
			}

			if (empty($settings["directory"])) {
				$settings["directory"] = "files/pages/";
			}

			// Sanitize user input
			$input = $data[$resource["id"]] ?? null;

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
				"file_input" => $field["file_input"][$index][$resource["id"]] ?? null,
			]);

			if (!empty($resource["display_title"])) {
				if (empty($entry["__internal-title"])) {
					$entry["__internal-title"] = $output;
				} elseif (empty($entry["__internal-subtitle"])) {
					$entry["__internal-subtitle"] = $output;
				}
			}

			if (!is_null($output)) {
				$entry[$resource["id"]] = $output;
			}
		}
		
		$field["output"][] = $entry;
	}

	// Revert to saved values	
	foreach ($saved as $key => $val) {
		$bigtree[$key] = $val;
	}
