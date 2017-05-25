<?
	$bigtree["current_page"] = $page;
	$bigtree["resources"] = $page["resources"];
	
	// Show the properties section
	include BigTree::path("admin/modules/pages/_properties.php");
	
	// Check for a page lock
	if (!empty($_GET["force"])) {
		$admin->verifyCSRFToken();
		$force = true;
	} else {
		$force = false;
	}
	
	$admin->lockCheck("bigtree_pages",$page["id"],"admin/modules/pages/_locked.php",$force);
	
	// Grab template information
	$template_data = $cms->getTemplate($page["template"]);

	// Audit Trail link
	$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/audit/search/?table=bigtree_pages&entry=".$page["id"],"icon" => "trail","title" => "View Audit Trail");		

	// Provide developers a nice handy link for edit/return of this form
	if ($admin->Level > 1) {
		$bigtree["subnav_extras"][] = array("link" => ADMIN_ROOT."developer/templates/edit/".$page["template"]."/?return=".$page["id"],"icon" => "setup","title" => "Edit Current Template in Developer");
	}
	
	$bigtree["form_action"] = "update";
	include BigTree::path("admin/modules/pages/_form.php");
?>