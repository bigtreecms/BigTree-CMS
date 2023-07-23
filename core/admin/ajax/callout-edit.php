<?php
	$callout = $admin->getCallout($_POST["type"]);
	$key = $_POST["key"];
	$count = $_POST["count"];
	$tabindex = $_POST["tab_index"];
	$existing_data = json_decode($_POST["data"], true);
	$cached_types = $admin->getCachedFieldTypes();
	
	$bigtree["field_types"] = $cached_types["callouts"];
	$bigtree["field_namespace"] = uniqid("callout_field_");
	$bigtree["html_fields"] = [];
	$bigtree["simple_html_fields"] = [];
	
	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}
	
	// Run hooks for modifying the field array
	$callout["resources"] = $admin->runHooks("fields", "callout", $callout["resources"], [
		"callout" => $callout,
		"step" => "draw"
	]);
	
	$bigtree["callout"] = $callout;
	
	echo '<input type="hidden" name="'.$key.'['.$count.'][type]" value="'.$callout["id"].'">';
	
	foreach ($callout["resources"] as $resource) {
		$field = [
			"type" => $resource["type"],
			"title" => $resource["title"],
			"subtitle" => $resource["subtitle"],
			"key" => $key."[$count][".$resource["id"]."]",
			"tabindex" => $tabindex,
			"settings" => $resource["settings"] ?? $resource["options"] ?? [],
			"value" => $existing_data[$resource["id"]] ?? "",
			"has_value" => isset($existing_data[$resource["id"]]),
		];
		
		if (empty($field["settings"]["directory"])) {
			$field["settings"]["directory"] = "files/callouts/";
		}
		
		BigTreeAdmin::drawField($field);
	}
	
	echo '<button class="matrix_collapse button green">Done Editing</button>';
	
	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$bigtree["html_editor_height"] = 365;
		include BigTree::path("admin/layouts/_html-field-loader.php");
	}
	
	include BigTree::path("admin/layouts/_ajax-ready-loader.php");
