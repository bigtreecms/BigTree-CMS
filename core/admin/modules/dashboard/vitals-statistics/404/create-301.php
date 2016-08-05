<?php
	namespace BigTree;
	
	Redirect::create($_POST["from"], $_POST["to"]);
	Utils::growl("301 Redirects", "Created Redirect");
	Router::redirect(ADMIN_ROOT."dashboard/vitals-statistics/404/301/");
