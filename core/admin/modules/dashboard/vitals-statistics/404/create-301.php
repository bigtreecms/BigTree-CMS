<?php
	$admin->verifyCSRFToken();
	
	if (is_array($bigtree["config"]["sites"]) && count($bigtree["config"]["sites"]) > 1) {
		$admin->create301($_POST["from"], $_POST["to"], $_POST["site_key"]);
	} else {
		$admin->create301($_POST["from"], $_POST["to"]);
	}

	$admin->growl("301 Redirects","Created Redirect");
	BigTree::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
