<?	

	$relative_path = "admin/developer/services/youtube/";
	$mroot = ADMIN_ROOT."developer/services/youtube/";
	$callback = $mroot . "return/";
	
	session_start();
	
	// SET UP API
	$youtubeAPI = new BigTreeYouTubeAPI();
	
	$configPaths = array(
		"configure",
		"set-config",
		"connect",
		"redirect",
		"return"
	);
	
	if (!$youtubeAPI->Connected && !in_array(end($bigtree["path"]), $configPaths)) {
		BigTree::redirect($mroot . "configure/");
	}
	
?>