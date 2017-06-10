<?
	$admin->verifyCSRFToken();
	$admin->deletePackage($_GET["id"]);
	$admin->growl("Developer","Uninstalled Package");

	BigTree::redirect(DEVELOPER_ROOT."packages/");
?>