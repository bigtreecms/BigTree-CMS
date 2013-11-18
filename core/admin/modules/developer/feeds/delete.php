<?
	$admin->deleteFeed(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Feed");
	BigTree::redirect(DEVELOPER_ROOT."feeds/");
?>