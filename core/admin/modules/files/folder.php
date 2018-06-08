<?php
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];

	$folder_id = intval($bigtree["commands"][0]);
	$permission = $admin->getResourceFolderPermission($folder_id);
	$contents = $admin->getContentsOfResourceFolder($folder_id);
	$folder = $admin->getResourceFolder($folder_id);
	$breadcrumb = $admin->getResourceFolderBreadcrumb($folder_id);

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}

	include "_list.php";
