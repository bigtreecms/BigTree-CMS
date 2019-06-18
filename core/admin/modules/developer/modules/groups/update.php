<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$group = new ModuleGroup(end(Router::$Path));
	$group->update($_POST["name"]);

	Admin::growl("Developer","Updated Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	