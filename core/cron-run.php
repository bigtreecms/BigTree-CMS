<?php
	$server_root = str_replace("cron-run.php", "", strtr(__FILE__, "\\", "/"));	
	include $server_root."core/cron.php";
	