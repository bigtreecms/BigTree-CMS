<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$group = new CalloutGroup(Router::$Command, ["BigTree\Admin", "catch404"]);
	$group->update($_POST["name"],$_POST["callouts"]);

	Admin::growl("Developer","Updated Callout Group");
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	