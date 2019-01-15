<?php
	// BigTree 4.3 -- prerelease

	if (empty($_GET["page"])) {
		// Set region for AWS if it's configured
		$setting_value = BigTreeCMS::getSetting("bigtree-internal-cloud-storage");
	
		if (!empty($setting_value["amazon"]["key"])) {
			$setting_value["amazon"]["region"] = "us-east-1";
			$admin->updateInternalSettingValue("bigtree-internal-cloud-storage", $setting_value, true);
		}
		
		SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `image_fixed` CHAR(2) NOT NULL AFTER `is_image`");

		$total_results = SQL::fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_resources WHERE is_image = 'on' AND image_fixed = '' AND crops = ''");

		if ($total_results) {
			$total_pages = ceil($total_results / 10);
			
			echo BigTree::json([
				"complete" => false,
				"response" => "Updating file manager data...",
				"pages" => $total_pages
			]);
		} else {
			$admin->updateInternalSettingValue("bigtree-internal-revision", 307);

			echo BigTree::json([
				"complete" => true,
				"response" => "Upgrading database to 4.3 revision 8"
			]);
		}

		die();
	}

	$storage = new BigTreeStorage;
	$local_storage = new BigTreeStorage(true);
	$page = intval($_GET["page"]);
	$total_pages = intval($_GET["total_pages"]);
	$start = ($page - 1) * 10;
	
	// Generate list preview images for the new file manager and fix the thumbs array
	$resources = SQL::fetchAll("SELECT * FROM bigtree_resources WHERE is_image = 'on' AND image_fixed = '' ORDER BY id ASC LIMIT 10");
	
	foreach ($resources as $resource) {
		$source = str_replace("{staticroot}", SITE_ROOT, $resource["file"]);

		if (strpos($source, "//") === 0) {
			$source = "https:".$source;
		}

		$thumbs = json_decode($resource["thumbs"], true);
		$basename = pathinfo($source, PATHINFO_BASENAME);
		$extension = pathinfo($source, PATHINFO_EXTENSION);
		$temp_file = SERVER_ROOT."cache/".BigTree::getAvailableFileName(SERVER_ROOT."cache/", "temp.$extension");
		
		// If we can't make a copy, it's likely a 404, don't delete it but don't treat it as an image anymore
		if (!BigTree::copyFile($source, $temp_file)) {
			SQL::update("bigtree_resources", $resource["id"], ["is_image" => ""]);
			
			continue;			
		}

		$image = new BigTreeImage($temp_file);
		
		// Turn bad image uploads into regular files rather than treat them as images
		if ($image->Error) {
			SQL::update("bigtree_resources", $resource["id"], ["is_image" => ""]);
			@unlink($temp_file);
			
			continue;
		}
		
		$temp_crop = SERVER_ROOT."cache/".BigTree::getAvailableFileName(SERVER_ROOT."cache/", "temp.$extension");
		$image->centerCrop($temp_crop, 100, 100);
		
		if ($resource["location"] == "local") {
			$local_storage->replace($temp_crop, $basename, "files/resources/list-preview/");
		} else {
			$storage->replace($temp_crop, $basename, "files/resources/list-preview/");
		}
		
		$fixed_thumbs = [];
		
		if (is_array($thumbs)) {
			foreach ($thumbs as $location) {
				$thumb_base_name = pathinfo($location, PATHINFO_BASENAME);
				$prefix = str_replace($basename, "", $thumb_base_name);
				
				if ($prefix == "bigtree_list_thumb_" || $prefix == "bigtree_detail_thumb_") {
					continue;
				}
				
				// Make a copy to get height/width
				if (BigTree::copyFile($location, $temp_file)) {
					list($width, $height) = getimagesize($temp_file);
					$fixed_thumbs[$prefix] = ["width" => $width, "height" => $height];
				}
			}
		}
		
		SQL::update("bigtree_resources", $resource["id"], ["thumbs" => $fixed_thumbs, "image_fixed" => "on"]);
		
		@unlink($temp_file);
	}

	if ($page < $total_pages) {
		echo BigTree::json([
			"complete" => false,
			"response" => "Updating file manager data: page $page of $total_pages complete."
		]);
	} else {
		$admin->updateInternalSettingValue("bigtree-internal-revision", 307);
		SQL::query("ALTER TABLE `bigtree_resources` DROP COLUMN `image_fixed`");

		echo BigTree::json([
			"complete" => true,
			"response" => "Upgrading database to 4.3 revision 8"
		]);
	}
