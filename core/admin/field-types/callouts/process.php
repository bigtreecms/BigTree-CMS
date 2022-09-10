<?php
	// We're going to change these $bigtree entries, so save them to revert back.
	$saved = [
		"entry" => $bigtree["entry"],
		"post_data" => $bigtree["post_data"],
		"file_data" => $bigtree["file_data"]
	];
	
	$callouts = [];
	
	if (is_array($field["input"]) && count($field["input"])) {
		foreach ($field["input"] as $number => $data) {
			// Make sure there's a callout here
			if ($data["type"]) {
				
				// Setup the new callout to emulate a normal field processing environment
				$bigtree["entry"] = [
					"type" => $data["type"],
					"display_title" => $data["display_title"] ?? "",
				];
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $field["file_input"][$number];
				
				$callout_info = $admin->getCallout($data["type"]);
				$callout_info["resources"] = $admin->runHooks("fields", "callout", $callout_info["resources"], [
					"callout" => $callout_info,
					"step" => "process",
					"post_data" => $bigtree["post_data"],
					"file_data" => $bigtree["file_data"]
				]);
				
				foreach ($callout_info["resources"] as $resource) {
					$sub_field = [
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"settings" => $resource["settings"] ?? $resource["options"] ?? [],
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]] ?? null,
						"file_input" => $bigtree["file_data"][$resource["id"]] ?? null,
					];
					
					if (empty($sub_field["settings"]["directory"])) {
						$sub_field["settings"]["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($sub_field["input"]) && is_array(json_decode($sub_field["input"], true))) {
						$sub_field["input"] = json_decode($sub_field["input"], true);
					}
					
					$output = BigTreeAdmin::processField($sub_field);
					
					if (!is_null($output)) {
						$bigtree["entry"][$sub_field["key"]] = $output;
					}
					
					if ($callout_info["display_field"] == $resource["id"]) {
						$bigtree["entry"]["display_title"] = strip_tags(strval($output));
					}
				}
				
				$callouts[] = $bigtree["entry"];
			}
		}
	}
	
	// Revert to saved values	
	foreach ($saved as $key => $val) {
		$bigtree[$key] = $val;
	}
	
	$field["output"] = $callouts;
