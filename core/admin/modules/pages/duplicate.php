<?php
	namespace BigTree;
	
	/**
	 * @global Page $page
	 */
	
	if (empty($page->Parent) || $page->Parent === -1 ||
		$page->UserAccessLevel != "p" ||
		$page->ParentPage->UserAccessLevel != "p"
	) {
		Auth::stop("Access denied.");
	}
	
	$pending = $page->copyToPending(Text::translate(" (Copy)"));

	Utils::growl("Pages", "Duplicated Page");
	Router::redirect(ADMIN_ROOT."pages/edit/p".$pending->ID."/");
	
