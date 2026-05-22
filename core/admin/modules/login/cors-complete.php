<?php
	$key = $_GET["key"] ?? "";
	$cache_data = BigTreeCMS::cacheGet("org.bigtreecms.login-session", $key) ?? [];
	
	BigTreeCMS::cacheDelete("org.bigtreecms.login-session", $key);
	BigTree::redirect(!empty($cache_data["login_redirect"]) ? $cache_data["login_redirect"] : ADMIN_ROOT);
