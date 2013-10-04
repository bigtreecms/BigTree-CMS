<?
	$api->disconnect();
	$admin->growl("$name API","Disconnected");
	BigTree::redirect(DEVELOPER_ROOT."services/$route/");
?>