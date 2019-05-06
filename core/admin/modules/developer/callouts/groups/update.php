<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();

	$group = new CalloutGroup(end(Router::$Path));
	$group->update($_POST["name"],$_POST["callouts"]);

	Utils::growl("Developer","Updated Callout Group");
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	