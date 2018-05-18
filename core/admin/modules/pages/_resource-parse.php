<?php
	// Parse the resources
	$bigtree["entry"] = array();
	$bigtree["template"] = $cms->getTemplate($_POST["template"]);
	$bigtree["file_data"] = BigTree::parsedFilesArray("resources");
	$bigtree["post_data"] = $_POST["resources"];

	foreach ((array)$bigtree["template"]["resources"] as $resource) {
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
