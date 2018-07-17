<?php
	$site = $cms->getPage(0, false);
	$bigtree["layout"] = "login";
	$login_root = $bigtree["config"]["force_secure_login"] ? str_replace("http://","https://",ADMIN_ROOT)."login/" : ADMIN_ROOT."login/";

	$last_path = $bigtree["path"][count($bigtree["path"]) - 1];
	
	// Check if we're forcing HTTPS
	if ($bigtree["config"]["force_secure_login"] && !BigTree::getIsSSL() && $last_path != "cors") {
		BigTree::redirect(str_replace("http://","https://",ADMIN_ROOT)."login/");
	}
