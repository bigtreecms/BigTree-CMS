<?php
	// BigTree 4.2.20
	
	// 4.2.17 broke the 404 list to add duplicates a plenty, do 100 per batch to prevent timeouts

	if (empty($_GET["page"])) {
		$total_results = SQL::query("SELECT COUNT(*) AS `count` FROM bigtree_404s WHERE `redirect_url` = '' GROUP BY `broken_url` HAVING `count` > 1")->rows();

		if ($total_results) {
			$total_pages = ceil($total_results / 100);
			
			echo BigTree::json([
				"complete" => false,
				"response" => "Cleaning up 404s...",
				"pages" => $total_pages
			]);
		} else {
			$admin->updateInternalSettingValue("bigtree-internal-revision", 209);

			echo BigTree::json([
				"complete" => true,
				"response" => "Upgraded to BigTree 4.2.20"
			]);
		}

		die();
	}

	$page = intval($_GET["page"]);
	$total_pages = intval($_GET["total_pages"]);
	$q = SQL::query("SELECT COUNT(*) AS `count`, SUM(`requests`) AS `requests`, `id`, `broken_url` FROM bigtree_404s 
					 WHERE `redirect_url` = '' GROUP BY `broken_url` HAVING `count` > 1 LIMIT 0, 100");

	while ($f = $q->fetch()) {
		SQL::query("DELETE FROM bigtree_404s WHERE `broken_url` = ? AND `id` != ?", $f["broken_url"], $f["id"]);
		SQL::update("bigtree_404s", $f["id"], ["requests" => $f["requests"]]);
	}

	if ($page < $total_pages) {
		echo BigTree::json([
			"complete" => false,
			"response" => "Cleaning up 404s: page $page of $total_pages complete."
		]);
	} else {
		$admin->updateInternalSettingValue("bigtree-internal-revision", 209);

		echo BigTree::json([
			"complete" => true,
			"response" => "Upgraded to BigTree 4.2.20"
		]);
	}
