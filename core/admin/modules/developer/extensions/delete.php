<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$extension = new Extension($_GET["id"]);
	$extension->delete();
	
	Admin::growl("Developer", "Uninstalled Extension");
	Router::redirect(DEVELOPER_ROOT."extensions/");
	