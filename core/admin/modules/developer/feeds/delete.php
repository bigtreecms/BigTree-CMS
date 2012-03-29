<?
	$admin->deleteFeed(end($commands));

	$admin->growl("Developer","Deleted Feed");
	header("Location: ".$developer_root."feeds/view/");
	die();
?>