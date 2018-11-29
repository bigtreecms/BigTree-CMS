<?php
	// Parse the resources
	$bigtree["entry"] = array();
	$bigtree["template"] = $cms->getTemplate($_POST["template"]);
	$bigtree["file_data"] = BigTree::parsedFilesArray("resources");
	
	// Run any pre-process hook
	$bigtree["preprocessed"] = [];

	if (!empty($bigtree["template"]["hooks"]["pre"])) {
		$bigtree["preprocessed"] = call_user_func($bigtree["template"]["hooks"]["pre"], $_POST["resources"]);
		
		// Update the $_POST
		if (is_array($bigtree["preprocessed"])) {
			foreach ($bigtree["preprocessed"] as $key => $val) {
				$_POST["resources"][$key] = $val;
			}
		}
	}

	$bigtree["post_data"] = $_POST["resources"];
	$bigtree["template"]["resources"] = array_filter((array) $bigtree["template"]["resources"]);
	$bigtree["template"]["resources"] = $admin->runHooks("fields", "template", $bigtree["template"]["resources"], [
		"template" => $bigtree["template"],
		"step" => "process",
		"post_data" => $bigtree["post_data"],
		"file_data" => $bigtree["file_data"]
	]);

	foreach ($bigtree["template"]["resources"] as $resource) {
		$settings = $resource["settings"] ?: $resource["options"];

		if (empty($settings["directory"])) {
			$settings["directory"] = "files/pages/";
		}

		$field = array(
			"type" => $resource["type"],
			"title" => $resource["title"],
			"subtitle" => $resource["subtitle"],
			"key" => $resource["id"],
			"settings" => $settings,
			"ignore" => false,
			"input" => $bigtree["post_data"][$resource["id"]],
			"file_input" => $bigtree["file_data"][$resource["id"]]
		);

		$output = BigTreeAdmin::processField($field);
		
		if (!is_null($output)) {
			$bigtree["entry"][$field["key"]] = $output;
		}
	}

	// We save it back to the post array because we're just going to feed the whole post array to createPage / updatePage
	$_POST["resources"] = $bigtree["entry"];
