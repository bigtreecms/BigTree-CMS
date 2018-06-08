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

	if (!empty($_FILES["file"]["tmp_name"])) {
		$storage = new BigTreeStorage;
		$file_name = pathinfo($file["file"], PATHINFO_BASENAME);

		if ($file["is_image"]) {
			include BigTree::path("admin/modules/files/process/_crop-setup.php");
			$min_height = intval($preset["min_height"]);
			$min_width = intval($preset["min_width"]);

			list($width, $height, $type, $attr) = getimagesize($_FILES["file"]["tmp_name"]);

			// Scale up content that doesn't meet minimums
			if ($width < $min_width || $height < $min_height) {
				BigTree::createUpscaledImage($_FILES["file"]["tmp_name"], $_FILES["file"]["tmp_name"], $min_width, $min_height);
				list($width, $height, $type, $attr) = getimagesize($_FILES["file"]["tmp_name"]);
			}

			$field = [
				"title" => $file["name"],
				"file_input" => [
					"tmp_name" => $_FILES["file"]["tmp_name"],
					"name" => $file_name,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			];

			$data["width"] = $width;
			$data["height"] = $height;
			$data["size"] = filesize($_FILES["file"]["tmp_name"]);

			$admin->processImageUpload($field, true);
			$admin->updateResource($_POST["id"], $data);

			$_SESSION["bigtree_admin"]["form_data"] = [
				"edit_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
				"return_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
				"crop_key" => $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"])
			];

			BigTree::redirect(ADMIN_ROOT."files/crop/".intval($bigtree["commands"][0])."/");

			die();
		} elseif (!$file["is_video"]) {
			$data["size"] = filesize($_FILES["file"]["tmp_name"]);
			$storage->replace($_FILES["file"]["tmp_name"], $file_name, "files/resources/");
		}
	}

	$admin->updateResource($_POST["id"], $data);
	$admin->growl("File Manager", "Updated File");

	BigTree::redirect(ADMIN_ROOT."files/folder/".$file["folder"]."/");
