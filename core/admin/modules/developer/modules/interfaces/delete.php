<?php
	namespace BigTree;
	
	$interface = new ModuleInterface(end($bigtree["commands"]));
	$interface->delete();

	$admin->growl("Developer","Deleted Interface");
	Router::redirect(DEVELOPER_ROOT."modules/edit/".$_GET["module"]."/");
	