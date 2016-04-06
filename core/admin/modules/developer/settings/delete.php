<?php
	namespace BigTree;
	
	$admin->deleteSetting(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Setting");
	Router::redirect(DEVELOPER_ROOT."settings/");
	