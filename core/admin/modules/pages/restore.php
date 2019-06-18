<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$page = new Page($_GET["id"]);
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->unarchive();
	}
	
	Admin::growl("Pages","Restored Page");
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page->Parent."/");
	