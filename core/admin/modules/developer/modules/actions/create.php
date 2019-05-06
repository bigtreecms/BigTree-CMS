<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	ModuleAction::create(end(Router::$Path), $_POST["name"], $_POST["route"], $_POST["in_nav"], $_POST["class"], $_POST["interface"], $_POST["level"]);
	Utils::growl("Developer", "Created Action");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".end(Router::$Path)."/");
	