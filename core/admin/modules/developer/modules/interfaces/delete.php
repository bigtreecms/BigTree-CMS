<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$interface = new ModuleInterface($_GET["id"]);
	$interface->delete();

	Admin::growl("Developer","Deleted Interface");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$interface->Module."/");
	