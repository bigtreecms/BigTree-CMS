<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$feed = new Feed(end($bigtree["path"]));
	$feed->update($_POST["name"], $_POST["description"], $_POST["table"], $_POST["type"], $_POST["options"], $_POST["fields"]);
	
	Utils::growl("Developer", "Updated Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	