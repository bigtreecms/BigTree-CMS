<?php
	namespace BigTree;
	
	/**
	 * @global $this Field
	 */
	
	if (!is_array($this->Input)) {
		$this->Input = [];
	}
	
	$this->Output = [];
	
	// Make sure file-only entries are represented
	if (is_array($this->FileInput)) {
		foreach ($this->FileInput as $index => $data) {
			if (!isset($this->Input[$index])) {
				$this->Input[$index] = [];
			}
		}
	}
	
	foreach ($this->Input as $index => $data) {
		// Make sure something has been entered
		if (!array_filter((array) $data) && !array_filter((array) $this->FileInput[$index])) {
			continue;
		}
		
		$entry = [
			"__internal-title" => $data["__internal-title"],
			"__internal-subtitle" => $data["__internal-subtitle"]
		];
		
		foreach ($this->Settings["columns"] as $column) {
			// Sanitize field settings
			$settings = @json_decode($column["settings"], true);
			$settings = is_array($settings) ? $settings : [];
			
			if (empty($settings["directory"])) {
				$settings["directory"] = "files/pages/";
			}
			
			// Sanitize user input
			$input = $data[$column["id"]];
			
			if (is_string($input) && is_array(json_decode($input, true))) {
				$input = json_decode($input, true);
			}
			
			// Process the sub-field
			$sub_field = new Field([
				"type" => $column["type"],
				"title" => $column["title"],
				"key" => $column["id"],
				"settings" => $settings,
				"input" => $input,
				"file_input" => $this->FileInput[$index][$column["id"]],
				"file_data" => $this->FileInput[$index],
				"post_data" => $data
			]);
			
			$output = $sub_field->process();
			
			if (!is_null($output)) {
				$entry[$column["id"]] = $output;
			}
		}
		
		$this->Output[] = $entry;
	}
