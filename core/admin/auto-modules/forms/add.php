<?php
	$bigtree["tags"] = array();
	$bigtree["access_level"] = null;
	
	if (!empty($bigtree["module"])) {
		$bigtree["access_level"] = $admin->getAccessLevel($bigtree["module"]);
	}
	
	include BigTree::path("admin/auto-modules/forms/_form.php");
