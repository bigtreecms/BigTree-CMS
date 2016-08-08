<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$setting = new Setting(end($bigtree["path"]));
	$setting->delete();
	
	Utils::growl("Developer","Deleted Setting");
	Router::redirect(DEVELOPER_ROOT."settings/");
	