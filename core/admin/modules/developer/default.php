<?php
	namespace BigTree;
	
	Router::setLayout("new");
	
	// Check whether our database is running the latest revision of BigTree or not.
	$current_revision = Setting::value("bigtree-internal-revision");
	
	if ($current_revision < BIGTREE_REVISION && Auth::user()->Level > 1) {
		Router::redirect(DEVELOPER_ROOT."upgrade/scripts/");
	}

	// Check for updates
	include Router::getIncludePath("admin/modules/developer/upgrade/_update-list.php");

	if (empty($showing_updates)) {
		Extension::runHooks("markup", "developer-top");
?>
<developer-landing></developer-landing>
<?php
		Extension::runHooks("markup", "developer-bottom");
	}
