<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$parent_folder_id = intval($_POST["folder"]);
	
	if (!ResourceFolder::exists($parent_folder_id)) {
		Auth::stop("Parent folder does not exist.");
	}
	
	$parent_folder = new ResourceFolder($parent_folder_id);
	
	if ($parent_folder->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}
	
	ResourceFolder::create($parent_folder_id, $_POST["name"]);
	Admin::growl("Files", "Created Folder");
	Router::redirect(ADMIN_ROOT."files/folder/".$parent_folder_id."/");
