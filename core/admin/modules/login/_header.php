<?php
	namespace BigTree;
	
	Router::setLayout("login");

	$site = new Page(0, null, false);
	$last_path = Router::$Path[count(Router::$Path) - 1];
	
	if (Router::$Config["force_secure_login"]) {
		$login_root = str_replace("http://", "https://", ADMIN_ROOT)."login/";
		
		if (!Router::getIsSSL() && $last_path != "cors") {
			Router::redirect($login_root);
			
		}
	} else {
		$login_root = ADMIN_ROOT."login/";
	}
	