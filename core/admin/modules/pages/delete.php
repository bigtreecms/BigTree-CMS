<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	if ($page_id->UserAccessLevel == "p" && $page_id->UserCanModifyChildren) {
		$page_id->delete();
	}
	
	Router::redirect(ADMIN_ROOT."pages/view-tree/".$page_id->Parent."/");
	