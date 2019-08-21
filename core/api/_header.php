<?php
	namespace BigTree;
	
	header("Content-type: text/json");
	API::authenticate();
	
	if (!empty($_GET["since"])) {
		$since = date("Y-m-d H:i:s", is_numeric($_GET["since"]) ? $_GET["since"] : strtotime($_GET["since"]));
		define("API_SINCE", $since);
		
		$changed_permissions = SQL::fetchSingle("SELECT COUNT(*) FROM bigtree_audit_trail
    											 WHERE `table` = 'bigtree_users' AND `date` >= ?
    											   AND `type` = 'update' AND `action` = 'permissions changed'
    											   AND `entry` = ?", $since, Auth::user()->ID);
		
		if ($changed_permissions) {
			define("API_PERMISSIONS_CHANGED", true);
		}
	}
	