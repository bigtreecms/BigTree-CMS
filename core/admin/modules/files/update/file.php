<?php
	namespace BigTree;
	
	/**
	 * @global $crop_prefixes
	 * @global $thumb_prefixes
	 */
	
	CSRF::verify();
	
	if (!Resource::exists($_POST["id"])) {
		Auth::stop("File does not exist.");
	}
	
	$file = new Resource($_POST["id"]);
	
	if ($file->UserAccessLevel != "p") {
		Auth::stop("Access denied.");
	}
	
	$file->Name = $_POST["name"];
	$file->Metadata = [];
	
	if (Auth::user()->Level) {
		$file->Folder = $_POST["folder"];
	}
	
	$metadata = DB::get("config", "file-metadata");
	$preset = null;
	
	if ($file->IsImage) {
		$meta_fields = $metadata["image"];
		$settings = DB::get("config", "media-settings");
		$preset = $settings["presets"]["default"];
		$preset["directory"] = "files/resources/";
	} elseif ($file->IsVideo) {
		$meta_fields = $metadata["video"];
	} else {
		$meta_fields = $metadata["file"];
	}
	
	$bigtree["crops"] = [];
	
	if (is_array($meta_fields) && count($meta_fields)) {
		$bigtree["post_data"] = $_POST;
		$bigtree["file_data"] = Field::getParsedFiles[];
		
		foreach ($meta_fields as $meta) {
			$field = new Field([
				"type" => $meta["type"],
				"title" => $meta["title"],
				"key" => "metadata[".$meta["id"]."]",
				"settings" => $meta["settings"],
				"ignore" => false,
				"input" => $_POST["metadata"][$meta["id"]],
				"file_input" => $bigtree["file_data"]["metadata"][$meta["id"]]
			]);
			
			$output = $field->process();
			
			if (!is_null($output)) {
				$file->Metadata[$meta["id"]] = $output;
			}
		}
	}
	
	if (!empty($_FILES["file"]["tmp_name"])) {
		$storage = new Storage;
		$file_name = pathinfo($file->File, PATHINFO_BASENAME);
		
		if ($file->IsImage) {
			$image = new Image($_FILES["file"]["tmp_name"], $preset);
			$image->filterGeneratableCrops();
			
			// Get updated crop/thumb arrays
			include Router::getIncludePath("admin/modules/files/process/_resource-prefixes.php");
			
			$field = new Field([
				"title" => $file->Name,
				"file_input" => [
					"tmp_name" => $image->File,
					"name" => $file_name,
					"error" => 0
				],
				"settings" => [
					"directory" => "files/resources/",
					"preset" => "default"
				]
			]);
			
			$file->FileLastUpdated = "NOW()";
			$file->Width= $image->Width;
			$file->Height = $image->Height;
			$file->Crops = $crop_prefixes;
			$file->Thumbs = $thumb_prefixes;
			$file->Size = filesize($image->File);
			
			if ($field->processImageUpload(true)) {
				// Remove any crops that no longer work with the new image
				foreach ($file->Crops as $prefix => $size) {
					if (!isset($size["crops"][$prefix])) {
						$storage->delete(FileSystem::getPrefixedFile($file->File, $prefix));
					}
				}
			}
		} elseif (!$file->IsVideo) {
			$file->Size = filesize($_FILES["file"]["tmp_name"]);
			$file->FileLastUpdated = "NOW()";
			$storage->replace($_FILES["file"]["tmp_name"], $file_name, "files/resources/");
		}
	} elseif (!empty($_POST["__file_recrop__"])) {
		// User has asked for a re-crop
		$image = new Image(str_replace(STATIC_ROOT, SITE_ROOT, $file->File), $preset);
		$image_copy = $image->copy();
		$image_copy->StoredName = pathinfo($file->File, PATHINFO_BASENAME);
		$image_copy->filterGeneratableCrops();
		
		$bigtree["crops"] += $image_copy->processCrops();
	}
	
	$file->save();
	Utils::growl("File Manager", "Updated File");
	
	$_SESSION["bigtree_admin"]["form_data"] = [
		"edit_link" => ADMIN_ROOT."files/edit/file/".$_POST["id"]."/",
		"return_link" => ADMIN_ROOT."files/folder/".$file->Folder."/",
		"errors" => $bigtree["errors"]
	];
	
	if (count($bigtree["crops"])) {
		$_SESSION["bigtree_admin"]["form_data"]["crop_key"] = Cache::putUnique("org.bigtreecms.crops", $bigtree["crops"]);
		Router::redirect(ADMIN_ROOT."files/crop/");
	} elseif (count($bigtree["errors"])) {
		Router::redirect(ADMIN_ROOT."files/error/");
	} else {
		Router::redirect(ADMIN_ROOT."files/folder/".$file->Folder."/");
	}

