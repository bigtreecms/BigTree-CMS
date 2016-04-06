<?php
	namespace BigTree;

	BigTree::globalizePOSTVars();

	// Let's see if the ID has already been used.
	if (SQL::exists("bigtree_callouts",$id)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		Router::redirect(DEVELOPER_ROOT."callouts/add/");
	}

	$callout = BigTree\Callout::create($id,$name,$description,$level,$fields,$display_field,$display_default);
	if (!$callout) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		Router::redirect(DEVELOPER_ROOT."callouts/add/");
	}
		
	$admin->growl("Developer","Created Callout");
	
	Router::redirect(DEVELOPER_ROOT."callouts/");
