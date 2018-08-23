<?php
	$folder = intval($bigtree["commands"][0]);
	$permission = $admin->getResourceFolderPermission($folder);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	$crops = [];

	foreach ($_POST["files"] as $file) {
		$file = json_decode($file, true);
		$crops = array_merge($crops, $file["crops"]);
		$last_resource_id = $file["resource"];
	}

	$return_link = (count($_POST["files"]) > 1) ? ADMIN_ROOT."files/folder/$folder/" :  ADMIN_ROOT."files/edit/file/$last_resource_id/";
	
	if (count($crops)) {
		$_SESSION["bigtree_admin"]["form_data"] = [
			"edit_link" => ADMIN_ROOT."files/folder/$folder/",
			"return_link" => $return_link,
			"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $crops)
		];

		BigTree::redirect(ADMIN_ROOT."files/crop/$folder/");
	} else {
		BigTree::redirect($return_link);
	}
