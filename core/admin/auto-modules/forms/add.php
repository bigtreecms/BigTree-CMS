<?php
	namespace BigTree;

	/**
	 * @global Module $module
	 */
	
	$tags = [];
	$access_level = $module->UserAccessLevel;
	
	include Router::getIncludePath("admin/auto-modules/forms/_form.php");
	