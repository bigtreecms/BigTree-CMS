<?php
	namespace BigTree;
	
	$admin->deletePackage($bigtree["commands"][0]);
	$admin->growl("Developer","Uninstalled Package");

	Router::redirect(DEVELOPER_ROOT."packages/");
	