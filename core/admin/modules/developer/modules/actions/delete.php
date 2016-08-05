<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$action = new ModuleAction(end($bigtree["path"]));
	$action->delete();
	
	Utils::growl("Developer","Deleted Action");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	