<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 */
	
	$bigtree["tags"] = [];
	$bigtree["access_level"] = $module->UserAccessLevel;
	
	include Router::getIncludePath("admin/auto-modules/forms/_form.php");
	