<?php
	namespace BigTree;
	
	$page = end($bigtree["path"]);
	
	$page_data = $cms->getPendingPage($page);
	$admin->deletePage($page);
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page_data["parent"]."/");
	