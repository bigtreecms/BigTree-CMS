<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	ModuleAction::create(end($bigtree["path"]), $_POST["name"], $_POST["route"], $_POST["in_nav"], $_POST["class"], $_POST["interface"], $_POST["level"]);
	Utils::growl("Developer", "Created Action");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".end($bigtree["path"])."/");
	