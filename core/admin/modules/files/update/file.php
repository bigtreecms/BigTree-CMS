<?php
	$admin->verifyCSRFToken();
	$file = $admin->getResource($_POST["id"]);
	$permission = $admin->getResourceFolderPermission($file["folder"]);

	if ($permission != "p") {
		$admin->stop("Access denied.");
	}

	$data = [
		"name" => BigTree::safeEncode($_POST["name"]),
		"metadata" => []
	];

	if ($admin->Level) {
		$data["folder"] = $_POST["folder"] ? intval($_POST["folder"]) : null;
	}

	$metadata = $cms->getSetting("bigtree-file-metadata-fields");

	if ($file["is_image"]) {
		$meta_fields = $metadata["image"];
	} elseif ($file["is_video"]) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}

	if (is_array($meta_fields) && count($meta_fields)) {
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = BigTree::parsedFilesArray();

		foreach ($meta_fields as $meta) {
			$field = array(
				"type" => $meta["type"],
				"title" => $meta["title"],
				"key" => "metadata[".$meta["id"]."]",
				"settings" => json_decode($meta["settings"] ?: $meta["options"], true),
				"ignore" => false,
				"input" => $_POST["metadata"][$meta["id"]],
				"file_input" => $bigtree["file_data"]["metadata"][$meta["id"]]
			);

			$output = BigTreeAdmin::processField($field);

			if (!is_null($output)) {
				$data["metadata"][$meta["id"]] = $output;
			}
		}
	}

	$admin->updateResource($_POST["id"], $data);
	$admin->growl("File Manager", "Updated File");

	BigTree::redirect(ADMIN_ROOT."files/folder/".$file["folder"]."/");
