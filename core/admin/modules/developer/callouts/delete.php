<?php
	namespace BigTree;

	$callout = new Callout(end($bigtree["path"]));
	$callout->delete();
	
	$admin->growl("Developer","Deleted Callout");
	
	Router::redirect(DEVELOPER_ROOT."callouts/");