<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$extension = new Extension($bigtree["commands"][0]);
	$extension->delete();
	
	Utils::growl("Developer", "Uninstalled Extension");
	Router::redirect(DEVELOPER_ROOT."extensions/");
	