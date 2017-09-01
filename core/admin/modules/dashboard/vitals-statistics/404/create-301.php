<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
		Redirect::create($_POST["from"], $_POST["to"], $_POST["site_key"]);
	} else {
		Redirect::create($_POST["from"], $_POST["to"]);
	}
	
	Utils::growl("301 Redirects", "Created Redirect");
	Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
