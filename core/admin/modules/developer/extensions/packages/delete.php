<?
	$admin->deletePackage($bigtree["commands"][0]);
	$admin->growl("Deleted Package","Extensions & Packages");
	BigTree::redirect(DEVELOPER_ROOT."extensions/packages/");
?>