<?
	$admin->verifyCSRFToken();

	$admin->deleteSetting($_GET["id"]);
	
	$admin->growl("Developer","Deleted Setting");
	BigTree::redirect(DEVELOPER_ROOT."settings/");
?>