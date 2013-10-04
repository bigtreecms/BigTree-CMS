<?
	$site = $cms->getPage(0);
	$bigtree["layout"] = "login";
	$login_root = $bigtree["config"]["force_secure_login"] ? str_replace("http://","https://",ADMIN_ROOT)."login/" : ADMIN_ROOT."login/";
	
	// Check if we're forcing HTTPS
	if ($bigtree["config"]["force_secure_login"] && $_SERVER["SERVER_PORT"] == 80) {
		BigTree::redirect(str_replace("http://","https://",ADMIN_ROOT)."login/");
	}
?>