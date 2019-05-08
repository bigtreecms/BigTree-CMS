<?php
	namespace BigTree;
	
	$callouts = [];
	
	if (is_array($this->Input) && count($this->Input)) {
		foreach ($this->Input as $number => $data) {
			// Make sure there's a callout here
			if ($data["type"]) {
				
				// Setup the new callout to emulate a normal field processing environment
				$entry = ["type" => $data["type"], "display_title" => $data["display_title"]];
				$post_data = $data;
				$file_data = $this->FileInput[$number];
				
				$callout = new Callout($data["type"]);
				$callout->Fields = Extension::runHooks("fields", "callout", $callout->Fields, [
					"callout" => $callout,
					"step" => "process",
					"post_data" => $post_data,
					"file_data" => $file_data
				]);
				
				foreach ($callout->Fields as $resource) {
					$sub_field = [
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"settings" => $resource["settings"],
						"ignore" => false,
						"input" => $post_data[$resource["id"]],
						"file_input" => $file_data[$resource["id"]],
						"post_data" => $post_data
					];
					
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

					foreach ($sub_field->AlteredColumns as $column => $data) {
						$entry[$column] = $data;
					}
					
					if (!is_null($output)) {
						$entry[$sub_field->Key] = $output;
					}
				}
				
				$callouts[] = $entry;
				
			}
		}
	}
	
	$this->Output = $callouts;
