<?php
	$admin->verifyCSRFToken();
	$admin->updateResourceFolder($_POST["folder"], $_POST["name"], isset($_POST["parent"]) ? $_POST["parent"] : null);
	$admin->growl("Files", "Updated Folder");

	$folder = $admin->getResourceFolder($_POST["folder"]);

	BigTree::redirect(ADMIN_ROOT."files/folder/".$folder["parent"]."/");
