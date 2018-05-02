<?php
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"],
		["link" => "files/folder/0", "title" => "Home"]
	];

	$permission = $admin->getResourceFolderPermission(0);
	$contents = $admin->getContentsOfResourceFolder(0);
	$folder = ["id" => 0];

	include "_list.php";
