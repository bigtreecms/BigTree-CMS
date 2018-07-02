<?php
	if (!$bigtree["current_page"]["parent"] ||
		 $bigtree["current_page"]["parent"] == -1 ||
		 $bigtree["access_level"] != "p" ||
		 $admin->getPageAccessLevel($bigtree["current_page"]["parent"]) != "p") {
		$admin->stop("Access denied.");
	}
	
	$page = $cms->getPage($bigtree["current_page"]["id"]);
	$page["nav_title"] = $page["nav_title"]." (Copy)";
	$page["title"] = $page["title"]." (Copy)";
	$page["route"] = "";
	
	$id = $admin->createPendingPage($page);
	$admin->growl("Pages", "Duplicated Page");
	
	BigTree::redirect(ADMIN_ROOT."pages/edit/p".$id."/");
	