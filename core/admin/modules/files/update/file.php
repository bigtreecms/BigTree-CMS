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

	$metadata = BigTreeJSONDB::get("config", "file-metadata");

	if ($file["is_image"]) {
		$meta_fields = $metadata["image"];
		$settings = BigTreeJSONDB::get("config", "media-settings");
		$preset = $settings["presets"]["default"];
		$preset["directory"] = "files/resources/";
	} elseif ($file["is_video"]) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}

	$bigtree["crops"] = [];

	if (is_array($meta_fields) && count($meta_fields)) {
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = BigTree::parsedFilesArray();

		foreach ($meta_fields as $meta) {
			$field = array(
				"type" => $meta["type"],
				"title" => $meta["title"],
				"key" => "metadata[".$meta["id"]."]",
				"settings" => $meta["settings"] ?: $meta["options"],
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
			$image = new BigTreeImage($_FILES["file"]["tmp_name"], $preset);
			$image->filterGeneratableCrops();
			
			// Get updated crop/thumb arrays
			include BigTree::path("admin/modules/files/process/_resource-prefixes.php");

			$field = [
				"title" => $file["name"],
				"file_input" => [
					"tmp_name" => $image->File,
					"name" => $file_name,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			];

			$data["width"] = $image->Width;
			$data["height"] = $image->Height;
			$data["crops"] = $crop_prefixes;
			$data["thumbs"] = $thumb_prefixes;
			$data["size"] = filesize($image->File);

			if ($admin->processImageUpload($field, true)) {
				// Remove any crops that no longer work with the new image
				foreach ($file["crops"] as $prefix => $size) {
					if (!isset($size["crops"][$prefix])) {
						$storage->delete(BigTree::prefixFile($file["file"], $prefix));
					}
				}
			}
		} elseif (!$file["is_video"]) {
			$data["size"] = filesize($_FILES["file"]["tmp_name"]);
			$storage->replace($_FILES["file"]["tmp_name"], $file_name, "files/resources/");
		}
	} elseif (!empty($_POST["__file_recrop__"])) {
		// User has asked for a re-crop
		$image = new BigTreeImage(str_replace(STATIC_ROOT, SITE_ROOT, $file["file"]), $preset);
		$image_copy = $image->copy();
		$image_copy->StoredName = pathinfo($file["file"], PATHINFO_BASENAME);
		$image_copy->filterGeneratableCrops();

		$bigtree["crops"] += $image_copy->processCrops();
	}

	$admin->updateResource($_POST["id"], $data);
	$admin->growl("File Manager", "Updated File");

	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/edit/file/".$_POST["id"]."/",
		"return_link" => ADMIN_ROOT."files/folder/".intval($bigtree["commands"][0])."/",
		"errors" => $bigtree["errors"]
	];
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = $cms->cacheUnique("org.bigtreecms.crops", $bigtree["crops"]);
		BigTree::redirect(ADMIN_ROOT."files/crop/");
	} elseif (count($bigtree["errors"])) {
		BigTree::redirect(ADMIN_ROOT."files/error/");
	} else {
		BigTree::redirect(ADMIN_ROOT."files/folder/".$file["folder"]."/");
	}

