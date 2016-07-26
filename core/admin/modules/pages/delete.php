<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	if ($page->UserAccessLevel == "p" && $page->UserCanModifyChildren) {
		$page->delete();
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page->Parent."/");
	