<?php
	namespace BigTree;
	
	$_POST["id"] = $_GET["draft"];
	Router::includeFile("admin/ajax/dashboard/approve-change.php");

	$admin->growl("Pages","Published Draft");
	Router::redirect(ADMIN_ROOT."pages/revisions/".end($bigtree["commands"])."/");
	