<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$extension = new Extension($_GET["id"]);
	$extension->delete();
	
	Utils::growl("Developer","Uninstalled Package");
	Router::redirect(DEVELOPER_ROOT."packages/");
	