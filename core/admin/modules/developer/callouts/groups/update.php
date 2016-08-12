<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$group = new CalloutGroup(end($bigtree["path"]));
	$group->update($_POST["name"],$_POST["callouts"]);

	Utils::growl("Developer","Updated Callout Group");
	Router::redirect(DEVELOPER_ROOT."callouts/groups/");
	