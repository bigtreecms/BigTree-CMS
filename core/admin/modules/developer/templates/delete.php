<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$template = new Template(end($bigtree["path"]));
	$template->delete();
	
	Utils::growl("Developer","Deleted Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	