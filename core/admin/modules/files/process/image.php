<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$folder_id = intval($bigtree["commands"][0]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("Folder does not exist.");
	}
	
	$folder = new ResourceFolder($folder_id);
	
	if ($folder->UserAccessLevel != "p") {
		Auth::stop("You do not have permission to create content in this folder.");
	}

	$crops = [];
	$last_resource_id = null;

	foreach ($_POST["files"] as $file) {
		$file = json_decode($file, true);
		$crops = array_merge($crops, $file["crops"]);
		$last_resource_id = $file["resource"];
	}

	$return_link = (count($_POST["files"]) > 1) ? ADMIN_ROOT."files/folder/$folder_id/" : ADMIN_ROOT."files/edit/file/$last_resource_id/";
	
	
	if (count($crops)) {
		$_SESSION["bigtree_admin"]["form_data"] = [
			"edit_link" => ADMIN_ROOT."files/folder/$folder_id/",
			"return_link" => $return_link,
			"crop_key" => Cache::putUnique("org.bigtreecms.crops", $crops)
		];

		Router::redirect(ADMIN_ROOT."files/crop/$folder_id/");
	} else {
		Router::redirect($return_link);
	}
