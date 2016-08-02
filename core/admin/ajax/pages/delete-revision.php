<?php
	namespace BigTree;
	
	$page = Page::getRevision($_GET["id"]);
	
	// Force publisher access
	if ($page->UserAccessLevel != "p") {
		Auth::stop("You must be a publisher to manage revisions.");
	}
	
	// Delete the revision
	$page->deleteRevision($_GET["id"]);
	