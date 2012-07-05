<?
	$site = $cms->getPage(0);
	$bigtree["layout"] = "login";
	
	// Check if we're forcing HTTPS
	if ($bigtree["config"]["force_secure_login"] && $_SERVER["SERVER_PORT"] == 80) {
		BigTree::redirect(str_replace("http://","https://",ADMIN_ROOT)."login/");
	}
?>