<?php
	namespace BigTree;
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	Auth::user()->requireLevel(1);
	$item = $admin->getSetting($_POST["id"]);
	if ($item["system"] || ($item["locked"] && Auth::user()->Level < 2)) {
		Utils::growl("Settings", "Access Denied", "error");
	} else {
		$bigtree["crops"] = array();
		$bigtree["errors"] = array();
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = Field::getParsedFilesArray();
		
		$field = new Field(array(
			"type" => $item["type"],
			"title" => $item["title"],
			"key" => "value",
			"options" => json_decode($item["options"], true),
			"ignore" => false,
			"input" => $bigtree["post_data"]["value"],
			"file_input" => $bigtree["file_data"]["value"]
		));
		
		// Process the input
		$output = $field->process();
		if (!is_null($output)) {
			$admin->updateSettingValue($_POST["id"], $output);
		}
		
		Utils::growl("Settings", "Updated Setting");
	}
	
	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"errors" => $bigtree["errors"]
	);
	
	// Track resource allocation
	$admin->allocateResources("settings", $_POST["id"]);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."settings/crop/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."settings/error/");
	}
	
	Router::redirect(ADMIN_ROOT."settings/");
	