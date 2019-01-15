<?php
	$matrix = [
		"data" => [],
		"field" => $field,
		"saved_entry" => $bigtree["entry"],
		"saved_post_data" => $bigtree["post_data"],
		"saved_file_data" => $bigtree["file_data"]
	];
	
	// Find things that are strictly file based inputs and make a phantom entry in the POST so we don't miss it
	foreach ($matrix["field"]["file_input"] as $number => $data) {
		if (!isset($matrix["field"]["input"][$number])) {
			$matrix["field"]["input"][$number] = [];
		}
	}
	
	if (count($matrix["field"]["input"])) {
		foreach ($matrix["field"]["input"] as $number => $data) {
			// Make sure something has been entered
			if (array_filter((array) $data) || array_filter((array) $matrix["field"]["file_input"][$number])) {
				$bigtree["entry"] = [];
				$bigtree["post_data"] = $data["info"];
				$bigtree["file_data"] = $matrix["field"]["file_input"][$number]["info"];
				
				// Process a manual video upload
				if ($data["info"]["*localvideo"] || $bigtree["file_data"]["*localvideo"]["tmp_name"]) {
					$bigtree["entry"]["type"] = "video";
					
					$field = [
						"title" => "Photo",
						"key" => "*photo",
						"type" => "image",
						"input" => $bigtree["post_data"]["*photo"],
						"file_input" => $bigtree["file_data"]["*photo"],
						"settings" => $matrix["field"]["settings"],
						"ignore" => false
					];
					$output = BigTreeAdmin::processField($field);
					
					if ($output) {
						$bigtree["entry"]["image"] = $output;
					}
					
					$field = [
						"title" => "Video",
						"key" => "*localvideo",
						"type" => "upload",
						"input" => $bigtree["post_data"]["*localvideo"],
						"file_input" => $bigtree["file_data"]["*localvideo"],
						"settings" => $matrix["field"]["settings"],
						"ignore" => false
					];
					$output = BigTreeAdmin::processField($field);
					
					if ($output) {
						$bigtree["entry"]["video"] = [
							"service" => "local",
							"url" => $output
						];
					}
					
				// Process a photo upload
				} elseif ($data["info"]["*photo"] || $bigtree["file_data"]["*photo"]["tmp_name"]) {
					$field = [
						"title" => "Photo",
						"key" => "*photo",
						"type" => "image",
						"input" => $bigtree["post_data"]["*photo"],
						"file_input" => $bigtree["file_data"]["*photo"],
						"settings" => $matrix["field"]["settings"],
						"ignore" => false
					];
					$output = BigTreeAdmin::processField($field);
					
					if ($output) {
						$bigtree["entry"]["type"] = "photo";
						$bigtree["entry"]["image"] = $output;
					}
					
				// Process a video
				} elseif ($data["info"]["*video"]) {
					$field = [
						"title" => "Video URL",
						"key" => "*video",
						"type" => "video",
						"input" => $bigtree["post_data"]["*video"],
						"file_input" => $bigtree["file_data"]["*video"],
						"settings" => $matrix["field"]["settings"],
						"ignore" => false
					];
					$output = BigTreeAdmin::processField($field);
					
					if ($output) {
						$bigtree["entry"]["type"] = "video";
						$bigtree["entry"]["image"] = $output["image"];
						unset($output["image"]);
						$bigtree["entry"]["video"] = $output;
					}
					
				// Existing unchanged field
				} elseif ($data["type"]) {
					$bigtree["entry"] = $data;
				}
				
				// Only run the rest if we successfully processed a video or photo
				if (array_filter((array) $bigtree["entry"])) {
					
					// Handle all the additional columns
					foreach (array_filter((array) $matrix["field"]["settings"]["columns"]) as $resource) {
						$settings = @json_decode($resource["settings"], true);
						$settings = is_array($settings) ? $settings : [];
						
						$field = [
							"type" => $resource["type"],
							"title" => $resource["title"],
							"key" => $resource["id"],
							"settings" => $settings,
							"ignore" => false,
							"input" => $bigtree["post_data"][$resource["id"]],
							"file_input" => $bigtree["file_data"][$resource["id"]]
						];
						
						if (empty($field["settings"]["directory"])) {
							$field["settings"]["directory"] = "files/pages/";
						}
						
						// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
						if (is_string($field["input"]) && is_array(json_decode($field["input"], true))) {
							$field["input"] = json_decode($field["input"], true);
						}
						
						// Process the input
						$output = BigTreeAdmin::processField($field);
						
						if (!is_null($output)) {
							$bigtree["entry"]["info"][$field["key"]] = $output;
						}
					}
					
					$matrix["data"][] = $bigtree["entry"];
				}
			}
		}
	}
	
	foreach ($matrix["data"] as &$item) {
		if (!empty($item["info"]["caption"])) {
			$item["caption"] = $item["info"]["caption"];
		}
	}
	
	$bigtree["entry"] = $matrix["saved_entry"];
	$bigtree["post_data"] = $matrix["saved_post_data"];
	$bigtree["file_data"] = $matrix["saved_file_data"];
	
	$field = $matrix["field"];
	$field["output"] = $matrix["data"];
	