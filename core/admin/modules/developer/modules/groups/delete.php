<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$group = new ModuleGroup(end($bigtree["path"]));
	$group->delete();
	
	Utils::growl("Developer","Deleted Module Group");
	Router::redirect(DEVELOPER_ROOT."modules/groups/");
	