<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	// We're going to change these $bigtree entries, so save them to revert back.
	$this->SavedData = [
		"entry" => $bigtree["entry"],
		"post_data" => $bigtree["post_data"],
		"file_data" => $bigtree["file_data"]
	];
	
	$callouts = [];
	
	if (is_array($this->Input) && count($this->Input)) {
		foreach ($this->Input as $number => $data) {
			// Make sure there's a callout here
			if ($data["type"]) {
				
				// Setup the new callout to emulate a normal field processing environment
				$bigtree["entry"] = ["type" => $data["type"], "display_title" => $data["display_title"]];
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $this->FileInput[$number];
				
				$callout = new Callout($data["type"]);
				
				foreach ($callout->Fields as $resource) {
					$sub_field = array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"settings" => $resource["settings"],
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
					
					if (!is_array($sub_field["settings"])) {
						$sub_field["settings"] = [];
					}
					
					// Setup default directory
					if (empty($sub_field["settings"]["directory"])) {
						$sub_field["settings"]["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($sub_field["input"]) && is_array(json_decode($sub_field["input"], true))) {
						$sub_field["input"] = json_decode($sub_field["input"], true);
					}
					
					$sub_field = new Field($sub_field);
					$output = $sub_field->process();
					
					if (!is_null($output)) {
						$bigtree["entry"][$sub_field->Key] = $output;
					}
				}
				
				$callouts[] = $bigtree["entry"];
				
			}
		}
	}
	
	// Revert to saved values	
	foreach ($this->SavedData as $key => $val) {
		$bigtree[$key] = $val;
	}
	
	$this->Output = $callouts;
