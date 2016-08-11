<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global Page $page
	 */
	
	$bigtree["resources"] = $page->Resources;
	
	// Show the properties section
	include Router::getIncludePath("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	Lock::enforce("bigtree_pages", $page->ID, "admin/modules/pages/_locked.php", $force);
	
	// Grab template information
	$template = new Template($page->Template);
	
	// Provide developers a nice handy link for edit/return of this form and the audit trail
	if (Auth::user()->Level > 1) {
		$bigtree["subnav_extras"][] = array(
			"link" => ADMIN_ROOT."developer/audit/search/?table=bigtree_pages&entry=".$page->ID,
			"icon" => "trail",
			"title" => "View Audit Trail"
		);
		
		$bigtree["subnav_extras"][] = array(
			"link" => ADMIN_ROOT."developer/templates/edit/".$page->Template."/?return=".$page->ID,
			"icon" => "setup",
			"title" => "Edit Current Template in Developer"
		);
	}
	
	$bigtree["form_action"] = "update";
	include Router::getIncludePath("admin/modules/pages/_form.php");
	