<?	

	$relative_path = "admin/developer/services/twitter/";
	$mroot = ADMIN_ROOT."developer/services/twitter/";
	$callback = $mroot . "return/";
	
	session_start();
	
	// SET UP API
	$twitterAPI = new BigTreeTwitterAPI();
	
	$configPaths = array(
		"configure",
		"set-config",
		"connect",
		"redirect",
		"return"
	);
	
	if (!$twitterAPI->Connected && !in_array(end($bigtree["path"]), $configPaths)) {
		BigTree::redirect($mroot . "configure/");
	}
	
?>