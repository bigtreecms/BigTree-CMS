<?php
	namespace BigTree;

	// Defaults
	$id = $name = $description = $display_field = $display_default = "";
	$level = 0;
	$fields = array();

	Globalize::POST();

	// Let's see if the ID has already been used.
	if (SQL::exists("bigtree_callouts",$id)) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		
		Router::redirect(DEVELOPER_ROOT."callouts/add/");
	}

	$callout = Callout::create($id,$name,$description,$level,$fields,$display_field,$display_default);
	
	if (!$callout) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		
		Router::redirect(DEVELOPER_ROOT."callouts/add/");
	}
		
	Utils::growl("Developer","Created Callout");
	
	Router::redirect(DEVELOPER_ROOT."callouts/");
