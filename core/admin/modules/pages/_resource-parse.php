<?php
	namespace BigTree;
	
	$template = new Template($_POST["template"]);
	
	// Parse the resources
	$bigtree["entry"] = [];
	$bigtree["template"] = $template->Array;
	$bigtree["file_data"] = Field::getParsedFilesArray("resources");
	
	// Run any pre-process hook
	$bigtree["preprocessed"] = [];
	
	if (!empty($template->Hooks["pre"]))) {
		$bigtree["preprocessed"] = call_user_func($template->Hooks["pre"], $_POST["resources"]);
		
		// Update the $_POST
		if (is_array($bigtree["preprocessed"])) {
			foreach ($bigtree["preprocessed"] as $key => $val) {
				$_POST["resources"][$key] = $val;
			}
		}
	}
	
	$bigtree["post_data"] = $_POST["resources"];

	foreach ($template->Fields as $resource) {
		$field = [
			"type" => $resource["type"],
			"title" => $resource["title"],
			"subtitle" => $resource["subtitle"],
			"key" => $resource["id"],
			"settings" => $resource["settings"],
			"ignore" => false,
			"input" => $bigtree["post_data"][$resource["id"]],
			"file_input" => $bigtree["file_data"][$resource["id"]]
		];
		
		if (empty($field["settings"]["directory"])) {
			$field["settings"]["directory"] = "files/pages/";
		}
		
		$field = new Field($field);
		$output = $field->process();
		
		if (!is_null($output)) {
			$bigtree["entry"][$field->Key] = $output;
		}
	}
	
	// We save it back to the post array because we're just going to feed the whole post array to createPage / updatePage
	$_POST["resources"] = $bigtree["entry"];
