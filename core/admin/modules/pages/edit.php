<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 * @global Page $page
	 */
	
	$bigtree["resources"] = $page_id->Resources;
	
	// Show the properties section
	include Router::getIncludePath("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	$force = isset($_GET["force"]) ? $_GET["force"] : false;
	Lock::enforce("bigtree_pages", $page_id->ID, "admin/modules/pages/_locked.php", $force);
	
	// Grab template information
	$template = new Template($page_id->Template);
	
	// Provide developers a nice handy link for edit/return of this form and the audit trail
	if (Auth::user()->Level > 1) {
		$bigtree["subnav_extras"][] = array(
			"link" => ADMIN_ROOT."developer/audit/search/?table=bigtree_pages&entry=".$page_id->ID,
			"icon" => "trail",
			"title" => "View Audit Trail"
		);
		
		$bigtree["subnav_extras"][] = array(
			"link" => ADMIN_ROOT."developer/templates/edit/".$page_id->Template."/?return=".$page_id->ID,
			"icon" => "setup",
			"title" => "Edit Current Template in Developer"
		);
	}
	
	$bigtree["form_action"] = "update";
	include Router::getIncludePath("admin/modules/pages/_form.php");
	