<?php
	// BigTree 4.4.14

	if (empty($_GET["page"])) {
		$total_results = SQL::fetchSingle("SELECT COUNT(*) AS `count` FROM bigtree_resources WHERE is_image != 'on' AND size = 0");

		if ($total_results) {
			SQL::query("ALTER TABLE `bigtree_resources` ADD COLUMN `size_calculated` CHAR(2) NULL");

			$total_pages = ceil($total_results / 50);
			
			echo BigTree::json([
				"complete" => false,
				"response" => "Updating file manager data...",
				"pages" => $total_pages
			]);
		} else {
			$admin->updateInternalSettingValue("bigtree-internal-revision", 410);

			echo BigTree::json([
				"complete" => true,
				"response" => "Upgrading database to 4.4.14"
			]);
		}

		die();
	}

	$page = intval($_GET["page"]);
	$total_pages = intval($_GET["total_pages"]);
	$start = ($page - 1) * 50;
	
	$resources = SQL::fetchAll("SELECT * FROM bigtree_resources WHERE is_image != 'on' AND size_calculated IS NULL LIMIT 50");
	
	foreach ($resources as $resource) {
		if ($resource["location"] == "local") {
			$size = filesize(str_replace(["{staticroot}", "{wwwroot}"], SITE_ROOT, $resource["file"]));
		} else {
			$url = (substr($resource["file"], 0, 2) === "//") ? "https:".$resource["file"] : $resource["file"];
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_NOBODY, true);
			curl_exec($ch);
			$size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		}

		SQL::update("bigtree_resources", $resource["id"], ["size" => $size, "size_calculated" => "on"]);
	}

	if ($page < $total_pages) {
		echo BigTree::json([
			"complete" => false,
			"response" => "Updating file manager data: page $page of $total_pages complete."
		]);
	} else {
		SQL::query("ALTER TABLE `bigtree_resources` DROP COLUMN `size_calculated`");
		$admin->updateInternalSettingValue("bigtree-internal-revision", 410);

		echo BigTree::json([
			"complete" => true,
			"response" => "Upgrading database to 4.4.14"
		]);
	}
