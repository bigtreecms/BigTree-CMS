<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$setting = new Setting($_GET["id"]);
	$setting->delete();
	
	Utils::growl("Developer","Deleted Setting");
	Router::redirect(DEVELOPER_ROOT."settings/");
	