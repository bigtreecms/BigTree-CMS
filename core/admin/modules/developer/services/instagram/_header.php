<?	

	$relative_path = "admin/developer/services/instagram/";
	$mroot = ADMIN_ROOT."developer/services/instagram/";
	$callback = $mroot . "return/";
	
	session_start();
	include BigTree::path("inc/lib/instagram/instagram.class.php");
	
	$settings = $cms->getSetting("bigtree-internal-instagram-api");
	$id = isset($settings["id"]) ? $settings["id"] : "";
	$secret = isset($settings["secret"]) ? $settings["secret"] : "";
	$token = isset($settings["token"]) ? $settings["token"] : "";
	
	
	if ((!$id || !$secret || !$token) && end($bigtree["path"]) != "configure" && end($bigtree["path"]) != "set-config" && end($bigtree["path"]) != "connect" && end($bigtree["path"]) != "redirect" && end($bigtree["path"]) != "return") {
		BigTree::redirect($mroot . "configure/");
	}
	
?>