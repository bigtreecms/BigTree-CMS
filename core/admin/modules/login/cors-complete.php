<?php
	namespace BigTree;
	
	$cache_data = Cache::get("org.bigtreecms.login-session", $_GET["key"]);
	
	Cache::delete("org.bigtreecms.login-session", $_GET["key"]);
	Router::redirect($cache_data["login_redirect"] ?: ADMIN_ROOT);
	