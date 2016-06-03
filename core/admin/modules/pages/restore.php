<?php
	namespace BigTree;
	
	$id = end($bigtree["path"]);
	$page = $cms->getPage($id,false);
	$access = $admin->unarchivePage($id);

	Utils::growl("Pages","Restored Page");
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
	