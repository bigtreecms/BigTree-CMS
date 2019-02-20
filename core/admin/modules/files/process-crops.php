<?php
	namespace BigTree;
	
	$form = new \stdClass;
	$form->Root = ADMIN_ROOT."files/";

	include Router::getIncludePath("admin/auto-modules/forms/process-crops.php");
