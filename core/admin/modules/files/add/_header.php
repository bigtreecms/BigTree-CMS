<?php
	namespace BigTree;
	
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];
	
	$folder = new ResourceFolder($bigtree["commands"][0]);
	$breadcrumb = $folder->Breadcrumb;
	$access_level = $folder->UserAccessLevel;

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}
	
	if ($access_level != "p") {
		Auth::stop("You do not have permission to create content in this folder.");
	}
