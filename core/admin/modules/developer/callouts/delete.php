<?php
	namespace BigTree;
	
	/**
	 * @global array $bigtree
	 */

	$callout = new Callout(end($bigtree["path"]));
	$callout->delete();
	
	Utils::growl("Developer","Deleted Callout");
	
	Router::redirect(DEVELOPER_ROOT."callouts/");