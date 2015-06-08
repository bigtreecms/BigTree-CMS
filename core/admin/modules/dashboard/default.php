<?php
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = $cms->getSetting("bigtree-internal-revision");
	if ($current_revision < BIGTREE_REVISION && $admin->Level > 1) {
		BigTree::redirect(ADMIN_ROOT."developer/upgrade/database/");
	}
	
	include BigTree::path("admin/modules/dashboard/panes/analytics.php");
	include BigTree::path("admin/modules/dashboard/panes/pending-changes.php");
	include BigTree::path("admin/modules/dashboard/panes/messages.php");	
?>