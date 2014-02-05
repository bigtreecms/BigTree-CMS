<?
	$admin->deletePackage($bigtree["commands"][0]);
	$admin->growl("Developer","Uninstalled Package");
	BigTree::redirect(DEVELOPER_ROOT."packages/");
?>