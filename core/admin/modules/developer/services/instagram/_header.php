<?	

	$relative_path = "admin/developer/services/instagram/";
	$mroot = ADMIN_ROOT."developer/services/instagram/";
	$callback = $mroot . "return/";
	
	session_start();
	
	// SET UP API
	$instagramAPI = new BigTreeInstagramAPI();
	
	$configPaths = array(
		"configure",
		"set-config",
		"connect",
		"redirect",
		"return"
	);
	
	if (!$instagramAPI->Connected && !in_array(end($bigtree["path"]), $configPaths)) {
		BigTree::redirect($mroot . "configure/");
	}
	
?>