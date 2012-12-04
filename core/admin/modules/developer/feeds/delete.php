<?
	$admin->deleteFeed(end($bigtree["commands"]));

	$admin->growl("Developer","Deleted Feed");
	BigTree::redirect($developer_root."feeds/");
?>