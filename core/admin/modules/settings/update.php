<?php
	$admin->verifyCSRFToken();
	$admin->requireLevel(1);
	
	$item = $admin->getSetting($_POST["id"]);
	
	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings", "Access Denied", "error");
	} else {
		$bigtree["entry"] = [];
		$bigtree["crops"] = [];
		$bigtree["errors"] = [];
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = BigTree::parsedFilesArray();
		
		// Pretend like we're a normal field
		$field = [
			"type" => $item["type"],
			"title" => $item["name"],
			"key" => "value",
			"settings" => $item["settings"],
			"ignore" => false,
			"input" => $bigtree["post_data"]["value"] ?? null,
			"file_input" => $bigtree["file_data"]["value"] ?? null,
		];
		
		// Process the input
		$output = BigTreeAdmin::processField($field);
		
		if (!is_null($output)) {
			$admin->updateSettingValue($_POST["id"], $output);
		}
		
		$admin->growl("Settings", "Updated Setting");
	}
	
	$_SESSION["bigtree_admin"]["form_data"] = [
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"errors" => $bigtree["errors"]
	];
	
	// Track resource allocation
	$admin->allocateResources("bigtree_settings", $_POST["id"]);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"]);
		BigTree::redirect(ADMIN_ROOT."settings/crop/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."settings/error/");
	}
	
	BigTree::redirect(ADMIN_ROOT."settings/");
