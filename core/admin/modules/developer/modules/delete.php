<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$module = new Module(end($bigtree["path"]));
	$module->delete();
	
	Utils::growl("Developer","Deleted Module");
	Router::redirect(DEVELOPER_ROOT."modules/");
	