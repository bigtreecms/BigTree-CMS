<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	ModuleAction::create(Router::$Command, $_POST["name"], $_POST["route"], $_POST["in_nav"], $_POST["class"], $_POST["interface"], $_POST["level"]);
	Admin::growl("Developer", "Created Action");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".Router::$Command."/");
	