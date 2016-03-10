<?php
	BigTree::globalizePOSTVars();

	// Let's see if the ID has already been used.
	if (BigTreeCMS::$DB->exists("bigtree_callouts",$id)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		BigTree::redirect(DEVELOPER_ROOT."callouts/add/");
	}

	$callout = BigTree\Callout::create($id,$name,$description,$level,$fields,$display_field,$display_default);
	if (!$callout) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		BigTree::redirect(DEVELOPER_ROOT."callouts/add/");
	}
		
	$admin->growl("Developer","Created Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");
