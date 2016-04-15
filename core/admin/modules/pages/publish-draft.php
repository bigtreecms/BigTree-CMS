<?php
	namespace BigTree;
	
	$_POST["id"] = $_GET["draft"];
	include Router::getIncludePath("admin/ajax/dashboard/approve-change.php");

	$admin->growl("Pages","Published Draft");
	Router::redirect(ADMIN_ROOT."pages/revisions/".end($bigtree["commands"])."/");
	