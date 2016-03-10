<?php
	$page = new BigTree\Page(end($bigtree["path"]));
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->archive();
		$admin->growl("Pages","Archived Page");
	}

	BigTree::redirect(ADMIN_ROOT."pages/view-tree/".$page["parent"]."/");