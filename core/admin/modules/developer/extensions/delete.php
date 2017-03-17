<?
	$admin->verifyCSRFToken();
	$admin->deleteExtension($_GET["id"]);
	$admin->growl("Developer","Uninstalled Extension");

	BigTree::redirect(DEVELOPER_ROOT."extensions/");
?>