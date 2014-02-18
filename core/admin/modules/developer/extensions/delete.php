<?
	$admin->deleteExtension($bigtree["commands"][0]);
	$admin->growl("Developer","Uninstalled Extension");
	BigTree::redirect(DEVELOPER_ROOT."extensions/");
?>