<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$group = new ModuleGroup(end($bigtree["path"]));
	$group->update($_POST["name"]);

	Utils::growl("Developer","Updated Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	