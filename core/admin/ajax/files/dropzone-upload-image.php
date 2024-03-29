<?php
	/**
	 * @global BigTreeAdmin $admin
	 */
	
	if (!empty($admin->POSTError)) {
		http_response_code(406);
		header("Content-type: text/plain");
		echo "The uploaded image exceeded the server's maximum upload size. Please try a smaller image.";
		die();
	}

	$admin->verifyCSRFToken();
	$folder = intval($_POST["folder"]);
	$permission = $admin->getResourceFolderPermission($folder);

	if ($permission != "p") {
		http_response_code(403);
		header("Content-type: text/plain");
		echo "You do not have permission to create content in this folder.";
		die();
	}
	
	$settings = BigTreeJSONDB::get("config", "media-settings");
	$preset = $settings["presets"]["default"];
	$preset["directory"] = "files/resources/";

	// Add preview crop
	if (empty($preset["center_crops"]) || !is_array($preset["center_crops"])) {
		$preset["center_crops"] = [];
	}

	$preset["center_crops"][] = [
		"prefix" => "list-preview/",
		"width" => 100,
		"height" => 100
	];

	$image = new BigTreeImage($_FILES["file"]["tmp_name"], $preset);
		
	if (!empty($image->Error)) {
		http_response_code(406);
		header("Content-type: text/plain");
		echo $image->Error;

		$image->destroy();
		die();
	}

	$image->store($_FILES["file"]["name"]);
	$image->filterGeneratableCrops();

	// If we no longer have our list-preview because this image is tiny...
	$has_list_preview = false;

	foreach ($image->Settings["center_crops"] as $crop_index => $crop) {
		if ($crop["prefix"] == "list-preview/") {
			$has_list_preview = true;
		}
	}

	if (!$has_list_preview) {
		// Choose the smallest side
		$size = ($image->Width < $image->Height) ? $image->Width : $image->Height;

		$image->Settings["center_crops"][] = [
			"prefix" => "list-preview/",
			"width" => $size,
			"height" => $size
		];
	}

	$image->processThumbnails();
	$image->processCenterCrops();
	$crops = $image->processCrops();

	include BigTree::path("admin/modules/files/process/_resource-prefixes.php");

	// Remove the list preview as a user option
	unset($crop_prefixes["list-preview/"]);
	
	$resource_id = $admin->createResource($folder, $image->StoredFile, $image->StoredName, "image", $crop_prefixes, $thumb_prefixes);	

	echo BigTree::json([
		"resource" => $resource_id,
		"crops" => $crops
	]);
