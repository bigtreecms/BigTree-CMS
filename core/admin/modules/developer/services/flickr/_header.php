<?	

	$relative_path = "admin/developer/services/flickr/";
	$mroot = ADMIN_ROOT."developer/services/flickr/";
	$callback = $mroot . "return/";
	
	session_start();
	
	// SET UP API
	$flickrAPI = new BigTreeFlickrAPI();
	
	$configPaths = array(
		"configure",
		"set-config",
		"connect",
		"redirect",
		"return"
	);
	
	if (!$flickrAPI->Connected && !in_array(end($bigtree["path"]), $configPaths)) {
		BigTree::redirect($mroot . "configure/");
	}
	
?>