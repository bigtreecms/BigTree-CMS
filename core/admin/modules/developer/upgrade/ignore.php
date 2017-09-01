<?php
	namespace BigTree;
	
	CSRF::verify();
	
	$versions = json_decode($_GET["versions"], true);
	
	foreach ($versions as $version) {
		// Prevent cookie screwage
		$version = str_replace(["[", "]"], "", $version);
		Cookie::create("bigtree_admin[ignored_update][$version]", true, "+1 year");
	}
	
	Utils::growl("Developer", "Ignored Updates");
	Router::redirect(DEVELOPER_ROOT);
	