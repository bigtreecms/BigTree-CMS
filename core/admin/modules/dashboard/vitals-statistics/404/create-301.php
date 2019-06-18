<?php
	namespace BigTree;
	
	CSRF::verify();
	
	if (is_array(Router::$Config["sites"]) && count(Router::$Config["sites"]) > 1) {
		Redirect::create($_POST["from"], $_POST["to"], $_POST["site_key"]);
	} else {
		Redirect::create($_POST["from"], $_POST["to"]);
	}
	
	Admin::growl("301 Redirects", "Created Redirect");
	Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
