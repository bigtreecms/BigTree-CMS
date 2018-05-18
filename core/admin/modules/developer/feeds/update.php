<?php
	$admin->verifyCSRFToken();
	$admin->updateFeed(end($bigtree["path"]),$_POST["name"],$_POST["description"],$_POST["table"],$_POST["type"],$_POST["settings"],$_POST["fields"]);
	
	$admin->growl("Developer","Updated Feed");
	BigTree::redirect(DEVELOPER_ROOT."feeds/");
