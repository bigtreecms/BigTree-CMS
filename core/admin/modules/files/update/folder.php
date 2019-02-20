<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$folder_id = intval($_POST["folder"]);
	
	if (!ResourceFolder::exists($folder_id)) {
		Auth::stop("Folder does not exist.");
	}
	
	$folder = new ResourceFolder($folder_id);
	
	if ($folder->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}
	
	$folder->Name = $_POST["name"];
	
	if (Auth::user()->Level > 0) {
		$folder->Parent = $_POST["parent"];
	}
	
	$folder->save();
	
	Utils::growl("Files", "Updated Folder");
	Router::redirect(ADMIN_ROOT."files/folder/".$folder->Parent."/");
