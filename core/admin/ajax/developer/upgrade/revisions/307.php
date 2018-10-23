<?php
	// BigTree 4.3 -- prerelease

	if (empty($_GET["page"])) {
		// Set region for AWS if it's configured
		$setting_value = BigTreeCMS::getSetting("bigtree-internal-cloud-storage");
	
		if (!empty($setting_value["amazon"]["key"])) {
			$setting_value["amazon"]["region"] = "us-east-1";
			$admin->updateSettingValue("bigtree-internal-cloud-storage", $setting_value);
		}

		$total_results = SQL::fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_resources WHERE is_image = 'on'");

		if ($total_results) {
			$total_pages = ceil($total_results / 10);
			
			echo BigTree::json([
				"complete" => false,
				"response" => "Generating file manager thumbnails...",
				"pages" => $total_pages
			]);
		} else {
			$admin->updateSettingValue("bigtree-internal-revision", 307);

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
	$resources = SQL::fetchAll("SELECT * FROM bigtree_resources WHERE is_image = 'on' ORDER BY id ASC LIMIT $start, 10");
	
	foreach ($resources as $resource) {
		$source = str_replace("{staticroot}", SITE_ROOT, $resource["file"]);

		if (strpos($source, "//") === 0) {
			$source = "https:".$source;
		}

		$thumbs = json_decode($resource["thumbs"], true);
		$basename = pathinfo($source, PATHINFO_BASENAME);
		$extension = pathinfo($source, PATHINFO_EXTENSION);
		$temp_file = SERVER_ROOT."cache/".BigTree::getAvailableFileName(SERVER_ROOT."cache/", "temp.$extension");
		
		// See if this is an earlier 4.3 upload or a 4.2 or lower
		$is_43 = (count(array_filter(array_keys($thumbs), "is_int")) == count($thumbs));

		if ($is_43) {
			$fixed_thumbs = [];

			foreach ($thumbs as $key => $prefix) {
				if (!is_array($prefix)) {
					// Make a copy to get height/width
					if (BigTree::copyFile(BigTree::prefixFile($source, $prefix), $temp_file)) {
						list($width, $height) = getimagesize($temp_file);
						$fixed_thumbs[$prefix] = ["width" => $width, "height" => $height];
					}
				} else {
					$fixed_thumbs[$key] = $prefix;
				}
			}

			$fixed_crops = [];
			$crops = json_decode($resource["crops"], true);

			foreach ($crops as $key => $prefix) {
				if (!is_array($prefix)) {
					// Make a copy to get height/width
					if (BigTree::copyFile(BigTree::prefixFile($source, $prefix), $temp_file)) {
						list($width, $height) = getimagesize($temp_file);
						$fixed_crops[$prefix] = ["width" => $width, "height" => $height];
					}
				} else {
					$fixed_crops[$key] = $prefix;
				}
			}
			
			SQL::update("bigtree_resources", $resource["id"], ["thumbs" => $fixed_thumbs, "crops" => $fixed_crops]);
		} else {
			$image = new BigTreeImage($source);
			$image->centerCrop($temp_file, 100, 100);
			
			if ($resource["location"] == "local") {
				$local_storage->store($temp_file, $basename, "files/resources/list-preview/");
			} else {
				$storage->store($temp_file, $basename, "files/resources/list-preview/");
			}
			
			$fixed_thumbs = [];
			
			foreach ($thumbs as $prefix => $location) {
				// Make a copy to get height/width
				if (BigTree::copyFile(BigTree::prefixFile($source, $prefix), $temp_file)) {
					list($width, $height) = getimagesize($temp_file);
					$fixed_thumbs[$prefix] = ["width" => $width, "height" => $height];
				}
			}
			
			SQL::update("bigtree_resources", $resource["id"], ["thumbs" => $fixed_thumbs]);
		}
		
		@unlink($temp_file);
	}

	if ($page == $total_pages) {
		$admin->updateSettingValue("bigtree-internal-revision", 307);

		echo BigTree::json([
			"complete" => false,
			"response" => "Generating file manager thumbnails: page $page of $total_pages"
		]);
	} else {
		echo BigTree::json([
			"complete" => true,
			"response" => "Upgrading database to 4.3 revision 8"
		]);
	}
