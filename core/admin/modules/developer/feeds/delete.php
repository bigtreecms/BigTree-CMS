<?
	$admin->verifyCSRFToken();
	$admin->deleteFeed($_GET["id"]);

	$admin->growl("Developer","Deleted Feed");
	BigTree::redirect(DEVELOPER_ROOT."feeds/");
?>