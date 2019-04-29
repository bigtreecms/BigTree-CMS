<?php
	namespace BigTree;
	
	// BigTree 4.4.3
	FileSystem::createFile(SERVER_ROOT."cron-run.php", '<?php
	$server_root = str_replace("cron-run.php", "", strtr(__FILE__, "\\\\", "/"));	
	include $server_root."core/cron.php";
');

	echo JSON::encode([
		"complete" => true,
		"response" => "Upgrading to BigTree 4.4.3"
	]);

	Setting::updateValue("bigtree-internal-revision", 406);
