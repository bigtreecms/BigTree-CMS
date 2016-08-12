<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$interface = new ModuleInterface(end($bigtree["commands"]));
	$interface->delete();

	Utils::growl("Developer","Deleted Interface");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	