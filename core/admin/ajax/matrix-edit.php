<?php
	if (!empty($_POST["data"])) {
		$existing_data = json_decode($_POST["data"], true);
	} else {
		$existing_data = [];
	}
	
	$key = $_POST["key"];
	$count = $_POST["count"];
	$tabindex = $_POST["tab_index"] ?? 1;
	$cached_types = $admin->getCachedFieldTypes();
	
	$bigtree["field_types"] = $cached_types["callouts"];
	$bigtree["field_namespace"] = uniqid("callout_field_");
	$bigtree["html_fields"] = [];
	$bigtree["simple_html_fields"] = [];
	$bigtree["matrix_columns"] = $_POST["columns"];
	
	if (!empty($_POST["front_end_editor"]) && $_POST["front_end_editor"] != "false") {
		define("BIGTREE_FRONT_END_EDITOR", true);
	}

	foreach ($bigtree["matrix_columns"] as $resource) {
		if (!empty($resource["settings"])) {
			$settings = is_array($resource["settings"]) ? $resource["settings"] : @json_decode($resource["settings"], true);
		} else if (!empty($resource["options"])) {
			$settings = is_array($resource["options"]) ? $resource["options"] :@json_decode($resource["options"], true);
		} else {
			$settings = [];
		}
		
		if (!is_array($settings)) {
			$settings = [];
		}
		
		if (empty($settings["directory"])) {
			$settings["directory"] = "files/pages/";
		}
		
		$field = [
			"type" => $resource["type"],
			"title" => $resource["title"] ?? "",
			"subtitle" => $resource["subtitle"] ?? "",
			"key" => $key."[$count][".$resource["id"]."]",
			"tabindex" => $tabindex,
			"settings" => $settings,
			"matrix_title_field" => !empty($resource["display_title"]),
			"has_value" => isset($existing_data[$resource["id"]]),
			"value" => $existing_data[$resource["id"]] ?? "",
		];
		
		BigTreeAdmin::drawField($field);
	}
	
	echo '<button class="matrix_collapse button green">Done Editing</button>';

	if (count($bigtree["html_fields"]) || count($bigtree["simple_html_fields"])) {
		$bigtree["html_editor_height"] = 365;
		include BigTree::path("admin/layouts/_html-field-loader.php");
	}
	
	include BigTree::path("admin/layouts/_ajax-ready-loader.php");
