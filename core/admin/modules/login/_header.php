<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$bigtree["layout"] = "login";
	$site = new Page(0, false);
	$login_root = $bigtree["config"]["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT)."login/" : ADMIN_ROOT."login/";
	$last_path = $bigtree["path"][count($bigtree["path"]) - 1];
	
	// Check if we're forcing HTTPS
	if ($bigtree["config"]["force_secure_login"] && !Router::getIsSSL() && $last_path != "cors") {
		Router::redirect(str_replace("http://", "https://", ADMIN_ROOT)."login/");
	}
	