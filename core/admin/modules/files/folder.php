<?php
	namespace BigTree;
	
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];
	
	$folder_id = intval(Router::$Commands[0]);
	$folder = new ResourceFolder($folder_id);
	$permission = $folder->UserAccessLevel;
	$contents = $folder->Contents;
	$breadcrumb = $folder->Breadcrumb;

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}

	include "_list.php";
