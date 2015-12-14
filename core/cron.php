<?php
	// If we're not currently bootstrapped, bootstrap
	if (!isset($cms)) {
		$server_root = str_replace("core/cron.php","",strtr(__FILE__, "\\", "/"));
		include $server_root."custom/environment.php";
		include $server_root."custom/settings.php";
		include $server_root."core/bootstrap.php";		
	}

	$admin = new BigTreeAdmin;
	$admin->runCron();
