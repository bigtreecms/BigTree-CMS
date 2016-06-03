<?php
	namespace BigTree;
	
	$admin->deleteSetting(end($bigtree["path"]));
	
	Utils::growl("Developer","Deleted Setting");
	Router::redirect(DEVELOPER_ROOT."settings/");
	