<?php
	$bigtree["current_page"] = $page;
	$bigtree["resources"] = $page["resources"];
	
	if ($page["id"] === "") {
		$admin->stop("You have reached an invalid edit page.");	
	}
	
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
	
	$bigtree["form_action"] = "update";
	include BigTree::path("admin/modules/pages/_form.php");
