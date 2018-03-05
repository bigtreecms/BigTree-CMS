<?php
	namespace BigTree;
	
	$bigtree["layout"] = "login";
	$site = new Page(0, false);
	$login_root = $bigtree["config"]["force_secure_login"] ? str_replace("http://", "https://", ADMIN_ROOT)."login/" : ADMIN_ROOT."login/";
	
	// Check if we're forcing HTTPS
	if ($bigtree["config"]["force_secure_login"] && !Router::getIsSSL()) {
		Router::redirect(str_replace("http://", "https://", ADMIN_ROOT)."login/");
	}
	