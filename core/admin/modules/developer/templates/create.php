<?php
	namespace BigTree;
	
	CSRF::verify();
	
	// Let's see if the ID has already been used.
	if (SQL::exists("bigtree_templates", $_POST["id"])) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Used";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	}
	
	$template = Template::create($_POST["id"], $_POST["name"], $_POST["routed"], $_POST["level"],
								 $_POST["module"], $_POST["resources"], $_POST["hooks"]);
	
	if ($template === false) {
		$_SESSION["bigtree_admin"]["saved"] = $_POST;
		$_SESSION["bigtree_admin"]["error"] = "ID Invalid";
		Router::redirect(DEVELOPER_ROOT."templates/add/");
	}
	
	Utils::growl("Developer", "Created Template");
	Router::redirect(DEVELOPER_ROOT."templates/");
	