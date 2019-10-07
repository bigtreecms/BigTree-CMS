<?php
	namespace BigTree;
	
	header("Content-type: text/json");
	
	// Some API calls don't need to be authenticated first
	$unauthenticated_routes = [
		"users/login",
		"users/forgot-password",
		"users/reset-password",
		"users/two-factor-setup",
		"users/two-factor-auth"
	];
	
	if (!in_array(implode("/", Router::$RoutedPath), $unauthenticated_routes)) {
		API::authenticate();
	}
	
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
	