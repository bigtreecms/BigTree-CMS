<?php
	namespace BigTree;
	
	CSRF::verify();
	
	// Get the version, check if the user has access to the page the version refers to.
	$revision = new PageRevision($_GET["id"]);
	$page = new Page($revision->Page);
	
	if ($page->UserAccessLevel != "p") {
		Auth::stop("You must be a publisher to manage revisions.", Router::getIncludePath("admin/layouts/_error.php"));
	}
	
	// See if we have an existing draft, if so load its changes.  Otherwise start a new list.
	$existing = $page->PendingChange;
	
	if ($existing) {
		$changes = $existing->Changes;
	} else {
		$changes = array();
	}

	$changes["title"] = $revision->Title;
	$changes["meta_description"] = $revision->MetaDescription;
	$changes["template"] = $revision->Template;
	$changes["external"] = $revision->External;
	$changes["new_window"] = $revision->NewWindow;
	$changes["resources"] = $revision->Resources;
	
	if ($existing) {
		// Update an existing draft with our changes and new author
		$existing->Changes = $changes;
		$existing->save();
		Resource::deallocate("bigtree_pages", "p".$existing->ID);
	} else {
		// If we don't have an existing copy, make a new draft.
		PendingChange::create("bigtree_pages", $revision->Page, $changes);
	}
	
	Utils::growl("Pages","Loaded Saved Revision");
	Router::redirect(ADMIN_ROOT."pages/edit/".$revision->Page."/");
