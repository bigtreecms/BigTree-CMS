<?
	if (!file_exists($server_root."backup.sql") || (time() - filemtime($server_root."backup.sql")) > (24*60*60)) {
		exec("mysqldump ".$config["db"]["name"]." --user=".$config["db"]["user"]." --password=".$config["db"]["password"]." > ".$server_root."backup.sql");
	}
?>