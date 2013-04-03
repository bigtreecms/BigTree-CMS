<?	

	$relative_path = "admin/developer/services/googleplus/";
	$mroot = ADMIN_ROOT."developer/services/googleplus/";
	$callback = $mroot . "return/";
	
	session_start();
	
	// SET UP API
	$googleplusAPI = new BigTreeGooglePlusAPI();
	
	$configPaths = array(
		"configure",
		"set-config",
		"connect",
		"redirect",
		"return"
	);
	
	if (!$googleplusAPI->Connected && !in_array(end($bigtree["path"]), $configPaths)) {
		BigTree::redirect($mroot . "configure/");
	}
	
?>