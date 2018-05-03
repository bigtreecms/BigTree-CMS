<?php
	$admin->verifyCSRFToken();
	$file = $admin->getResource($_POST["id"]);

	if (!$file) {
		$admin->stop("Invalid resource.");
	}

	$permission = $admin->getResourceFolderPermission($file["folder"]);

	if ($permission != "p") {
		$admin->stop("Access denied.");
	}

	$admin->deleteResource($_POST["id"]);
	$admin->growl("File Manager", "Deleted File");

	BigTree::redirect(ADMIN_ROOT."files/folder/".intval($file["folder"])."/");
	