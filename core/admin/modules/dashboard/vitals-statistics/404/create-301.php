<?php
	namespace BigTree;

	$admin->create301($_POST["from"],$_POST["to"]);
	$admin->growl("301 Redirects","Created Redirect");
	
	Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
