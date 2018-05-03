<?php
	$permission = $admin->getResourceFolderPermission($bigtree["commands"][0]);

	if ($permission != "p") {
		$admin->stop("You do not have permission to create content in this folder.");
	}

	$dir = opendir(SITE_ROOT."files/temporary/".$admin->ID."/");

	while ($file = readdir($dir)) {
		if ($file == "." || $file == "..") {
			continue;
		}

		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if ($extension == "jpg" || $extension == "jpeg" || $extension == "png" || $extension == "gif") {
			continue;
		}
	
		$file_name = SITE_ROOT."files/temporary/".$admin->ID."/".$file;

		$storage = new BigTreeStorage;
		$output = $storage->store($file_name, $file, "files/resources/");

		if ($output) {
			$admin->createResource($bigtree["commands"][0], $output, $file, "file");
		}
	}

	$admin->growl("File Manager", "Uploaded Files");

	BigTree::redirect(ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/");
	