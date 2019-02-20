<?php
	namespace BigTree;
	
	CSRF::verify();
	
	if (!Resource::exists($_POST["id"])) {
		Auth::stop("Invalid resource.");
	}
	
	$file = new Resource($_POST["id"]);
	
	if ($file->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}

	$file->delete();
	
	Utils::growl("File Manager", "Deleted File");
	Router::redirect(ADMIN_ROOT."files/folder/".$file->Folder."/");
	