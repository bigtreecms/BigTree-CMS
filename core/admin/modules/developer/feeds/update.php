<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	if (is_string($_POST["settings"])) {
		$_POST["settings"] = array_filter((array) json_decode($_POST["settings"], true));
	}
	
	$feed = new Feed(Router::$Command, ["BigTree\Admin", "catch404"]);
	$feed->update($_POST["name"], $_POST["description"], $_POST["table"], $_POST["type"], $_POST["settings"], $_POST["fields"]);
	
	Admin::growl("Developer", "Updated Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	