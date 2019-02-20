<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	Auth::user()->requireLevel(1);
	
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		Router::redirect($_SERVER["HTTP_REFERER"]);
	}
	
	$setting = new Setting($_POST["id"]);
	
	if ($setting->System || ($setting->Locked && Auth::user()->Level < 2)) {
		Utils::growl("Settings", "Access Denied", "error");
	} else {
		$bigtree["crops"] = [];
		$bigtree["errors"] = [];
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = Field::getParsedFilesArray();
		
		$field = new Field([
			"type" => $setting->Type,
			"title" => $setting->Name,
			"key" => "value",
			"settings" => $setting->Settings,
			"ignore" => false,
			"input" => $bigtree["post_data"]["value"],
			"file_input" => $bigtree["file_data"]["value"]
		]);
		
		// Process the input
		$output = $field->process();
		
		if (!is_null($output)) {
			$setting->Value = $output;
			$setting->save();
		}
		
		Utils::growl("Settings", "Updated Setting");
	}
	
	$_SESSION["bigtree_admin"]["form_data"] = [
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"errors" => $bigtree["errors"]
	];
	
	// Track resource allocation
	Resource::allocate("settings", $_POST["id"]);
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."settings/crop/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."settings/error/");
	}
	
	Router::redirect(ADMIN_ROOT."settings/");
	