<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	CSRF::verify();
	
	$page = new Page($_GET["id"]);
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->delete();
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page->Parent."/");
	