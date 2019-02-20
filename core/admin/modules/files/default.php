<?php
	namespace BigTree;
	
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"],
		["link" => "files/folder/0", "title" => "Home"]
	];
	
	$folder = new ResourceFolder(0);
	$permission = $folder->UserAccessLevel;
	$contents = $folder->Contents;

	include "_list.php";
