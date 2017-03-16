<?
	$admin->verifyCSRFToken();
	$admin->deleteCallout($_GET["id"]);
	
	$admin->growl("Developer","Deleted Callout");
	BigTree::redirect(DEVELOPER_ROOT."callouts/");
?>