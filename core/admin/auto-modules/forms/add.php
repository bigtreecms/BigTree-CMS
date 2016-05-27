<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 */
	
	$bigtree["tags"] = array();
	$bigtree["access_level"] = $module->UserAccessLevel;
	
	include Router::getIncludePath("admin/auto-modules/forms/_form.php");
	