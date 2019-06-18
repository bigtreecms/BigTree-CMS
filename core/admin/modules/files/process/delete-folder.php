<?php
	namespace BigTree;
	
	CSRF::verify();
	Auth::user()->requireLevel(1);
	
	if (!ResourceFolder::exists($_POST["id"])) {
		Auth::stop("Folder does not exist.");
	} elseif (empty($_POST["id"])) {
		Auth::stop("You may not delete the root folder.");
	}

	$folder = new ResourceFolder($_POST["id"]);
	$folder->delete();

	Admin::growl("File Manager", "Deleted Folder");
	Router::redirect(ADMIN_ROOT."files/folder/".$folder->Parent."/");
	