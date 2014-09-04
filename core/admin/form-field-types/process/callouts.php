<?
	// Some backwards compat stuff.
	$upload_service = new BigTreeUploadService;
	$bigtree["callout_field"] = $field;
	$bigtree["saved_entry"] = $bigtree["entry"];
	$bigtree["saved_post_data"] = $bigtree["post_data"];
	$bigtree["saved_file_data"] = $bigtree["file_data"];
	$bigtree["parsed_callouts"] = array();

	if (count($bigtree["callout_field"]["input"])) {
		foreach ($bigtree["callout_field"]["input"] as $number => $data) {
			// Make sure there's a callout here...
			if ($data["type"]) {
				// Setup the new callout for fun-ness.
				$bigtree["entry"] = array("type" => $data["type"],"display_title" => $data["display_title"]);
				$bigtree["callout"] = $admin->getCallout($data["type"]);
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $bigtree["callout_field"]["file_input"][$number];
				
				foreach ($bigtree["callout"]["resources"] as $resource) {
					$field = array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"options" => $resource["options"],
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
					if (empty($field["options"]["directory"])) {
						$field["options"]["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($field["input"]) && is_array(json_decode($field["input"],true))) {
						$field["input"] = json_decode($field["input"],true);
					}

					$output = BigTreeAdmin::processField($field);
					if (!is_null($output)) {
						$bigtree["entry"][$field["key"]] = $field["output"];
					}
				}
				$bigtree["parsed_callouts"][] = $bigtree["entry"];
			}
		}
	}
	
	$bigtree["entry"] = $bigtree["saved_entry"];
	$bigtree["post_data"] = $bigtree["saved_post_data"];
	$bigtree["file_data"] = $bigtree["saved_file_data"];
	$field = $bigtree["callout_field"];
	$field["output"] = $bigtree["parsed_callouts"];
?>