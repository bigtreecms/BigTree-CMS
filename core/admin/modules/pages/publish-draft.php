<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$_POST["id"] = $_GET["draft"];
	include Router::getIncludePath("admin/ajax/dashboard/approve-change.php");

	Admin::growl("Pages","Published Draft");
	Router::redirect(ADMIN_ROOT."pages/revisions/".end(Router::$Commands)."/");
	