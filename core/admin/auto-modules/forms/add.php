<?php
	namespace BigTree;
	
	$bigtree["tags"] = array();
	$bigtree["access_level"] = $admin->getAccessLevel($bigtree["module"]);
	
	Router::includeFile("admin/auto-modules/forms/_form.php");
	