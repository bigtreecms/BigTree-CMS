<?
	$site = $cms->getPage(0);
	$layout = "login";
	
	// Check if we're forcing HTTPS
	if ($config["force_secure_login"] && $_SERVER["SERVER_PORT"] == 80) {
		header("Location: ".str_replace("http://","https://",$admin_root)."login/");
		die();
	}
?>