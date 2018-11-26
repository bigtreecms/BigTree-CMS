<?php
	// 4.2.17 broke the 404 list to add duplicates a plenty, updating those that have redirect URLs first so we don't delete them by accident
	$q = sqlquery("SELECT COUNT(*) AS `count`, SUM(`requests`) AS `requests`, `id`, `broken_url` FROM bigtree_404s 
				   WHERE `redirect_url` != '' GROUP BY `broken_url` HAVING `count` > 1");

	while ($f = sqlfetch($q)) {
		sqlquery("DELETE FROM bigtree_404s WHERE `broken_url` = '".sqlescape($f["broken_url"])."' AND `id` != '".$f["id"]."'");
		sqlquery("UPDATE bigtree_404s SET `requests` = '".$f["requests"]."' WHERE `id` = '".$f["id"]."'");
	}

	$admin->updateInternalSettingValue("bigtree-internal-revision", 208);

	echo BigTree::json([
		"complete" => true,
		"response" => "Cleaned up 404 data with redirects."
	]);
	