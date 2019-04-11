<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global Page $page
	 */
	
	$bigtree["resources"] = $page->Resources;
	
	if ($page->ID === -1) {
		Auth::stop("You have reached an invalid edit page.");
	}
	
	// Show the properties section
	include Router::getIncludePath("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	if (!empty($_GET["force"])) {
		CSRF::verify();
		$force = true;
	} else {
		$force = false;
	}
	
	Lock::enforce("bigtree_pages", $page->ID, "admin/modules/pages/_locked.php", $force);
	
	// Grab template information
	if (!empty($page->Template) && $page->Template != "!") {
		$template = new Template($page->Template);
	}
	
	$bigtree["form_action"] = "update";
	include Router::getIncludePath("admin/modules/pages/_form.php");
	