<?php
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

		$entry = [];

		// Process a manual video upload
		if ($data["info"]["*localvideo"] || $field["file_input"][$index]["info"]["*localvideo"]["tmp_name"]) {
			// Process the uploaded video
			$output = BigTreeAdmin::processField([
				"title" => "Video",
				"key" => "*localvideo",
				"type" => "upload",
				"input" => $data["info"]["*localvideo"],
				"file_input" => $field["file_input"][$index]["info"]["*localvideo"],
				"settings" => $field["settings"]
			]);
			
			// If this field fails, we shouldn't upload the image
			if ($output) {
				$entry["type"] = "video";
				$entry["video"] = [
					"service" => "local",
					"url" => $output
				];

				// Process the cover image
				$output = BigTreeAdmin::processField([
					"title" => "Photo",
					"key" => "*photo",
					"type" => "image",
					"input" => $data["info"]["*photo"],
					"file_input" => $field["file_input"][$index]["info"]["*photo"],
					"settings" => $field["settings"]
				]);
				
				if ($output) {
					$entry["image"] = $output;
				}
			}
			
		// Process a photo upload
		} elseif ($data["info"]["*photo"] || $field["file_input"][$index]["info"]["*photo"]["tmp_name"]) {
			$output = BigTreeAdmin::processField([
				"title" => "Photo",
				"key" => "*photo",
				"type" => "image",
				"input" => $data["info"]["*photo"],
				"file_input" => $field["file_input"][$index]["info"]["*photo"],
				"settings" => $field["settings"],
				"recrop" => !empty($data["info"]["__*photo_recrop__"]),
			]);
			
			if ($output) {
				$entry["type"] = "photo";
				$entry["image"] = $output;
			}
			
		// Process a video
		} elseif ($data["info"]["*video"]) {
			$output = BigTreeAdmin::processField([
				"title" => "Video URL",
				"key" => "*video",
				"type" => "video",
				"input" => $data["info"]["*video"],
				"file_input" => $field["file_input"][$index]["info"]["*video"],
				"settings" => $field["settings"]
			]);
			
			if ($output) {
				$entry["type"] = "video";
				$entry["image"] = $output["image"];
				unset($output["image"]);
				$entry["video"] = $output;
			}
			
		// Existing unchanged field
		} elseif ($data["type"]) {
			$entry = $data;
		}
		
		// Only run the rest if we successfully processed a video or photo
		if (!array_filter((array) $entry)) {
			continue;
		}
			
		// Handle all the additional columns
		foreach (array_filter((array) $field["settings"]["columns"]) as $resource) {
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
			$input = $data["info"][$resource["id"]];

			if (is_string($input) && is_array(json_decode($input, true))) {
				$input = json_decode($input, true);
			}
			
			$output = BigTreeAdmin::processField([
				"type" => $resource["type"],
				"title" => $resource["title"],
				"key" => $resource["id"],
				"settings" => $settings,
				"input" => $input,
				"file_input" => $field["file_input"][$index]["info"][$resource["id"]]
			]);
			
			if (!is_null($output)) {
				$entry["info"][$resource["id"]] = $output;
			}
		}
		
		$field["output"][] = $entry;
	}
	
	foreach ($field["output"] as $index => $entry) {
		if (!empty($entry["info"]["caption"])) {
			$field["output"][$index]["caption"] = BigTree::safeEncode(strip_tags($entry["info"]["caption"]));
		}
	}
