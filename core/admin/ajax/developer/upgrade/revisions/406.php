<?php
	// BigTree 4.4.3
	file_put_contents(SERVER_ROOT."cron-run.php", '<?php
	$server_root = str_replace("cron-run.php", "", strtr(__FILE__, "\\\\", "/"));	
	include $server_root."core/cron.php";
');
	BigTree::setPermissions(SERVER_ROOT."cron-run.php");

	echo BigTree::json([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.3"
	]);

	$admin->updateInternalSettingValue("bigtree-internal-revision", 406);
