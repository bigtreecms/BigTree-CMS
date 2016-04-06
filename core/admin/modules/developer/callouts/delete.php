<?php
	namespace BigTree;

	Callout::delete(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Callout");
	
	Router::redirect(DEVELOPER_ROOT."callouts/");