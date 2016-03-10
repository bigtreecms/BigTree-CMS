<?php
	BigTree\Callout::delete(end($bigtree["path"]));
	
	$admin->growl("Developer","Deleted Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");