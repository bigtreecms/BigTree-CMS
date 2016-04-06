<?php
	namespace BigTree;
	
	$admin->deleteFeed(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	