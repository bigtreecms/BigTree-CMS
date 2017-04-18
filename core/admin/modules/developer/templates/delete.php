<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$template = new Template($_GET["id"]);
	$template->delete();
	
	Utils::growl("Developer","Deleted Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	