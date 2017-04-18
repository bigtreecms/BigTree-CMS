<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$action = new ModuleAction($_GET["id"]);
	$action->delete();
	
	Utils::growl("Developer","Deleted Action");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$action->Module."/");
	