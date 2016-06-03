<?php
	namespace BigTree;
	
	$admin->deleteFeed(end($bigtree["commands"]));

	Utils::growl("Developer","Deleted Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	