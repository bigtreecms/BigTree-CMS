<?php
	$admin->verifyCSRFToken();
	$admin->createResourceFolder($_POST["folder"], $_POST["name"]);
	$admin->growl("Files", "Created Folder");

	BigTree::redirect(ADMIN_ROOT."files/folder/".intval($_POST["folder"])."/");
