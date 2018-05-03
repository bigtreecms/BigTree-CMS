<?php
	$admin->verifyCSRFToken();
	$admin->requireLevel(1);

	$folder = $admin->getResourceFolder($_POST["id"]);
	$admin->deleteResourceFolder($_POST["id"]);
	$admin->growl("File Manager", "Deleted Folder");

	BigTree::redirect(ADMIN_ROOT."files/folder/".intval($folder["parent"])."/");
	