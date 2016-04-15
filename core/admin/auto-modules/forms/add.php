<?php
	namespace BigTree;
	
	$bigtree["tags"] = array();
	$bigtree["access_level"] = $admin->getAccessLevel($bigtree["module"]);
	
	include Router::getIncludePath("admin/auto-modules/forms/_form.php");
	