<?php
	namespace BigTree;
	
	$admin->deletePackage($bigtree["commands"][0]);
	Utils::growl("Developer","Uninstalled Package");

	Router::redirect(DEVELOPER_ROOT."packages/");
	