<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$module = new Module($_GET["id"]);
	$module->delete();
	
	Utils::growl("Developer","Deleted Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
	