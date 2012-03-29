<?
	// Get the version, check if the user has access to the page the version refers to.
	$revision = $admin->getPageRevision($_GET["id"]);
	$access = $admin->getPageAccessLevel($revision["page"]);
	if ($access != "p") {
		$admin->stop("You must be a publisher to manage revisions.");
	}
	
	foreach ($revision as $key => $val) {
		$$key = $val;
	}
	
	// See if we have an existing draft, if so load its changes.  Otherwise start a new list.
	$existing = $admin->getPageChanges($revision["page"]);
	if ($existing) {
		$changes = $existing["changes"];
	} else {
		$changes = array();
	}

	$changes["title"] = $title;
	$changes["meta_keywords"] = $meta_keywords;
	$changes["meta_description"] = $meta_description;
	$changes["template"] = $template;
	$changes["external"] = $external;
	$changes["new_window"] = $new_window;
	// These two already are json encoded.  We don't want it encoded twice so we decode it here first.
	$changes["resources"] = json_decode($resources,true);
	$changes["callouts"] = json_decode($callouts,true);
	
	if ($existing) {
		// Update an existing draft with our changes and new author
		$admin->updatePendingChange($existing["id"],$changes);
	} else {
		// If we don't have an existing copy, make a new draft.
		$admin->createPendingChange("bigtree_pages",$revision["page"],$changes);
	}
	
	$admin->growl("Pages","Loaded Saved Revision");
	header("Location: ".$admin_root."pages/edit/".$revision["page"]."/");
	die();
?>