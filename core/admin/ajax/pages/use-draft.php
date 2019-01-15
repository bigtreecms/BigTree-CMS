<?php
	$admin->verifyCSRFToken();
	
	// Get the version, check if the user has access to the page the version refers to.
	$revision = $admin->getPageRevision($_GET["id"]);
	$access = $admin->getPageAccessLevel($revision["page"]);
	
	if ($access != "p") {
?>
<div class="container">
	<section>
		<h3>Error</h3>
		<p>You must be a publisher to manage revisions.</p>
	</section>
</div>
<?php
		$admin->stop();
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
	$changes["meta_description"] = $meta_description;
	$changes["template"] = $template;
	$changes["external"] = $external;
	$changes["new_window"] = $new_window;
	// Resources are already are json encoded.
	$changes["resources"] = json_decode($resources, true);
	
	if ($existing) {
		// Update an existing draft with our changes and new author
		$admin->updatePendingChange($existing["id"], $changes);
		$admin->deallocateResources("bigtree_pages", "p".$existing["id"]);
		$change_id = $existing["id"];
	} else {
		// If we don't have an existing copy, make a new draft.
		$change_id = $admin->createPendingChange("bigtree_pages", $revision["page"], $changes);
	}

	$admin->allocateResources("bigtree_pages", "p".$change_id);
	
	$admin->growl("Pages","Loaded Saved Revision");
	BigTree::redirect(ADMIN_ROOT."pages/edit/".$revision["page"]."/");
?>