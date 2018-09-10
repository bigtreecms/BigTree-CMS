<?php
	$cache_data = BigTreeCMS::cacheGet("org.bigtreecms.login-session", $_GET["key"]);
	BigTreeCMS::cacheDelete("org.bigtreecms.login-session", $_GET["key"]);
	BigTree::redirect($cache_data["login_redirect"] ?: ADMIN_ROOT);
