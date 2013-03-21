<?	

	$relative_path = "admin/developer/services/twitter/";
	$mroot = ADMIN_ROOT."developer/services/twitter/";
	$callback = $mroot . "return/";
	
	session_start();
	include BigTree::path("inc/lib/twitter/twitteroauth.php");
	
	$settings = $cms->getSetting("bigtree-internal-twitter-api");
	$key = isset($settings["key"]) ? $settings["key"] : "";
	$secret = isset($settings["secret"]) ? $settings["secret"] : "";
	$token = isset($settings["token"]) ? $settings["token"] : "";
	
	
	if ((!$key || !$secret || !$token) && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-config" && end($bigtree["path"]) != "connect" && end($bigtree["path"]) != "redirect" && end($bigtree["path"]) != "return") {
		BigTree::redirect($mroot . "configure/");
	}
	
?>