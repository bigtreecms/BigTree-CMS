<?php
	namespace BigTree;
	
	// Prevent including files outside feed-options
	$type = FileSystem::getSafePath($_POST["type"]);

	$table = $_POST["table"];
	$data = json_decode(str_replace(array("\r","\n"),array('\r','\n'),$_POST["data"]),true);

	include Router::getIncludePath("admin/ajax/developer/feed-options/$type.php");
