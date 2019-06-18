<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	CSRF::verify();
	
	$group = new CalloutGroup($_GET["id"]);
	$group->delete();

	Admin::growl("Developer","Deleted Callout Group");
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	