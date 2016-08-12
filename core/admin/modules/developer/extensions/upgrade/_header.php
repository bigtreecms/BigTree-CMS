<?php
	namespace BigTree;
	
	$updater = new Updater($_GET["id"]);
	$page_link = DEVELOPER_ROOT."extensions/upgrade/";
	$page_vars = "?id=".urlencode($_GET["id"])."&url=".urlencode($_GET["url"]);