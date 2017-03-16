<?
	// See if we've hit post_max_size
	if (!$_POST["_bigtree_post_check"]) {
		$_SESSION["bigtree_admin"]["post_max_hit"] = true;
		BigTree::redirect($_SERVER["HTTP_REFERER"]);
	}

	$admin->verifyCSRFToken();
	$admin->requireLevel(1);

	$item = $admin->getSetting($_POST["id"]);

	if ($item["system"] || ($item["locked"] && $admin->Level < 2)) {
		$admin->growl("Settings","Access Denied","error");
	} else {
		$bigtree["crops"] = array();
		$bigtree["errors"] = array();
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = BigTree::parsedFilesArray();

		// Pretend like we're a normal field
		$field = array(
			"type" => $item["type"],
			"title" => $item["title"],
			"key" => "value",
			"options" => json_decode($item["options"],true),
			"ignore" => false,
			"input" => $bigtree["post_data"]["value"],
			"file_input" => $bigtree["file_data"]["value"]
		);

		// Process the input
		$output = BigTreeAdmin::processField($field);
		if (!is_null($output)) {
			$admin->updateSettingValue($_POST["id"],$output);
		}

		$admin->growl("Settings","Updated Setting");
	}

	$_SESSION["bigtree_admin"]["form_data"] = array(
		"page" => true,
		"return_link" => ADMIN_ROOT."settings/",
		"edit_link" => ADMIN_ROOT."settings/edit/".$_POST["id"]."/",
		"errors" => $bigtree["errors"]
	);

	// Track resource allocation
	$admin->allocateResources("settings",$_POST["id"]);

	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = $cms->cacheUnique("org.bigtreecms.crops",$bigtree["crops"]);
		BigTree::redirect(ADMIN_ROOT."settings/crop/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."settings/error/");
	}

	BigTree::redirect(ADMIN_ROOT."settings/");
?>