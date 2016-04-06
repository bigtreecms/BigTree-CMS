<?php
	namespace BigTree;
	
	$page = new Page(end($bigtree["path"]));
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->archive();
		$admin->growl("Pages","Archived Page");
	}

	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");
	