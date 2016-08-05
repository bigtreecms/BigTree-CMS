<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */
	
	$feed = new Feed(end($bigtree["commands"]));
	$feed->delete();

	Utils::growl("Developer","Deleted Feed");
	Router::redirect(DEVELOPER_ROOT."feeds/");
	