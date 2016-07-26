<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$page = new Page(end($bigtree["path"]));
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->unarchive();
	}
	
	Utils::growl("Pages","Restored Page");
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page->Parent."/");
	