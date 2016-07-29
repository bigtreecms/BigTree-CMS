<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$matrix = array(
		"data" => array(),
		"saved_entry" => $bigtree["entry"],
		"saved_post_data" => $bigtree["post_data"],
		"saved_file_data" => $bigtree["file_data"]
	);
	
	if (count($matrix["field"]["input"])) {
		foreach ($matrix["field"]["input"] as $number => $data) {
			// Make sure something has been entered
			if (array_filter((array) $data) || array_filter((array) $matrix["field"]["file_input"][$number])) {
				$bigtree["entry"] = array(
					"__internal-title" => $data["__internal-title"],
					"__internal-subtitle" => $data["__internal-subtitle"]
				);
				$bigtree["post_data"] = $data;
				$bigtree["file_data"] = $matrix["field"]["file_input"][$number];
				
				foreach ($matrix["field"]["options"]["columns"] as $resource) {
					$options = @json_decode($resource["options"], true);
					$options = is_array($options) ? $options : array();
					
					$field = array(
						"type" => $resource["type"],
						"title" => $resource["title"],
						"key" => $resource["id"],
						"options" => $options,
						"ignore" => false,
						"input" => $bigtree["post_data"][$resource["id"]],
						"file_input" => $bigtree["file_data"][$resource["id"]]
					);
					
					if (empty($this->Settings["directory"])) {
						$this->Settings["directory"] = "files/pages/";
					}
					
					// If we JSON encoded this data and it hasn't changed we need to decode it or the parser will fail.
					if (is_string($this->Input) && is_array(json_decode($this->Input, true))) {
						$this->Input = json_decode($this->Input, true);
					}
					
					// Process the input
					$field = new Field($field);
					$output = $field->process();
					
					if (!is_null($output)) {
						$bigtree["entry"][$this->Key] = $output;
					}
				}
				$matrix["data"][] = $bigtree["entry"];
			}
		}
	}
	
	$bigtree["entry"] = $matrix["saved_entry"];
	$bigtree["post_data"] = $matrix["saved_post_data"];
	$bigtree["file_data"] = $matrix["saved_file_data"];
	
	$this->Output = $matrix["data"];
	