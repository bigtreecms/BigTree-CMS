<?php
	namespace BigTree;
	
	$extension = new Extension($bigtree["commands"][0]);
	$extension->delete();

	$admin->growl("Developer","Uninstalled Extension");
	Router::redirect(DEVELOPER_ROOT."extensions/");
	