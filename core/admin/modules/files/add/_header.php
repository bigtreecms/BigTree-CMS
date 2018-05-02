<?php
	// Create custom breadcrumb
	$bigtree["breadcrumb"] = [
		["link" => "files", "title" => "Files"]
	];

	$breadcrumb = $admin->getResourceFolderBreadcrumb($bigtree["commands"][0]);

	foreach ($breadcrumb as $piece) {
		$bigtree["breadcrumb"][] = ["link" => "files/folder/".$piece["id"], "title" => $piece["name"]];
	}
