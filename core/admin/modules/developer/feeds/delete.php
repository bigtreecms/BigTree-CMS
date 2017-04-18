<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	CSRF::verify();
	
	$feed = new Feed($_GET["id"]);
	$feed->delete();

	Utils::growl("Developer","Deleted Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	